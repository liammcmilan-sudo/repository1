<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
start_app_session();

$error = '';
$notice = $_SESSION['notice'] ?? '';
$newKey = $_SESSION['new_key'] ?? '';
unset($_SESSION['notice'], $_SESSION['new_key']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!valid_csrf()) {
        $error = 'Your session expired. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');

        if ($action === 'logout') {
            $_SESSION = [];
            session_regenerate_id(true);
            $_SESSION['notice'] = 'You have signed out.';
            header('Location: /');
            exit;
        }

        if ($action === 'register') {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
                $error = 'Enter a valid email and a password with at least 12 characters.';
            } else {
                [$plainKey, $keyHash] = provision_key();
                try {
                    $statement = db()->prepare(
                        'INSERT INTO users (email, password_hash, api_key_hash)
                         VALUES (:email, :password_hash, :api_key_hash)'
                    );
                    $statement->execute([
                        'email' => $email,
                        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                        'api_key_hash' => $keyHash,
                    ]);
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = (int) db()->lastInsertId();
                    $_SESSION['new_key'] = $plainKey;
                    $_SESSION['notice'] = 'Account created. Copy your search key now; it is shown only once.';
                    header('Location: /');
                    exit;
                } catch (PDOException $exception) {
                    if ($exception->getCode() === '23000') {
                        $error = 'An account with that email already exists.';
                    } else {
                        throw $exception;
                    }
                }
            }
        } elseif ($action === 'login') {
            $statement = db()->prepare('SELECT id, password_hash FROM users WHERE email = :email');
            $statement->execute(['email' => $email]);
            $user = $statement->fetch();
            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $user['id'];
                $_SESSION['notice'] = 'Signed in. Your existing key is not displayed again.';
                header('Location: /');
                exit;
            }
            $error = 'Email or password is incorrect.';
        } elseif ($action === 'rotate_key' && current_user()) {
            [$plainKey, $keyHash] = provision_key();
            $statement = db()->prepare('UPDATE users SET api_key_hash = :key_hash WHERE id = :id');
            $statement->execute(['key_hash' => $keyHash, 'id' => $_SESSION['user_id']]);
            $_SESSION['new_key'] = $plainKey;
            $_SESSION['notice'] = 'Your search key was replaced. Copy the new key now.';
            header('Location: /');
            exit;
        }
    }
}

$user = current_user();
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Visitor Search Access</title>
    <link rel="stylesheet" href="/style.css">
</head>
<body>
<header class="topbar">
    <a class="brand" href="/">Visitor Search Access</a>
    <?php if ($user): ?>
        <span class="account-label"><?= e($user['email']) ?></span>
    <?php endif; ?>
</header>
<main>
    <?php if ($notice): ?><p class="message" role="status"><?= e($notice) ?></p><?php endif; ?>
    <?php if ($error): ?><p class="message error" role="alert"><?= e($error) ?></p><?php endif; ?>

    <?php if ($user): ?>
        <section class="account">
            <div class="section-heading">
                <p class="eyebrow">Authenticated visitor</p>
                <h1>Your search access</h1>
                <p class="intro">Your account has one active key for website search.</p>
            </div>
            <label for="api-key-input"><?= $newKey ? 'New API key, shown once' : 'Your saved search key' ?></label>
            <input id="api-key-input" class="key-field" value="<?= e($newKey) ?>" placeholder="Paste your vsk_ search key" autocomplete="off" onfocus="this.select()">
            <?php if (!$newKey): ?><p class="search-status">The key is not stored on this server in readable form. Paste the key you saved to search.</p><?php endif; ?>
            <div class="actions">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="rotate_key">
                    <button type="submit" class="secondary">Replace search key</button>
                </form>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="logout">
                    <button type="submit" class="quiet">Sign out</button>
                </form>
            </div>
            <div class="search-area">
                <h2>Website content</h2>
                <div class="site-content">
                    <?php readfile(CONTENT_DIR . '/index.html'); ?>
                </div>
                <h2>Search this site</h2>
                <form id="search-form" class="search-form">
                    <label class="visually-hidden" for="search-query">Search query</label>
                    <input id="search-query" type="search" placeholder="Search website content" required>
                    <button type="submit">Search</button>
                </form>
                <p id="search-status" class="search-status" aria-live="polite"></p>
                <ul id="search-results" class="search-results"></ul>
            </div>
        </section>
    <?php else: ?>
        <section class="welcome">
            <p class="eyebrow">Visitor search access</p>
            <h1>Sign in to search</h1>
            <p class="intro">Create an account to receive a personal search key, or sign in to continue.</p>
            <div class="forms">
                <form method="post" class="auth-form">
                    <h2>Create account</h2>
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="register">
                    <label for="register-email">Email</label>
                    <input id="register-email" name="email" type="email" autocomplete="email" required>
                    <label for="register-password">Password <span>(12+ characters)</span></label>
                    <input id="register-password" name="password" type="password" autocomplete="new-password" minlength="12" required>
                    <button type="submit">Create account and key</button>
                </form>
                <form method="post" class="auth-form">
                    <h2>Sign in</h2>
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="login">
                    <label for="login-email">Email</label>
                    <input id="login-email" name="email" type="email" autocomplete="email" required>
                    <label for="login-password">Password</label>
                    <input id="login-password" name="password" type="password" autocomplete="current-password" required>
                    <button type="submit">Sign in</button>
                </form>
            </div>
        </section>
    <?php endif; ?>
</main>
<?php if ($user): ?>
<script>
document.getElementById('search-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    const status = document.getElementById('search-status');
    const results = document.getElementById('search-results');
    results.replaceChildren();
    status.textContent = 'Searching...';
    try {
        const response = await fetch('/api/search.php?q=' + encodeURIComponent(document.getElementById('search-query').value), {
            headers: { 'X-API-Key': document.getElementById('api-key-input').value.trim() }
        });
        const data = await response.json();
        if (!response.ok) throw new Error(data.error || 'Search failed.');
        status.textContent = data.results.length ? `${data.results.length} result(s)` : 'No matching pages.';
        for (const result of data.results) {
            const item = document.createElement('li');
            const title = document.createElement('strong');
            title.textContent = result.title;
            const snippet = document.createElement('p');
            snippet.textContent = result.snippet;
            item.append(title, snippet);
            results.append(item);
        }
    } catch (error) {
        status.textContent = error.message;
    }
});
</script>
<?php endif; ?>
</body>
</html>