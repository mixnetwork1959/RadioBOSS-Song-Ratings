<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/widget.php';

if (!rbsr_installed()) {
    http_response_code(503);
    echo '<!doctype html><html><body><p>Song Ratings is not installed. <a href="setup/">Open setup</a>.</p></body></html>';
    exit;
}

$stations = rbsr_enabled_stations();
$stationSlug = rbsr_clean_slug((string) ($_GET['station'] ?? array_key_first($stations)));
$mode = (string) ($_GET['mode'] ?? 'ratings') === 'player' ? 'player' : 'ratings';
$widgetConfig = rbsr_widget_config($stationSlug);
if ($widgetConfig === []) {
    http_response_code(404);
}
?>
<!doctype html>
<html lang="<?= rbsr_h((string) (rbsr_station($stationSlug)['language'] ?? 'en')) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Song Ratings</title>
    <link rel="stylesheet" href="<?= rbsr_h(rbsr_base_url()) ?>/assets/widget.css?v=<?= rbsr_h(RBSR_STANDALONE_VERSION) ?>">
</head>
<body class="rbsr-embed-body">
<?= rbsr_render_widget($stationSlug, $mode) ?>
<script>window.RBSR_STANDALONE_CONFIG=<?= json_encode($widgetConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="<?= rbsr_h(rbsr_base_url()) ?>/assets/widget.js?v=<?= rbsr_h(RBSR_STANDALONE_VERSION) ?>"></script>
</body>
</html>

