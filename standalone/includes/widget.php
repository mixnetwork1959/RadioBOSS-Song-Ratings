<?php

declare(strict_types=1);

function rbsr_widget_config(string $stationSlug): array
{
    $station = rbsr_station($stationSlug);
    if ($station === null) {
        return [];
    }
    $language = in_array((string) ($station['language'] ?? 'en'), ['de', 'en'], true) ? (string) $station['language'] : 'en';
    return [
        'station' => $stationSlug,
        'pollInterval' => 15000,
        'endpoints' => [
            'nowPlaying' => rbsr_base_url() . '/api/now-playing.php',
            'ratings' => rbsr_base_url() . '/api/ratings.php',
            'vote' => rbsr_base_url() . '/api/vote.php',
        ],
        'labels' => rbsr_labels($language),
    ];
}

function rbsr_render_widget(string $stationSlug, string $mode = 'ratings'): string
{
    $station = rbsr_station($stationSlug);
    if ($station === null) {
        return '<p class="rbsr-error">Unknown or disabled station.</p>';
    }
    $mode = $mode === 'player' ? 'player' : 'ratings';
    $theme = (array) ($station['theme'] ?? []);
    $accent = rbsr_color((string) ($theme['accent'] ?? ''), '#2563eb');
    $background = rbsr_color((string) ($theme['background'] ?? ''), '#111827');
    $text = rbsr_color((string) ($theme['text'] ?? ''), '#eef4ff');
    $radius = (string) ($theme['radius'] ?? 'rounded') === 'square' ? 'square' : 'rounded';
    $size = (string) ($theme['size'] ?? 'normal') === 'compact' ? 'compact' : 'normal';
    $showCover = !array_key_exists('show_cover', $theme) || !empty($theme['show_cover']);
    $logo = rbsr_http_url((string) ($station['logo_url'] ?? ''), true);
    $language = in_array((string) ($station['language'] ?? 'en'), ['de', 'en'], true) ? (string) $station['language'] : 'en';
    $labels = rbsr_labels($language);
    $id = 'rbsr-' . bin2hex(random_bytes(6));
    $classes = 'rbsr-widget rbsr-mode-' . $mode . ' rbsr-size-' . $size . ' rbsr-corners-' . $radius;
    if (!$showCover) {
        $classes .= ' rbsr-hide-cover';
    }

    ob_start();
    ?>
    <section
        id="<?= rbsr_h($id) ?>"
        class="<?= rbsr_h($classes) ?>"
        data-station="<?= rbsr_h($stationSlug) ?>"
        data-mode="<?= rbsr_h($mode) ?>"
        data-logo="<?= rbsr_h($logo) ?>"
        style="--rbsr-accent:<?= rbsr_h($accent) ?>;--rbsr-background:<?= rbsr_h($background) ?>;--rbsr-text:<?= rbsr_h($text) ?>"
    >
        <div class="rbsr-head">
            <strong class="rbsr-station-name"><?= rbsr_h((string) ($station['name'] ?? $stationSlug)) ?></strong>
            <span class="rbsr-live"><?= rbsr_h($labels['live']) ?></span>
        </div>
        <div class="rbsr-track">
            <div class="rbsr-cover-wrap">
                <img class="rbsr-cover" src="<?= rbsr_h($logo) ?>" alt=""<?= $logo === '' ? ' hidden' : '' ?>>
                <span class="rbsr-cover-placeholder" aria-hidden="true"<?= $logo !== '' ? ' hidden' : '' ?>>♪</span>
            </div>
            <div class="rbsr-track-text">
                <small><?= rbsr_h($labels['nowPlaying']) ?></small>
                <strong class="rbsr-title"><?= rbsr_h($labels['loading']) ?></strong>
                <span class="rbsr-artist"></span>
            </div>
        </div>
        <?php if ($mode === 'player'): ?>
            <div class="rbsr-controls">
                <button type="button" class="rbsr-play" aria-label="<?= rbsr_h($labels['play']) ?>">▶</button>
                <button type="button" class="rbsr-mute" aria-label="<?= rbsr_h($labels['mute']) ?>" title="<?= rbsr_h($labels['mute']) ?>">🔊</button>
                <input class="rbsr-volume" type="range" min="0" max="1" step="0.05" value="0.8" aria-label="<?= rbsr_h($labels['volume']) ?>">
                <audio class="rbsr-audio" preload="none" playsinline></audio>
            </div>
        <?php endif; ?>
        <div class="rbsr-rating" aria-live="polite">
            <span class="rbsr-question"><?= rbsr_h($labels['question']) ?></span>
            <div class="rbsr-buttons">
                <button type="button" data-rating="dislike" title="<?= rbsr_h($labels['dislike']) ?>" aria-label="<?= rbsr_h($labels['dislike']) ?>" disabled>👎 <span>0</span></button>
                <button type="button" data-rating="ok" title="<?= rbsr_h($labels['okay']) ?>" aria-label="<?= rbsr_h($labels['okay']) ?>" disabled>😐 <span>0</span></button>
                <button type="button" data-rating="love" title="<?= rbsr_h($labels['love']) ?>" aria-label="<?= rbsr_h($labels['love']) ?>" disabled>❤️ <span>0</span></button>
            </div>
            <small class="rbsr-message"></small>
        </div>
    </section>
    <?php
    $html = (string) ob_get_clean();
    $customCss = (string) ($theme['custom_css'] ?? '');
    if ($customCss !== '') {
        $customCss = str_ireplace(['<style', '</style', '<?', '?>'], '', substr($customCss, 0, 5000));
        $html .= '<style>' . $customCss . '</style>';
    }
    return $html;
}

