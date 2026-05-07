<?php
declare(strict_types=1);

session_start();
require_once 'config.php';

// Simple output escaping helper for safe HTML rendering.
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

// Admin password hash comes from .env (loaded in config.php) via $_ENV.
// Keeping only a hash in config is safer than storing a plain-text password.
$adminPasswordHash = (string)($_ENV['ADMIN_PASSWORD_HASH'] ?? '');
$hasPasswordConfig = $adminPasswordHash !== '';

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

// Basic login rate limiting (IP-based).
// Limits repeated failed attempts to reduce brute-force risk on admin login.
const ADMIN_MAX_FAILED_ATTEMPTS = 5;
const ADMIN_ATTEMPT_WINDOW_SECONDS = 600; // 10 minutes
const ADMIN_LOCKOUT_SECONDS = 600; // 10 minutes
const ADMIN_RATE_LIMIT_FILE = __DIR__ . '/admin_rate_limit.json';

/**
 * Get client IP for rate limiting.
 * Uses REMOTE_ADDR to avoid trusting spoofable forwarded headers by default.
 */
function getClientIp(): string
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    return $ip !== '' ? $ip : 'unknown';
}

/**
 * Read rate-limit state from JSON file.
 *
 * @return array<string, array{attempts: int[], lockout_until: int}>
 */
function loadRateLimitState(): array
{
    if (!is_file(ADMIN_RATE_LIMIT_FILE)) {
        return [];
    }

    $raw = @file_get_contents(ADMIN_RATE_LIMIT_FILE);
    if ($raw === false || $raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Persist rate-limit state to JSON file.
 *
 * @param array<string, array{attempts: int[], lockout_until: int}> $state
 */
function saveRateLimitState(array $state): void
{
    $dir = dirname(ADMIN_RATE_LIMIT_FILE);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    @file_put_contents(ADMIN_RATE_LIMIT_FILE, json_encode($state, JSON_PRETTY_PRINT), LOCK_EX);
}

$error = '';
$isLoggedIn = !empty($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

// Handle logout request (POST-only + CSRF).
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['logout'])
    && $isLoggedIn
) {
    $token = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals((string)$_SESSION['admin_csrf'], $token)) {
        http_response_code(403);
        exit('Invalid request.');
    }

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
    }
    session_destroy();
    header('Location: admin.php');
    exit;
}

// Handle login request (POST-only + CSRF).
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['password'])
    && !$isLoggedIn
) {
    $token = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals((string)$_SESSION['admin_csrf'], $token)) {
        http_response_code(403);
        exit('Invalid request.');
    }

    $password = (string)($_POST['password'] ?? '');
    $now = time();
    $ip = getClientIp();
    $state = loadRateLimitState();

    // Cleanup stale entries to keep storage small.
    foreach ($state as $key => $value) {
        $entryAttempts = array_values(array_filter(
            (array)($value['attempts'] ?? []),
            static fn ($ts): bool => is_int($ts) && ($now - $ts) <= ADMIN_ATTEMPT_WINDOW_SECONDS
        ));
        $entryLockout = (int)($value['lockout_until'] ?? 0);
        if ($entryLockout <= $now && count($entryAttempts) === 0) {
            unset($state[$key]);
        } else {
            $state[$key] = ['attempts' => $entryAttempts, 'lockout_until' => $entryLockout];
        }
    }

    $entry = $state[$ip] ?? ['attempts' => [], 'lockout_until' => 0];
    $attempts = array_values(array_filter(
        (array)$entry['attempts'],
        static fn ($ts): bool => is_int($ts) && ($now - $ts) <= ADMIN_ATTEMPT_WINDOW_SECONDS
    ));
    $lockoutUntil = (int)($entry['lockout_until'] ?? 0);

    if ($lockoutUntil > $now) {
        $remaining = $lockoutUntil - $now;
        $error = 'Too many failed attempts. Try again in ' . (string)max(1, (int)ceil($remaining / 60)) . ' minute(s).';
    }

    if ($error !== '') {
        // Locked out, skip password verification.
    } elseif (!$hasPasswordConfig) {
        $error = 'Admin password is not configured. Set ADMIN_PASSWORD_HASH in .env.';
    } elseif (password_verify($password, $adminPasswordHash)) {
        $state[$ip] = ['attempts' => [], 'lockout_until' => 0];
        saveRateLimitState($state);
        session_regenerate_id(true);
        $_SESSION['is_admin'] = true;
        header('Location: admin.php');
        exit;
    } else {
        $attempts[] = $now;

        if (count($attempts) >= ADMIN_MAX_FAILED_ATTEMPTS) {
            $state[$ip] = ['attempts' => [], 'lockout_until' => $now + ADMIN_LOCKOUT_SECONDS];
            saveRateLimitState($state);
            $error = 'Too many failed attempts. Try again in 10 minutes.';
        } else {
            $state[$ip] = ['attempts' => $attempts, 'lockout_until' => 0];
            saveRateLimitState($state);
        }
    }

    if ($error === '') {
        $error = 'Invalid password.';
    }
}

