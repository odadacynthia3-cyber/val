<?php
// message.php
// Displays the secret message based on unique code.

declare(strict_types=1);

require_once 'config.php';

$code = strtoupper(trim((string)($_GET['code'] ?? '')));

if (!preg_match('/^[A-F0-9]{8}$/', $code)) {
    http_response_code(400);
    exit('Invalid or missing message code.');
}

$sql = 'SELECT receiver_name, message, sender_type FROM messages WHERE code = ? LIMIT 1';
$stmt = $conn->prepare($sql);

if (!$stmt) {
    error_log('Failed to prepare message lookup query.');
    $conn->close();
    http_response_code(500);
    exit('Internal server error.');
}

$stmt->bind_param('s', $code);

try {
    $stmt->execute();
    $result = $stmt->get_result();
    $messageData = $result ? $result->fetch_assoc() : null;
} catch (mysqli_sql_exception $e) {
    error_log('Message lookup failed: ' . $e->getMessage());
    $stmt->close();
    $conn->close();
    http_response_code(500);
    exit('Internal server error.');
}

$stmt->close();
$conn->close();

if (!$messageData) {
    http_response_code(404);
    exit('Message not found. Please check your link.');
}

$receiverName = (string)$messageData['receiver_name'];
$messageText = (string)$messageData['message'];
$senderType = strtolower((string)$messageData['sender_type']);

$revealText = "Oops... your secret admirer stays mysterious for now. <i class='fa-solid fa-envelope'></i>";
if ($senderType === 'boy') {
    $revealText = "Oops... it's the boy you are thinking about <i class='fa-solid fa-envelope'></i>";
} elseif ($senderType === 'girl') {
    $revealText = "Oops... it's the girl you are thinking about <i class='fa-solid fa-envelope'></i>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secret Valentine Message</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="valentine-bg message-page">
    <!-- Dreamy background layers: subtle hearts + sparkles behind content. -->
    <div id="ambientHeartLayer" class="ambient-heart-layer" aria-hidden="true"></div>
    <div id="sparkleLayer" class="sparkle-layer" aria-hidden="true"></div>
    <div id="heartContainer" class="heart-container" aria-hidden="true"></div>

    <div class="page-wrap">
        <div class="card message-card fade-up">
            <h1 class="dear-name-glow">Dear <?php echo htmlspecialchars($receiverName, ENT_QUOTES, 'UTF-8'); ?>,</h1>
            <p id="typedMessage" class="typewriter-text" data-message="<?php echo htmlspecialchars($messageText, ENT_QUOTES, 'UTF-8'); ?>"></p>

            <button id="revealBtn" class="btn" type="button">Reveal Sender <i class="fa-solid fa-heart"></i></button>
            <p id="revealText" class="reveal-text hidden"><?php echo $revealText; ?></p>

            <!-- New "Send Your Own Message" section: appears after reveal animation. -->
            <div id="ownMessageCta" class="own-message-section hidden">
                <h3>Send Your Own Message <i class="fa-solid fa-envelope"></i></h3>
                <p>Create a secret Valentine link and share it with someone special.</p>
                <a href="index.php" class="btn own-message-btn">Send Yours Now</a>
            </div>
        </div>
    </div>

    <!-- Audio setup: hidden player, user-triggered from Reveal button (autoplay-safe). -->
    <audio id="bgMusic" loop preload="metadata">
        <source src="juliush-sweet-memories-romantic-love-music-soft-piano-481504.mp3" type="audio/mpeg">
    </audio>

    <!-- Music toggle button: shown only after music starts. -->
    <button id="musicToggle" class="music-toggle hidden" type="button" aria-label="Pause music">Pause Music</button>

    <script src="hearts.js"></script>
</body>
</html>
