<?php
declare(strict_types=1);

const CONTENT_DIR = __DIR__ . '/../content';

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
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS visitors (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            api_key_hash TEXT NOT NULL UNIQUE,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_seen_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )'
    );

    return $pdo;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function provision_key(): array
{
    $key = 'vsk_' . bin2hex(random_bytes(32));
    return [$key, hash('sha256', $key)];
}

function ensure_visitor(): int
{
    $key = $_COOKIE['visitor_key'] ?? '';
    if (is_string($key) && preg_match('/^vsk_[a-f0-9]{64}$/', $key)) {
        $statement = db()->prepare('SELECT id FROM visitors WHERE api_key_hash = :key_hash');
        $statement->execute(['key_hash' => hash('sha256', $key)]);
        $visitorId = $statement->fetchColumn();
        if ($visitorId !== false) {
            $statement = db()->prepare('UPDATE visitors SET last_seen_at = CURRENT_TIMESTAMP WHERE id = :id');
            $statement->execute(['id' => $visitorId]);
            return (int) $visitorId;
        }
    }

    [$key, $keyHash] = provision_key();
    $statement = db()->prepare('INSERT INTO visitors (api_key_hash) VALUES (:key_hash)');
    $statement->execute(['key_hash' => $keyHash]);
    setcookie('visitor_key', $key, [
        'expires' => time() + 60 * 60 * 24 * 30,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    return (int) db()->lastInsertId();
}