$isLoggedIn = !empty($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

$totalMessages = 0;
$todayMessages = 0;
$messagesPerDay = [];
$latestMessages = [];

if ($isLoggedIn) {
    // 1) Total messages sent.
    $stmt = $conn->prepare('SELECT COUNT(*) FROM messages');
    $stmt->execute();
    $stmt->bind_result($totalMessages);
    $stmt->fetch();
    $stmt->close();

    // 2) Messages sent today.
    $stmt = $conn->prepare('SELECT COUNT(*) FROM messages WHERE DATE(created_at) = CURDATE()');
    $stmt->execute();
    $stmt->bind_result($todayMessages);
    $stmt->fetch();
    $stmt->close();

    // 3) Messages per day (latest 7 rows by date).
    $stmt = $conn->prepare(
        'SELECT DATE(created_at) AS day, COUNT(*) AS total
         FROM messages
         GROUP BY DATE(created_at)
         ORDER BY day DESC
         LIMIT 7'
    );
    $stmt->execute();
    $stmt->bind_result($day, $dayTotal);
    while ($stmt->fetch()) {
        $messagesPerDay[] = [
            'day' => (string)$day,
            'total' => (int)$dayTotal,
        ];
    }
    $stmt->close();

    // 4) Latest 20 messages for admin viewer.
    $stmt = $conn->prepare(
        'SELECT id, receiver_name, message, sender_type, created_at
         FROM messages
         ORDER BY created_at DESC
         LIMIT 20'
    );
    $stmt->execute();
    $stmt->bind_result($id, $receiverName, $message, $senderType, $createdAt);
    while ($stmt->fetch()) {
        $latestMessages[] = [
            'id' => (int)$id,
            'receiver_name' => (string)$receiverName,
            'message' => (string)$message,
            'sender_type' => $senderType !== null ? (string)$senderType : '',
            'created_at' => (string)$createdAt,
        ];
    }
    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <style>
        :root {
            --bg: #f6f7fb;
            --card: #ffffff;
            --text: #1f2937;
            --muted: #6b7280;
            --accent: #e11d48;
            --border: #e5e7eb;
            --shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text);
            background: linear-gradient(160deg, #fff1f2 0%, var(--bg) 40%, #eef2ff 100%);
            min-height: 100vh;
        }

        .container {
            width: min(1100px, 94%);
            margin: 28px auto;
        }

        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: var(--shadow);
            padding: 20px;
        }

        .login-wrap {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 20px;
        }

        .login-card {
            width: min(420px, 96%);
        }

        h1, h2, h3 {
            margin: 0 0 12px;
        }

        .muted {
            color: var(--muted);
            margin: 0 0 16px;
        }

        label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
        }

        input[type="password"] {
            width: 100%;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 12px;
            font-size: 1rem;
            margin-bottom: 12px;
        }

        .btn {
            border: 0;
            border-radius: 10px;
            background: var(--accent);
            color: #fff;
            padding: 10px 14px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn.secondary {
            background: #111827;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 16px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 16px;
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 760px;
        }

        th, td {
            text-align: left;
            padding: 12px 10px;
            border-bottom: 1px solid var(--border);
            vertical-align: top;
        }

        th {
            font-size: 0.9rem;
            color: var(--muted);
            font-weight: 700;
        }

        .pill {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            background: #ffe4e6;
            color: #9f1239;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .view-btn {
            background: #0f172a;
            color: #fff;
            border: 0;
            border-radius: 8px;
            padding: 8px 10px;
            cursor: pointer;
        }

        .error {
            background: #ffe4e6;
            border: 1px solid #fecdd3;
            color: #9f1239;
            border-radius: 10px;
            padding: 10px 12px;
            margin-bottom: 12px;
        }

        .modal {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 18px;
            background: rgba(2, 6, 23, 0.55);
            z-index: 50;
        }

        .modal.active {
            display: flex;
        }

        .modal-card {
            width: min(640px, 100%);
            background: #fff;
            border-radius: 14px;
            padding: 18px;
            box-shadow: 0 24px 40px rgba(2, 6, 23, 0.25);
        }

        .modal-message {
            white-space: pre-wrap;
            line-height: 1.55;
            margin: 10px 0 16px;
            color: #111827;
        }

        @media (max-width: 860px) {
            .grid {
                grid-template-columns: 1fr;
            }

            .topbar {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
<?php if (!$isLoggedIn): ?>
    <div class="login-wrap">
        <div class="card login-card">
            <h1>Admin Login</h1>
            <p class="muted">Enter password to access dashboard.</p>
            <?php if (!$hasPasswordConfig): ?>
                <div class="error">Missing <code>ADMIN_PASSWORD_HASH</code> in <code>.env</code>.</div>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <div class="error"><?php echo e($error); ?></div>
            <?php endif; ?>
            <form method="post" action="admin.php" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo e((string)$_SESSION['admin_csrf']); ?>">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
                <button class="btn" type="submit">Login</button>
            </form>
        </div>
    </div>
<?php else: ?>
    <div class="container">
        <div class="topbar">
            <h1>Valentine Admin Dashboard</h1>
            <form method="post" action="admin.php">
                <input type="hidden" name="csrf_token" value="<?php echo e((string)$_SESSION['admin_csrf']); ?>">
                <input type="hidden" name="logout" value="1">
                <button type="submit" class="btn secondary">Logout</button>
            </form>
        </div>

        <div class="grid">
            <div class="card">
                <h3>Total Messages Sent</h3>
                <p class="stat-value"><?php echo e((string)$totalMessages); ?></p>
            </div>
            <div class="card">
                <h3>Messages Sent Today</h3>
                <p class="stat-value"><?php echo e((string)$todayMessages); ?></p>
            </div>
            <div class="card">
                <h3>Days Returned</h3>
                <p class="stat-value"><?php echo e((string)count($messagesPerDay)); ?></p>
            </div>
        </div>

        <div class="card" style="margin-bottom:16px;">
            <h2>Messages Per Day (Last 7 Days)</h2>
            <?php if (count($messagesPerDay) === 0): ?>
                <p class="muted">No data found.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Day</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($messagesPerDay as $row): ?>
                                <tr>
                                    <td><?php echo e($row['day']); ?></td>
                                    <td><?php echo e((string)$row['total']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2>Latest 20 Messages</h2>
            <?php if (count($latestMessages) === 0): ?>
                <p class="muted">No messages yet.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Receiver Name</th>
                                <th>Message</th>
                                <th>Sender Type</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($latestMessages as $row): ?>
                                <?php
                                $fullMessage = $row['message'];
                                $preview = mb_strimwidth($fullMessage, 0, 80, '...');
                                $senderLabel = $row['sender_type'] !== '' ? $row['sender_type'] : 'N/A';
                                ?>
                                <tr>
                                    <td><?php echo e($row['receiver_name']); ?></td>
                                    <td><?php echo e($preview); ?></td>
                                    <td><span class="pill"><?php echo e($senderLabel); ?></span></td>
                                    <td><?php echo e($row['created_at']); ?></td>
                                    <td>
                                        <button
                                            type="button"
                                            class="view-btn"
                                            data-message="<?php echo e($fullMessage); ?>"
                                            data-receiver="<?php echo e($row['receiver_name']); ?>"
                                            onclick="openMessageModal(this)"
                                        >
                                            View
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div id="messageModal" class="modal" aria-hidden="true">
        <div class="modal-card">
            <h3 id="modalTitle">Message</h3>
            <div id="modalMessage" class="modal-message"></div>
            <button type="button" class="btn" onclick="closeMessageModal()">Close</button>
        </div>
    </div>

    <script>
        function openMessageModal(button) {
            const modal = document.getElementById('messageModal');
            const modalTitle = document.getElementById('modalTitle');
            const modalMessage = document.getElementById('modalMessage');

            const receiver = button.getAttribute('data-receiver') || 'Receiver';
            const message = button.getAttribute('data-message') || '';

            modalTitle.textContent = 'Message for ' + receiver;
            modalMessage.textContent = message;
            modal.classList.add('active');
            modal.setAttribute('aria-hidden', 'false');
        }

        function closeMessageModal() {
            const modal = document.getElementById('messageModal');
            modal.classList.remove('active');
            modal.setAttribute('aria-hidden', 'true');
        }

        document.getElementById('messageModal').addEventListener('click', function (event) {
            if (event.target === this) {
                closeMessageModal();
            }
        });
    </script>
<?php endif; ?>
</body>
</html>
