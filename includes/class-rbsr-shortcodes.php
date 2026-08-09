<?php

if (!defined('ABSPATH')) {
    exit;
}

final class RBSR_Shortcodes
{
    public static function register_assets(): void
    {
        wp_register_style('rbsr-public', RBSR_URL . 'assets/rbsr-public.css', [], RBSR_VERSION);
        wp_register_script('rbsr-public', RBSR_URL . 'assets/rbsr-public.js', [], RBSR_VERSION, true);
    }

    public static function player(array $attributes = []): string
    {
        return self::render($attributes, 'player');
    }

    public static function ratings(array $attributes = []): string
    {
        return self::render($attributes, 'ratings');
    }

    private static function render(array $attributes, string $mode): string
    {
        $stations = RBSR_Core::stations();
        if ($stations === []) {
            return '<p>' . esc_html__('Song Ratings has not been configured yet.', 'radioboss-song-ratings') . '</p>';
        }

        $first = (string) array_key_first($stations);
        $settings = RBSR_Core::settings();
        $defaultSource = (string) ($settings['default_source'] ?? 'api');
        $attributes = shortcode_atts([
            'station' => $first,
            'source' => $defaultSource,
            'show_track' => 'yes',
        ], $attributes, $mode === 'player' ? 'radioboss_rating_player' : 'radioboss_song_ratings');

        $selected = sanitize_key((string) $attributes['station']);
        if (!isset($stations[$selected])) {
            $selected = $first;
        }

        $source = strtolower((string) $attributes['source']) === 'external' ? 'external' : 'api';
        $showTrack = strtolower((string) $attributes['show_track']) !== 'no';
        $id = 'rbsr-' . wp_generate_uuid4();

        wp_enqueue_style('rbsr-public');
        wp_enqueue_script('rbsr-public');
        wp_localize_script('rbsr-public', 'RBSR_CONFIG', [
            'restUrl' => esc_url_raw(rest_url('radioboss-song-ratings/v1/')),
            'labels' => [
                'loading' => __('Loading current song …', 'radioboss-song-ratings'),
                'waiting' => __('Waiting for track metadata …', 'radioboss-song-ratings'),
                'offline' => __('Track information is currently unavailable.', 'radioboss-song-ratings'),
                'unknown' => __('Unknown song', 'radioboss-song-ratings'),
                'thanks' => __('Thank you! Your rating has been counted.', 'radioboss-song-ratings'),
                'voteError' => __('Your rating could not be saved.', 'radioboss-song-ratings'),
                'play' => __('Play live radio', 'radioboss-song-ratings'),
                'pause' => __('Pause live radio', 'radioboss-song-ratings'),
                'mute' => __('Mute', 'radioboss-song-ratings'),
                'unmute' => __('Turn sound on', 'radioboss-song-ratings'),
            ],
        ]);

        $station = $stations[$selected];
        ob_start();
        ?>
        <section
            id="<?php echo esc_attr($id); ?>"
            class="rbsr-widget rbsr-<?php echo esc_attr($mode); ?>"
            data-mode="<?php echo esc_attr($mode); ?>"
            data-source="<?php echo esc_attr($source); ?>"
            data-station="<?php echo esc_attr($selected); ?>"
            style="--rbsr-accent:<?php echo esc_attr($station['color']); ?>"
        >
            <div class="rbsr-head">
                <?php if (count($stations) > 1): ?>
                    <label class="screen-reader-text" for="<?php echo esc_attr($id); ?>-station">
                        <?php esc_html_e('Choose a station', 'radioboss-song-ratings'); ?>
                    </label>
                    <select id="<?php echo esc_attr($id); ?>-station" class="rbsr-station">
                        <?php foreach ($stations as $slug => $item): ?>
                            <option value="<?php echo esc_attr($slug); ?>" data-color="<?php echo esc_attr($item['color']); ?>" <?php selected($slug, $selected); ?>>
                                <?php echo esc_html($item['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <strong class="rbsr-station-name"><?php echo esc_html($station['name']); ?></strong>
                    <input class="rbsr-station" type="hidden" value="<?php echo esc_attr($selected); ?>">
                <?php endif; ?>
                <span class="rbsr-live"><?php esc_html_e('LIVE', 'radioboss-song-ratings'); ?></span>
            </div>

            <?php if ($showTrack): ?>
                <div class="rbsr-track">
                    <div class="rbsr-cover-wrap">
                        <img class="rbsr-cover" src="" alt="" hidden>
                        <span class="rbsr-cover-placeholder" aria-hidden="true">♪</span>
                    </div>
                    <div class="rbsr-track-text">
                        <small><?php esc_html_e('Now Playing', 'radioboss-song-ratings'); ?></small>
                        <strong class="rbsr-title">
                            <?php echo esc_html($source === 'external' ? __('Waiting for track metadata …', 'radioboss-song-ratings') : __('Loading current song …', 'radioboss-song-ratings')); ?>
                        </strong>
                        <span class="rbsr-artist"></span>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($mode === 'player'): ?>
                <div class="rbsr-controls">
                    <button type="button" class="rbsr-play" aria-label="<?php esc_attr_e('Play live radio', 'radioboss-song-ratings'); ?>">▶</button>
                    <button type="button" class="rbsr-mute" aria-label="<?php esc_attr_e('Mute', 'radioboss-song-ratings'); ?>" title="<?php esc_attr_e('Mute', 'radioboss-song-ratings'); ?>">🔊</button>
                    <input class="rbsr-volume" type="range" min="0" max="1" step="0.05" value="0.8" aria-label="<?php esc_attr_e('Volume', 'radioboss-song-ratings'); ?>">
                    <audio class="rbsr-audio" preload="none" playsinline></audio>
                </div>
            <?php endif; ?>

            <div class="rbsr-rating" aria-live="polite">
                <span class="rbsr-question"><?php esc_html_e('How do you like this song?', 'radioboss-song-ratings'); ?></span>
                <div class="rbsr-buttons">
                    <button type="button" data-rating="dislike" title="<?php esc_attr_e('Dislike', 'radioboss-song-ratings'); ?>" aria-label="<?php esc_attr_e('Dislike', 'radioboss-song-ratings'); ?>" disabled>👎 <span>0</span></button>
                    <button type="button" data-rating="ok" title="<?php esc_attr_e("It's okay", 'radioboss-song-ratings'); ?>" aria-label="<?php esc_attr_e("It's okay", 'radioboss-song-ratings'); ?>" disabled>😐 <span>0</span></button>
                    <button type="button" data-rating="love" title="<?php esc_attr_e('Love it', 'radioboss-song-ratings'); ?>" aria-label="<?php esc_attr_e('Love it', 'radioboss-song-ratings'); ?>" disabled>❤️ <span>0</span></button>
                </div>
                <small class="rbsr-message"></small>
            </div>
        </section>
        <?php
        return (string) ob_get_clean();
    }
}
