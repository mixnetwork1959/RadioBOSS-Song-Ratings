<?php

if (!defined('ABSPATH')) {
    exit;
}

final class RBSR_REST
{
    private const NAMESPACE = 'radioboss-song-ratings/v1';

    public static function register_routes(): void
    {
        register_rest_route(self::NAMESPACE, '/now-playing/(?P<station>[a-zA-Z0-9_-]+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'now_playing'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/ratings/(?P<station>[a-zA-Z0-9_-]+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'ratings'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/vote', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'vote'],
            'permission_callback' => '__return_true',
        ]);
    }

    public static function now_playing(WP_REST_Request $request)
    {
        $stationSlug = sanitize_key((string) $request['station']);
        $payload = self::now_playing_payload($stationSlug);
        if (is_wp_error($payload)) {
            return $payload;
        }
        return rest_ensure_response($payload);
    }

    public static function ratings(WP_REST_Request $request)
    {
        $stationSlug = sanitize_key((string) $request['station']);
        if (RBSR_Core::station($stationSlug) === null) {
            return new WP_Error('rbsr_unknown_station', __('Unknown station.', 'radioboss-song-ratings'), ['status' => 404]);
        }

        $artist = self::clean_track_text((string) $request->get_param('artist'));
        $title = self::clean_track_text((string) $request->get_param('title'));
        if ($artist === '' || $title === '') {
            return new WP_Error('rbsr_missing_track', __('Artist and title are required.', 'radioboss-song-ratings'), ['status' => 400]);
        }

        $canonical = RBSR_Core::catalog_track($stationSlug, $artist, $title);
        if (is_wp_error($canonical)) {
            return $canonical;
        }
        $artist = $canonical['artist'];
        $title = $canonical['title'];
        $songKey = RBSR_Core::song_key($stationSlug, $artist, $title);

        return rest_ensure_response([
            'station' => $stationSlug,
            'songKey' => $songKey,
            'counts' => RBSR_Core::counts($stationSlug, $songKey),
        ]);
    }

    public static function vote(WP_REST_Request $request)
    {
        global $wpdb;

        $params = (array) $request->get_json_params();
        $station = sanitize_key((string) ($params['station'] ?? ''));
        $artist = self::clean_track_text((string) ($params['artist'] ?? ''));
        $title = self::clean_track_text((string) ($params['title'] ?? ''));
        $rating = sanitize_key((string) ($params['rating'] ?? ''));
        $visitor = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($params['visitor'] ?? '')) ?? '';

        if (
            RBSR_Core::station($station) === null
            || $artist === ''
            || $title === ''
            || !in_array($rating, ['dislike', 'ok', 'love'], true)
            || strlen($visitor) < 16
            || strlen($visitor) > 128
        ) {
            return new WP_Error('rbsr_invalid_vote', __('Invalid rating.', 'radioboss-song-ratings'), ['status' => 400]);
        }

        $remoteAddress = sanitize_text_field((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        $rateKey = 'rbsr_rate_' . hash('sha256', $visitor . '|' . $remoteAddress);
        $attempts = (int) get_transient($rateKey);
        if ($attempts >= 20) {
            return new WP_Error('rbsr_rate_limit', __('Please wait a moment before rating again.', 'radioboss-song-ratings'), ['status' => 429]);
        }
        set_transient($rateKey, $attempts + 1, MINUTE_IN_SECONDS);

        $addressRateKey = 'rbsr_ip_rate_' . hash('sha256', $remoteAddress);
        $addressAttempts = (int) get_transient($addressRateKey);
        if ($addressAttempts >= 120) {
            return new WP_Error('rbsr_address_rate_limit', __('Too many rating requests. Please try again later.', 'radioboss-song-ratings'), ['status' => 429]);
        }
        set_transient($addressRateKey, $addressAttempts + 1, MINUTE_IN_SECONDS);

        $canonical = RBSR_Core::catalog_track($station, $artist, $title);
        if (is_wp_error($canonical)) {
            return $canonical;
        }
        $artist = $canonical['artist'];
        $title = $canonical['title'];
        $songKey = RBSR_Core::song_key($station, $artist, $title);
        $visitorHash = hash_hmac('sha256', $visitor, wp_salt('auth'));
        $now = current_time('mysql', true);
        $table = RBSR_Core::table();

        $sql = $wpdb->prepare(
            "INSERT INTO {$table}
                (station, song_key, artist, title, visitor_hash, rating, created_at, updated_at)
             VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
             ON DUPLICATE KEY UPDATE
                artist = VALUES(artist), title = VALUES(title),
                rating = VALUES(rating), updated_at = VALUES(updated_at)",
            $station,
            $songKey,
            $artist,
            $title,
            $visitorHash,
            $rating,
            $now,
            $now
        );

        if ($wpdb->query($sql) === false) {
            return new WP_Error('rbsr_database_error', __('The rating could not be saved.', 'radioboss-song-ratings'), ['status' => 500]);
        }

        return rest_ensure_response([
            'success' => true,
            'station' => $station,
            'songKey' => $songKey,
            'rating' => $rating,
            'counts' => RBSR_Core::counts($station, $songKey),
        ]);
    }

    private static function now_playing_payload(string $stationSlug)
    {
        $station = RBSR_Core::station($stationSlug);
        if ($station === null || $station['api'] === '') {
            return new WP_Error('rbsr_not_configured', __('The station has not been configured yet.', 'radioboss-song-ratings'), ['status' => 404]);
        }

        $cacheKey = 'rbsr_np_' . md5($stationSlug);
        $cached = get_transient($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $response = wp_safe_remote_get($station['api'], [
            'timeout' => 8,
            'redirection' => 3,
            'headers' => ['Accept' => 'application/json'],
        ]);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return new WP_Error('rbsr_source_unavailable', __('The now-playing source is unavailable.', 'radioboss-song-ratings'), ['status' => 502]);
        }

        try {
            $data = json_decode(wp_remote_retrieve_body($response), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            return new WP_Error('rbsr_invalid_source', __('The now-playing source returned invalid JSON.', 'radioboss-song-ratings'), ['status' => 502]);
        }

        if (!is_array($data)) {
            return new WP_Error('rbsr_invalid_source', __('The now-playing source returned invalid data.', 'radioboss-song-ratings'), ['status' => 502]);
        }

        $current = self::extract_current_song($data);
        $next = self::extract_next_song($data);
        $artist = self::clean_track_text((string) ($current['artist'] ?? ''));
        $title = self::clean_track_text((string) ($current['title'] ?? ''));
        if ($artist === '' || $title === '') {
            return new WP_Error('rbsr_track_unavailable', __('The metadata source did not provide both artist and title.', 'radioboss-song-ratings'), ['status' => 502]);
        }

        $canonical = RBSR_Core::catalog_track($stationSlug, $artist, $title);
        if (is_wp_error($canonical)) {
            return $canonical;
        }
        $artist = $canonical['artist'];
        $title = $canonical['title'];
        $songKey = RBSR_Core::song_key($stationSlug, $artist, $title);

        $stream = $station['stream'];
        if ($stream === '') {
            $stream = esc_url_raw((string) (
                $data['station']['listen_url']
                ?? $data['listen_url']
                ?? $data['stream']
                ?? ''
            ));
        }

        $payload = [
            'station' => $stationSlug,
            'stationName' => $station['name'],
            'stream' => $stream,
            'artist' => $artist,
            'title' => $title,
            'art' => self::song_art($current),
            'songKey' => $songKey,
            'counts' => RBSR_Core::counts($stationSlug, $songKey),
            'next' => [
                'artist' => self::clean_track_text((string) ($next['artist'] ?? '')),
                'title' => self::clean_track_text((string) ($next['title'] ?? '')),
                'art' => self::song_art($next),
            ],
        ];

        set_transient($cacheKey, $payload, 10);
        return $payload;
    }

    private static function extract_current_song(array $data): array
    {
        $candidates = [
            $data['now_playing']['song'] ?? null,
            $data['current']['song'] ?? null,
            $data['current'] ?? null,
            $data['current_song'] ?? null,
            $data['song'] ?? null,
            $data,
        ];
        foreach ($candidates as $candidate) {
            if (is_array($candidate) && (isset($candidate['artist']) || isset($candidate['title']))) {
                return $candidate;
            }
        }
        return [];
    }

    private static function extract_next_song(array $data): array
    {
        $candidates = [
            $data['playing_next']['song'] ?? null,
            $data['next']['song'] ?? null,
            $data['next'] ?? null,
            $data['next_song'] ?? null,
        ];
        foreach ($candidates as $candidate) {
            if (is_array($candidate) && (isset($candidate['artist']) || isset($candidate['title']))) {
                return $candidate;
            }
        }
        return [];
    }

    private static function song_art(array $song): string
    {
        return esc_url_raw((string) ($song['art'] ?? $song['artwork'] ?? $song['cover'] ?? ''));
    }

    private static function clean_track_text(string $value): string
    {
        $value = sanitize_text_field($value);
        return function_exists('mb_substr') ? mb_substr($value, 0, 255) : substr($value, 0, 255);
    }
}
