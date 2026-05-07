<?php
// config.php
// Central app bootstrap + database connection.

declare(strict_types=1);

/**
 * Load key/value pairs from a local .env file into $_ENV.
 * This keeps credentials out of source code while avoiding external dependencies.
 */
function loadEnvFile(string $path): void
{
    if (!is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }

        $parts = explode('=', $trimmed, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $key = trim($parts[0]);
        $value = trim($parts[1]);

        if ($key === '') {
            continue;
        }

        // Strip surrounding quotes if present.
        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        $_ENV[$key] = $value;
        putenv($key . '=' . $value);
    }
}

// Load environment variables from .env at the project root.
loadEnvFile(__DIR__ . '/.env');

$DB_HOST = (string)($_ENV['DB_HOST'] ?? '');
$DB_USER = (string)($_ENV['DB_USER'] ?? '');
$DB_PASS = (string)($_ENV['DB_PASS'] ?? '');
$DB_NAME = (string)($_ENV['DB_NAME'] ?? '');
$APP_BASE_URL = (string)($_ENV['APP_BASE_URL'] ?? 'http://localhost/val');

if ($DB_HOST === '' || $DB_USER === '' || $DB_NAME === '') {
    http_response_code(500);
    exit('Server configuration error.');
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    $conn->set_charset('utf8mb4');
} catch (Throwable $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Internal server error.');
}
