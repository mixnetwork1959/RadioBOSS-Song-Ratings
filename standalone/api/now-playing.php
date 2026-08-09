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
if ($station === '' || rbsr_station($station) === null) {
    rbsr_json_response(['message' => 'Unknown station.'], 404);
}

try {
    rbsr_json_response(rbsr_now_playing($station));
} catch (Throwable $exception) {
    rbsr_json_response(['message' => $exception->getMessage()], 502);
}

