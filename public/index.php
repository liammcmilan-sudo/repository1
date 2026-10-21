<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
ensure_visitor();
readfile(CONTENT_DIR . '/index.html');