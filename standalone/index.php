<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/widget.php';

if (!rbsr_installed()) {
    header('Location: setup/');
    exit;
}

$stations = rbsr_enabled_stations();
$stationSlug = rbsr_clean_slug((string) ($_GET['station'] ?? array_key_first($stations)));
if (rbsr_station($stationSlug) === null) {
    $stationSlug = (string) array_key_first($stations);
}
$mode = (string) ($_GET['mode'] ?? 'ratings') === 'player' ? 'player' : 'ratings';
$station = rbsr_station($stationSlug) ?? [];
$widgetConfig = rbsr_widget_config($stationSlug);
?>
<!doctype html>
<html lang="<?= rbsr_h((string) ($station['language'] ?? 'en')) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= rbsr_h((string) ($station['name'] ?? 'Radio')) ?> — Song Ratings</title>
    <link rel="stylesheet" href="<?= rbsr_h(rbsr_base_url()) ?>/assets/widget.css?v=<?= rbsr_h(RBSR_STANDALONE_VERSION) ?>">
</head>
<body class="rbsr-public-page">
<main class="rbsr-public-shell">
    <?= rbsr_render_widget($stationSlug, $mode) ?>
    <?php if (count($stations) > 1): ?>
        <nav class="rbsr-station-links" aria-label="Stations">
            <?php foreach ($stations as $slug => $item): ?>
                <a href="?station=<?= rbsr_h((string) $slug) ?>&amp;mode=<?= rbsr_h($mode) ?>"<?= $slug === $stationSlug ? ' class="active"' : '' ?>><?= rbsr_h((string) ($item['name'] ?? $slug)) ?></a>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>
</main>
<script>window.RBSR_STANDALONE_CONFIG=<?= json_encode($widgetConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="<?= rbsr_h(rbsr_base_url()) ?>/assets/widget.js?v=<?= rbsr_h(RBSR_STANDALONE_VERSION) ?>"></script>
</body>
</html>

