<?php
declare(strict_types=1);

const CONTENT_DIR = __DIR__ . '/../content';

function start_app_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_set_cookie_params([
            'httponly' => true,
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function db(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $storageDir = __DIR__ . '/../storage';
    if (!is_dir($storageDir) && !mkdir($storageDir, 0700, true) && !is_dir($storageDir)) {
        throw new RuntimeException('Unable to create the storage directory.');
    }

    $pdo = new PDO('sqlite:' . $storageDir . '/app.sqlite', options: [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            api_key_hash TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )'
    );

    return $pdo;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function valid_csrf(): bool
{
    $submitted = $_POST['csrf_token'] ?? '';
    return is_string($submitted)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $submitted);
}

function provision_key(): array
{
    $key = 'vsk_' . bin2hex(random_bytes(32));
    return [$key, hash('sha256', $key)];
}

function current_user(): ?array
{
    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    $statement = db()->prepare('SELECT id, email FROM users WHERE id = :id');
    $statement->execute(['id' => $_SESSION['user_id']]);
    $user = $statement->fetch();

    if (!$user) {
        unset($_SESSION['user_id']);
        return null;
    }

    return $user;
}