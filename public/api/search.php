<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if (!is_string($apiKey) || !preg_match('/^vsk_[a-f0-9]{64}$/', $apiKey)) {
    http_response_code(401);
    echo json_encode(['error' => 'A valid X-API-Key header is required.']);
    exit;
}

$statement = db()->prepare('SELECT id FROM users WHERE api_key_hash = :key_hash');
$statement->execute(['key_hash' => hash('sha256', $apiKey)]);
if (!$statement->fetch()) {
    http_response_code(401);
    echo json_encode(['error' => 'The search key is invalid or has been replaced.']);
    exit;
}

$query = trim((string) ($_GET['q'] ?? ''));
if ($query === '' || strlen($query) > 200) {
    http_response_code(400);
    echo json_encode(['error' => 'Provide a search query of 1 to 200 characters.']);
    exit;
}

$results = [];
$terms = preg_split('/\s+/u', mb_strtolower($query), -1, PREG_SPLIT_NO_EMPTY) ?: [];
foreach (glob(CONTENT_DIR . '/*.html') ?: [] as $file) {
    $html = file_get_contents($file);
    if ($html === false) {
        continue;
    }
    $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    $normalizedText = mb_strtolower($text);
    foreach ($terms as $term) {
        if (!str_contains($normalizedText, $term)) {
            continue 2;
        }
    }

    $position = mb_stripos($text, $terms[0] ?? $query);
    $start = max(0, ($position === false ? 0 : $position) - 70);
    $snippet = mb_substr($text, $start, 220);
    if ($start > 0) {
        $snippet = '...' . $snippet;
    }
    if ($start + 220 < mb_strlen($text)) {
        $snippet .= '...';
    }

    preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $titleMatch);
    $results[] = [
        'title' => trim(strip_tags($titleMatch[1] ?? basename($file, '.html'))),
        'page' => basename($file),
        'snippet' => $snippet,
    ];
}

echo json_encode(['results' => $results], JSON_INVALID_UTF8_SUBSTITUTE);