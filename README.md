# Visitor Search Access

A small PHP 8.1+ application that provisions a unique first-party browser key and lets visitors search your editable HTML pages.

## Run locally

Requirements: PHP with PDO SQLite and the `mbstring` extension.

From the repository root, run:

```sh
php -S 127.0.0.1:8000 -t public
```

Open <http://127.0.0.1:8000>. Hashed visitor keys are stored in `storage/app.sqlite`, outside the public document root. A random key is issued automatically in an HttpOnly cookie scoped to the current host and expires after 30 days.

## Add your website content

Edit `content/index.html` with the HTML you want to render for visitors. Add more `.html` files in `content/` to include them in search. This folder is treated as trusted administrator content: only place HTML here that you control, because active HTML can run scripts in the visitor's browser.

Each browser receives a unique `vsk_...` key on its first visit. The key is HttpOnly and is sent automatically for same-origin search requests; JavaScript cannot read it. The page discloses the cookie and its lifetime to visitors. Clearing the site's cookies removes the key; revisiting then provisions a new one. The search endpoint is `GET /api/search.php?q=...`. Search matches every query term against the text of the `.html` files in `content/`.

Use HTTPS in production. Also configure your web server to apply appropriate rate limits and account abuse controls before opening public registration.