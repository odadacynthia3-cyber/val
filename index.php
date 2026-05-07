<?php
// index.php
declare(strict_types=1);

session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secret Valentine Message</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="valentine-bg">
    <div class="page-wrap">
        <div class="card form-card fade-up">
            <h1>Send a Secret Valentine</h1>
            <p class="subtext">Write a sweet message and share the private link.</p>

            <form action="submit.php" method="POST" class="valentine-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

                <label for="receiver_name">Receiver's Name</label>
                <input type="text" id="receiver_name" name="receiver_name" maxlength="100" required>

                <label for="message">Message</label>
                <textarea id="message" name="message" rows="5" maxlength="1000" required></textarea>

                <label for="sender_type">Sender Type (Optional)</label>
                <select id="sender_type" name="sender_type">
                    <option value="">Prefer not to say</option>
                    <option value="boy">Boy</option>
                    <option value="girl">Girl</option>
                </select>

                <button type="submit" class="btn">Generate Secret Link</button>
            </form>
        </div>
    </div>
</body>
</html>
