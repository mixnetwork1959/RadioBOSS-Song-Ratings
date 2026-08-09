<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
rbsr_api_cors();

if (!rbsr_installed()) {
    rbsr_json_response(['message' => 'Song Ratings is not installed.'], 503);
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    rbsr_json_response(['message' => 'Method not allowed.'], 405);
}

$station = rbsr_clean_slug((string) ($_GET['station'] ?? ''));
$artist = rbsr_clean_track((string) ($_GET['artist'] ?? ''));
$title = rbsr_clean_track((string) ($_GET['title'] ?? ''));
if ($station === '' || rbsr_station($station) === null || $artist === '' || $title === '') {
    rbsr_json_response(['message' => 'Station, artist, and title are required.'], 400);
}

try {
    $songKey = rbsr_song_key($station, $artist, $title);
    rbsr_json_response([
        'station' => $station,
        'songKey' => $songKey,
        'counts' => rbsr_counts($station, $songKey),
    ]);
} catch (Throwable $exception) {
    rbsr_json_response(['message' => 'The ratings could not be loaded.'], 500);
}

