# Visitor Search Access

A small PHP 8.1+ application that lets visitors create accounts, receive an individual search API key, and search your editable HTML pages.

## Run locally

Requirements: PHP with PDO SQLite and the `mbstring` extension.

From the repository root, run:

```sh
php -S 127.0.0.1:8000 -t public
```

Open <http://127.0.0.1:8000>. Accounts and hashed API keys are stored in `storage/app.sqlite`, outside the public document root.

## Add your website content

Edit `content/index.html` with the HTML you want to render for signed-in visitors. Add more `.html` files in `content/` to include them in search. This folder is treated as trusted administrator content: only place HTML here that you control, because active HTML can run scripts in the visitor's browser.

Each account receives a unique `vsk_...` key after registration. The raw key is shown only once; the visitor can replace it to issue a new key. The search endpoint is `GET /api/search.php?q=...` and expects the key in the `X-API-Key` header. Search matches every query term against the text of the `.html` files in `content/`.

Use HTTPS in production. Also configure your web server to apply appropriate rate limits and account abuse controls before opening public registration.