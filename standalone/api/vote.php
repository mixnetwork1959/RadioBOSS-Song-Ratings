<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
rbsr_api_cors();

if (!rbsr_installed()) {
    rbsr_json_response(['message' => 'Song Ratings is not installed.'], 503);
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    rbsr_json_response(['message' => 'Method not allowed.'], 405);
}

$raw = file_get_contents('php://input');
if (!is_string($raw) || strlen($raw) > 8192) {
    rbsr_json_response(['message' => 'Invalid request body.'], 400);
}
try {
    $params = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
} catch (JsonException $exception) {
    rbsr_json_response(['message' => 'Invalid JSON.'], 400);
}
if (!is_array($params)) {
    rbsr_json_response(['message' => 'Invalid rating.'], 400);
}

$station = rbsr_clean_slug((string) ($params['station'] ?? ''));
$artist = rbsr_clean_track((string) ($params['artist'] ?? ''));
$title = rbsr_clean_track((string) ($params['title'] ?? ''));
$rating = strtolower(trim((string) ($params['rating'] ?? '')));
$visitor = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($params['visitor'] ?? '')) ?? '';
if (
    rbsr_station($station) === null
    || $artist === ''
    || $title === ''
    || !in_array($rating, ['dislike', 'ok', 'love'], true)
    || strlen($visitor) < 16
    || strlen($visitor) > 128
) {
    rbsr_json_response(['message' => 'Invalid rating.'], 400);
}

$ip = rbsr_request_ip();
if (!rbsr_rate_limit('vote-visitor|' . $visitor . '|' . $ip, 20, 60)) {
    rbsr_json_response(['message' => 'Please wait a moment before rating again.'], 429);
}
if (!rbsr_rate_limit('vote-address|' . $ip, 120, 60)) {
    rbsr_json_response(['message' => 'Too many rating requests. Please try again later.'], 429);
}

try {
    $songKey = rbsr_song_key($station, $artist, $title);
    $visitorHash = hash_hmac('sha256', $visitor, rbsr_secret());
    $now = gmdate('Y-m-d H:i:s');
    $table = rbsr_quote_identifier(rbsr_table_name());
    $statement = rbsr_db()->prepare(
        "INSERT INTO {$table}
            (station, song_key, artist, title, visitor_hash, rating, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            artist = VALUES(artist), title = VALUES(title),
            rating = VALUES(rating), updated_at = VALUES(updated_at)"
    );
    $statement->execute([$station, $songKey, $artist, $title, $visitorHash, $rating, $now, $now]);
    rbsr_json_response([
        'success' => true,
        'station' => $station,
        'songKey' => $songKey,
        'rating' => $rating,
        'counts' => rbsr_counts($station, $songKey),
    ]);
} catch (Throwable $exception) {
    rbsr_json_response(['message' => 'The rating could not be saved.'], 500);
}

