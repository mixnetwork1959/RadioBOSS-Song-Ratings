<?php

if (!defined('ABSPATH')) {
    exit;
}

final class RBSR_Core
{
    public const OPTION = 'rbsr_settings';
    public const TABLE_SUFFIX = 'rbsr_song_votes';
    public const LEGACY_TABLE_SUFFIX = 'rap_song_votes';
    public const MAX_STATIONS = 4;

    public static function boot(): void
    {
        add_action('rest_api_init', ['RBSR_REST', 'register_routes']);
        add_action('wp_enqueue_scripts', ['RBSR_Shortcodes', 'register_assets']);
        add_action('admin_enqueue_scripts', ['RBSR_Admin', 'enqueue_assets']);
        add_action('admin_menu', ['RBSR_Admin', 'register_menu']);
        add_action('admin_init', ['RBSR_Admin', 'register_settings']);
        add_action('admin_notices', ['RBSR_Admin', 'setup_notice']);

        add_shortcode('radioboss_rating_player', ['RBSR_Shortcodes', 'player']);
        add_shortcode('radioboss_song_ratings', ['RBSR_Shortcodes', 'ratings']);

        if (!shortcode_exists('radio_song_rating')) {
            add_shortcode('radio_song_rating', ['RBSR_Shortcodes', 'ratings']);
        }
    }

    public static function activate(): void
    {
        global $wpdb;

        $table = self::table();
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            station varchar(80) NOT NULL,
            song_key char(64) NOT NULL,
            artist varchar(255) NOT NULL,
            title varchar(255) NOT NULL,
            visitor_hash char(64) NOT NULL,
            rating varchar(10) NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY song_visitor (station, song_key, visitor_hash),
            KEY song_counts (station, song_key, rating),
            KEY updated_at (updated_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        if (get_option(self::OPTION, null) === null) {
            add_option(self::OPTION, self::defaults());
        }

        if (get_option('rbsr_setup_complete', null) === null) {
            add_option('rbsr_setup_complete', '0');
        }

        self::migrate_legacy_votes();
    }

    public static function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_SUFFIX;
    }

    public static function defaults(): array
    {
        $defaults = [];
        $defaults['default_source'] = 'api';
        for ($index = 1; $index <= self::MAX_STATIONS; $index++) {
            $defaults["station_{$index}_enabled"] = $index === 1 ? '1' : '0';
            $defaults["station_{$index}_name"] = $index === 1 ? 'Example Radio' : '';
            $defaults["station_{$index}_slug"] = $index === 1 ? 'example-radio' : '';
            $defaults["station_{$index}_api"] = '';
            $defaults["station_{$index}_stream"] = '';
            $defaults["station_{$index}_catalog"] = '';
            $defaults["station_{$index}_color"] = $index === 1 ? '#2563eb' : '#7c3aed';
        }
        return $defaults;
    }

    public static function settings(): array
    {
        return wp_parse_args((array) get_option(self::OPTION, []), self::defaults());
    }

    public static function stations(): array
    {
        $settings = self::settings();
        $stations = [];

        for ($index = 1; $index <= self::MAX_STATIONS; $index++) {
            if (empty($settings["station_{$index}_enabled"])) {
                continue;
            }

            $slug = sanitize_key((string) ($settings["station_{$index}_slug"] ?? ''));
            if ($slug === '') {
                continue;
            }

            $stations[$slug] = [
                'name' => sanitize_text_field((string) ($settings["station_{$index}_name"] ?? $slug)),
                'slug' => $slug,
                'api' => esc_url_raw((string) ($settings["station_{$index}_api"] ?? '')),
                'stream' => esc_url_raw((string) ($settings["station_{$index}_stream"] ?? '')),
                'catalog' => esc_url_raw((string) ($settings["station_{$index}_catalog"] ?? '')),
                'color' => sanitize_hex_color((string) ($settings["station_{$index}_color"] ?? '')) ?: '#2563eb',
            ];
        }

        return $stations;
    }

    public static function station(string $slug): ?array
    {
        $stations = self::stations();
        return $stations[sanitize_key($slug)] ?? null;
    }

    public static function song_key(string $station, string $artist, string $title): string
    {
        return hash('sha256', strtolower(trim($station . '|' . $artist . '|' . $title)));
    }

