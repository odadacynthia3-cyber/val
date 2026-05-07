<?php
// submit.php
// Handles form submission, stores message, and redirects to confirmation page.

declare(strict_types=1);

session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$csrfToken = (string)($_POST['csrf_token'] ?? '');
if ($csrfToken === '' || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    http_response_code(403);
    exit('Invalid request.');
}

// Read and trim form values.
$receiverName = trim((string)($_POST['receiver_name'] ?? ''));
$message = trim((string)($_POST['message'] ?? ''));
$senderTypeInput = strtolower(trim((string)($_POST['sender_type'] ?? '')));
$senderType = in_array($senderTypeInput, ['boy', 'girl'], true) ? $senderTypeInput : null;

// Basic validation.
if ($receiverName === '' || $message === '') {
    http_response_code(422);
    exit('Receiver name and message are required.');
}

if (mb_strlen($receiverName) > 100 || mb_strlen($message) > 1000) {
    http_response_code(422);
    exit('Input exceeds allowed length.');
}

function generateCode(int $lengthBytes = 4): string
{
    return strtoupper(bin2hex(random_bytes($lengthBytes)));
}

$insertSql = 'INSERT INTO messages (code, receiver_name, message, sender_type) VALUES (?, ?, ?, ?)';
$insertStmt = $conn->prepare($insertSql);

if (!$insertStmt) {
    error_log('Failed to prepare insert statement.');
    http_response_code(500);
    exit('Unable to save your message right now.');
}

$code = '';
$inserted = false;
$maxAttempts = 5;

for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
    $code = generateCode();
    $insertStmt->bind_param('ssss', $code, $receiverName, $message, $senderType);

    try {
        $insertStmt->execute();
        $inserted = true;
        break;
    } catch (mysqli_sql_exception $e) {
        // Retry only for duplicate key collision on code (rare).
        if ((int)$e->getCode() === 1062 && $attempt < $maxAttempts) {
            continue;
        }

        error_log('Failed to save message: ' . $e->getMessage());
        $insertStmt->close();
        $conn->close();
        http_response_code(500);
        exit('Unable to save your message right now.');
    }
}

if (!$inserted) {
    $insertStmt->close();
    $conn->close();
    http_response_code(500);
    exit('Unable to save your message right now.');
}

$insertStmt->close();
$conn->close();

header('Location: confirmation.php?code=' . urlencode($code));
exit;
