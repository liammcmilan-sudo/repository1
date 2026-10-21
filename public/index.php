<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
ensure_visitor();
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Website Search</title>
    <link rel="stylesheet" href="/style.css">
</head>
<body>
<header class="topbar">
    <a class="brand" href="/">Website Search</a>
</header>
<main>
    <p class="message" role="note">This site creates a random first-party browser key to support search. It is stored in a secure, HttpOnly cookie for 30 days and is not linked to your identity. Clear this site's cookies to remove it.</p>
    <section class="account">
        <div class="site-content">
            <?php readfile(CONTENT_DIR . '/index.html'); ?>
        </div>
        <div class="search-area">
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
</main>
<script>
document.getElementById('search-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    const status = document.getElementById('search-status');
    const results = document.getElementById('search-results');
    results.replaceChildren();
    status.textContent = 'Searching...';
    try {
        const response = await fetch('/api/search.php?q=' + encodeURIComponent(document.getElementById('search-query').value), {
            credentials: 'same-origin'
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
</body>
</html>