    public static function normalize_track_text(string $value): string
    {
        $value = html_entity_decode(wp_strip_all_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = str_replace(["’", "‘", "`", "´"], "'", $value);
        $value = str_replace(["–", "—", "−"], '-', $value);
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
        return strtolower($value);
    }

    public static function catalog_track(string $stationSlug, string $artist, string $title)
    {
        $station = self::station($stationSlug);
        if ($station === null || empty($station['catalog'])) {
            return new WP_Error('rbsr_catalog_not_configured', __('The SongSync songs.json URL is not configured for this station.', 'radioboss-song-ratings'), ['status' => 503]);
        }

        $cacheKey = 'rbsr_catalog_' . md5($stationSlug . '|' . $station['catalog']);
        $catalog = get_transient($cacheKey);
        if (!is_array($catalog)) {
$response = wp_safe_remote_get($station['catalog'], [
                'timeout' => 10,
                'redirection' => 3,
                'headers' => ['Accept' => 'application/json'],
            ]);
            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
                return new WP_Error('rbsr_catalog_unavailable', __('The SongSync songs.json catalog is unavailable.', 'radioboss-song-ratings'), ['status' => 502]);
            }
            $decoded = json_decode(wp_remote_retrieve_body($response), true);
            if (!is_array($decoded)) {
                return new WP_Error('rbsr_catalog_invalid', __('The SongSync songs.json catalog is invalid.', 'radioboss-song-ratings'), ['status' => 502]);
            }

            $catalog = [];
            foreach ($decoded as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $a = sanitize_text_field((string) ($row['artist'] ?? ''));
                $t = sanitize_text_field((string) ($row['title'] ?? ''));
                if ($a === '' || $t === '') {
                    continue;
                }
                $key = self::normalize_track_text($a) . '|' . self::normalize_track_text($t);
                if (!isset($catalog[$key])) {
                    $catalog[$key] = ['artist' => $a, 'title' => $t];
                }
            }
            set_transient($cacheKey, $catalog, 5 * MINUTE_IN_SECONDS);
        }

        $lookup = self::normalize_track_text($artist) . '|' . self::normalize_track_text($title);
        if (!isset($catalog[$lookup])) {
            return new WP_Error('rbsr_track_not_in_catalog', __('This track was not found in this station\'s SongSync catalog.', 'radioboss-song-ratings'), ['status' => 404]);
        }
        return $catalog[$lookup];
    }

    public static function counts(string $station, string $songKey): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT rating, COUNT(*) AS total FROM ' . self::table()
                    . ' WHERE station = %s AND song_key = %s GROUP BY rating',
                $station,
                $songKey
            ),
            ARRAY_A
        );

        $counts = ['dislike' => 0, 'ok' => 0, 'love' => 0];
        foreach ((array) $rows as $row) {
            $rating = (string) ($row['rating'] ?? '');
            if (array_key_exists($rating, $counts)) {
                $counts[$rating] = (int) $row['total'];
            }
        }
        return $counts;
    }

    public static function status(int $dislikes, int $okay, int $loves, int $total): array
    {
        if ($total < 5) {
            return ['key' => 'insufficient', 'label' => __('Not enough votes', 'radioboss-song-ratings'), 'icon' => '⚪'];
        }

        $positive = ($loves / $total) * 100;
        $negative = ($dislikes / $total) * 100;
        $score = (($loves - $dislikes) / $total) * 100;

        if ($negative >= 60 && $score <= -30) {
            return ['key' => 'review', 'label' => __('Review', 'radioboss-song-ratings'), 'icon' => '🔴'];
        }
        if ($positive >= 70 && $score >= 40) {
            return ['key' => 'popular', 'label' => __('Popular', 'radioboss-song-ratings'), 'icon' => '🟢'];
        }
        return ['key' => 'observe', 'label' => __('Observe', 'radioboss-song-ratings'), 'icon' => '🟡'];
    }

    private static function migrate_legacy_votes(): void
    {
        global $wpdb;

        if (get_option('rbsr_legacy_migration_checked')) {
            return;
        }

        $legacy = $wpdb->prefix . self::LEGACY_TABLE_SUFFIX;
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($legacy)));
        if ($exists === $legacy) {
            $target = self::table();
            $migrated = $wpdb->query(
                "INSERT IGNORE INTO {$target}
                    (station, song_key, artist, title, visitor_hash, rating, created_at, updated_at)
                 SELECT station, song_key, artist, title, visitor_hash, rating, created_at, updated_at
                 FROM {$legacy}"
            );
            if ($migrated === false) {
                return;
            }
        }

        update_option('rbsr_legacy_migration_checked', '1', false);
    }
}
