<?php
// confirmation.php
// Shows generated private message URL and social share actions.

declare(strict_types=1);

require_once 'config.php';

$code = strtoupper(trim((string)($_GET['code'] ?? '')));

if (!preg_match('/^[A-F0-9]{8}$/', $code)) {
    header('Location: index.php');
    exit;
}

$messageLink = rtrim($APP_BASE_URL, '/') . '/message.php?code=' . urlencode($code);

$shareText = 'You have a secret Valentine message waiting for you!';
$whatsAppUrl = 'https://wa.me/?text=' . rawurlencode($shareText . ' ' . $messageLink);
$telegramUrl = 'https://t.me/share/url?url=' . rawurlencode($messageLink) . '&text=' . rawurlencode($shareText);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Message Link Ready</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="valentine-bg">
    <div class="page-wrap">
        <div class="card confirmation-card fade-up">
            <h1>Your Secret Link Is Ready</h1>
            <p class="subtext">Share this private URL with your Valentine:</p>

            <div class="link-box" id="messageLink"><?php echo htmlspecialchars($messageLink, ENT_QUOTES, 'UTF-8'); ?></div>

            <div class="button-row">
                <a class="btn" href="<?php echo htmlspecialchars($whatsAppUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">Share on WhatsApp</a>
                <a class="btn btn-alt" href="<?php echo htmlspecialchars($telegramUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">Share on Telegram</a>
                <button class="btn btn-copy" type="button" onclick="copyLink()">Copy Link</button>
            </div>

            <a class="back-link" href="index.php">Create another message</a>
        </div>
    </div>

    <script>
        function copyLink() {
            const text = document.getElementById('messageLink').innerText;
            navigator.clipboard.writeText(text).then(() => {
                alert('Link copied to clipboard.');
            }).catch(() => {
                alert('Unable to copy automatically. Please copy manually.');
            });
        }
    </script>
</body>
</html>
