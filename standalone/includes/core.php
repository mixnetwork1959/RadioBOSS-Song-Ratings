<?php

declare(strict_types=1);

function rbsr_config(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    if (!is_file(RBSR_CONFIG_FILE)) {
        $config = [];
        return $config;
    }

    $loaded = require RBSR_CONFIG_FILE;
    $config = is_array($loaded) ? $loaded : [];
    return $config;
}

function rbsr_installed(): bool
{
    $config = rbsr_config();
    return isset($config['database'], $config['admin'], $config['stations']);
}

function rbsr_base_url(): string
{
    $config = rbsr_config();
    if (!empty($config['app_url'])) {
        return rtrim((string) $config['app_url'], '/');
    }

    $https = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
    $scheme = $https ? 'https' : 'http';
    $host = preg_replace('/[^a-zA-Z0-9.\-:\[\]]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost')) ?: 'localhost';
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/'));
    $root = preg_replace('#/(?:setup|admin|api)/[^/]*$#', '', $script) ?: dirname($script);
    return rtrim($scheme . '://' . $host . '/' . trim($root, '/'), '/');
}

function rbsr_detect_app_url(): string
{
    return rbsr_base_url();
}

function rbsr_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function rbsr_json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function rbsr_api_cors(): void
{
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Max-Age: 600');
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

function rbsr_clean_slug(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9_-]+/', '-', $value) ?? '';
    return trim(substr($value, 0, 80), '-_');
}

function rbsr_clean_track(string $value): string
{
    $value = trim(strip_tags($value));
    $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '';
    return function_exists('mb_substr') ? mb_substr($value, 0, 255) : substr($value, 0, 255);
}

function rbsr_color(string $value, string $fallback): string
{
    return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtolower($value) : $fallback;
}

function rbsr_http_url(string $value, bool $allowEmpty = false): string
{
    $value = trim($value);
    if ($value === '' && $allowEmpty) {
        return '';
    }
    if (filter_var($value, FILTER_VALIDATE_URL) === false) {
        return '';
    }
    $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true) ? $value : '';
}

function rbsr_table_prefix(string $prefix): string
{
    $prefix = strtolower(trim($prefix));
    return preg_match('/^[a-z][a-z0-9_]{0,39}$/', $prefix) ? $prefix : '';
}

function rbsr_table_name(?array $database = null): string
{
    $database = $database ?? (array) (rbsr_config()['database'] ?? []);
    $tableName = trim((string) ($database['table_name'] ?? ''));
    if ($tableName !== '') {
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,63}$/', $tableName)) {
            throw new RuntimeException('Invalid database table name.');
        }
        return $tableName;
    }
    $prefix = rbsr_table_prefix((string) ($database['table_prefix'] ?? 'rbsr_'));
    if ($prefix === '') {
        throw new RuntimeException('Invalid database table prefix.');
    }
    return $prefix . 'song_votes';
}

function rbsr_table_has_rating_schema(PDO $pdo, string $tableName): bool
{
    if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,63}$/', $tableName)) {
        return false;
    }
    try {
        $columns = $pdo->query('SHOW COLUMNS FROM ' . rbsr_quote_identifier($tableName))->fetchAll();
    } catch (Throwable $exception) {
        return false;
    }
    $names = array_map(static fn (array $column): string => (string) ($column['Field'] ?? ''), $columns);
    $required = ['station', 'song_key', 'artist', 'title', 'visitor_hash', 'rating', 'created_at', 'updated_at'];
    return array_diff($required, $names) === [];
}

function rbsr_compatible_rating_tables(PDO $pdo): array
{
    $tables = [];
    $names = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($names as $name) {
        $name = (string) $name;
        if (!rbsr_table_has_rating_schema($pdo, $name)) {
            continue;
        }
        try {
            $table = rbsr_quote_identifier($name);
            $stats = $pdo->query("SELECT COUNT(*) AS votes, COUNT(DISTINCT CONCAT(station, ':', song_key)) AS songs FROM {$table}")->fetch() ?: [];
        } catch (Throwable $exception) {
            $stats = [];
        }
        $tables[] = [
            'name' => $name,
            'votes' => (int) ($stats['votes'] ?? 0),
            'songs' => (int) ($stats['songs'] ?? 0),
        ];
    }
    usort($tables, static function (array $left, array $right): int {
        return ($right['votes'] <=> $left['votes']) ?: strcmp($left['name'], $right['name']);
    });
    return $tables;
}

function rbsr_quote_identifier(string $identifier): string
{
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $identifier)) {
        throw new RuntimeException('Invalid SQL identifier.');
    }
    return '`' . $identifier . '`';
}

function rbsr_db_from_settings(array $database): PDO
{
    $host = trim((string) ($database['host'] ?? ''));
    $port = (int) ($database['port'] ?? 3306);
    $name = trim((string) ($database['name'] ?? ''));
    $user = (string) ($database['user'] ?? '');
    $password = (string) ($database['password'] ?? '');

    if ($host === '' || $name === '' || $user === '' || $port < 1 || $port > 65535) {
        throw new RuntimeException('The database settings are incomplete.');
    }

    $dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $name . ';charset=utf8mb4';
    return new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function rbsr_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = rbsr_config();
    if (!isset($config['database']) || !is_array($config['database'])) {
        throw new RuntimeException('Song Ratings has not been installed yet.');
    }
    $pdo = rbsr_db_from_settings($config['database']);
    return $pdo;
}

function rbsr_create_schema(PDO $pdo, array $database): void
{
    $table = rbsr_quote_identifier(rbsr_table_name($database));
    $sql = "CREATE TABLE IF NOT EXISTS {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        station VARCHAR(80) NOT NULL,
        song_key CHAR(64) NOT NULL,
        artist VARCHAR(255) NOT NULL,
        title VARCHAR(255) NOT NULL,
        visitor_hash CHAR(64) NOT NULL,
        rating VARCHAR(10) NOT NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY song_visitor (station, song_key, visitor_hash),
        KEY song_counts (station, song_key, rating),
        KEY updated_at (updated_at)
    ) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    $pdo->exec($sql);
}

function rbsr_ensure_rating_table(PDO $pdo, array $database): void
{
    $tableName = rbsr_table_name($database);
    if (rbsr_table_has_rating_schema($pdo, $tableName)) {
        return;
    }
    rbsr_create_schema($pdo, $database);
    if (!rbsr_table_has_rating_schema($pdo, $tableName)) {
        throw new RuntimeException('The selected table exists but does not have the required Song Ratings columns.');
    }
}

function rbsr_write_config(array $config): void
{
    $directory = dirname(RBSR_CONFIG_FILE);
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('The config directory could not be created.');
    }
    if (!is_writable($directory)) {
        throw new RuntimeException('The config directory is not writable.');
    }

    $contents = "<?php\n\ndeclare(strict_types=1);\n\n// Generated by the RadioBOSS Song Ratings setup wizard.\nreturn "
        . var_export($config, true) . ";\n";
    $temporary = RBSR_CONFIG_FILE . '.tmp';
    if (file_put_contents($temporary, $contents, LOCK_EX) === false) {
        throw new RuntimeException('The configuration file could not be written.');
    }
    @chmod($temporary, 0640);
    if (!rename($temporary, RBSR_CONFIG_FILE)) {
        @unlink($temporary);
        throw new RuntimeException('The configuration file could not be activated.');
    }
}

function rbsr_station(string $slug): ?array
{
    $slug = rbsr_clean_slug($slug);
    $stations = (array) (rbsr_config()['stations'] ?? []);
    $station = $stations[$slug] ?? null;
    if (!is_array($station) || empty($station['enabled'])) {
        return null;
    }
    return $station;
}

function rbsr_enabled_stations(): array
{
    return array_filter((array) (rbsr_config()['stations'] ?? []), static function ($station): bool {
        return is_array($station) && !empty($station['enabled']);
    });
}

function rbsr_song_key(string $station, string $artist, string $title): string
{
    return hash('sha256', strtolower(trim($station . '|' . $artist . '|' . $title)));
}

function rbsr_counts(string $station, string $songKey): array
{
    $table = rbsr_quote_identifier(rbsr_table_name());
    $statement = rbsr_db()->prepare("SELECT rating, COUNT(*) AS total FROM {$table} WHERE station = ? AND song_key = ? GROUP BY rating");
    $statement->execute([$station, $songKey]);
    $counts = ['dislike' => 0, 'ok' => 0, 'love' => 0];
    foreach ($statement->fetchAll() as $row) {
        $rating = (string) ($row['rating'] ?? '');
        if (array_key_exists($rating, $counts)) {
            $counts[$rating] = (int) $row['total'];
        }
    }
    return $counts;
}

function rbsr_extract_song(array $data, bool $next = false): array
{
    $candidates = $next ? [
        $data['playing_next']['song'] ?? null,
        $data['next']['song'] ?? null,
        $data['next'] ?? null,
        $data['next_song'] ?? null,
    ] : [
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

function rbsr_song_art(array $song): string
{
    return rbsr_http_url((string) ($song['art'] ?? $song['artwork'] ?? $song['cover'] ?? ''), true);
}

function rbsr_fetch_json_url(string $url, int $timeout = 10): array
{
    if (rbsr_http_url($url) === '') {
        throw new RuntimeException('The Now Playing URL is invalid.');
    }

    $body = '';
    $status = 0;
    if (function_exists('curl_init')) {
        $curl = curl_init($url);
        if ($curl === false) {
            throw new RuntimeException('The metadata connection could not be started.');
        }
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_USERAGENT => 'RadioBOSS-Song-Ratings/' . RBSR_STANDALONE_VERSION,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $result = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        if (!is_string($result)) {
            throw new RuntimeException('The metadata request failed: ' . ($error !== '' ? $error : 'unknown error'));
        }
        $body = $result;
    } elseif ((bool) ini_get('allow_url_fopen')) {
        $context = stream_context_create(['http' => [
            'method' => 'GET',
            'timeout' => $timeout,
            'follow_location' => 1,
            'max_redirects' => 3,
            'header' => "Accept: application/json\r\nUser-Agent: RadioBOSS-Song-Ratings/" . RBSR_STANDALONE_VERSION . "\r\n",
            'ignore_errors' => true,
        ]]);
        $result = @file_get_contents($url, false, $context);
        if (!is_string($result)) {
            throw new RuntimeException('The metadata request failed. Enable cURL or allow_url_fopen.');
        }
        $body = $result;
        $headers = $http_response_header ?? [];
        if (isset($headers[0]) && preg_match('/\s(\d{3})\s/', $headers[0], $match)) {
            $status = (int) $match[1];
        }
    } else {
        throw new RuntimeException('PHP cURL or allow_url_fopen is required for the Now Playing API.');
    }

    if ($status !== 200) {
        throw new RuntimeException('The metadata URL returned HTTP ' . $status . '.');
    }
    if (strlen($body) > 2097152) {
        throw new RuntimeException('The metadata response is unexpectedly large.');
    }

    try {
        $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $error) {
        throw new RuntimeException('The metadata URL did not return valid JSON.');
    }
    if (!is_array($data)) {
        throw new RuntimeException('The metadata response is not a JSON object.');
    }
    return $data;
}

function rbsr_test_now_playing(string $url): array
{
    $data = rbsr_fetch_json_url($url);
    $song = rbsr_extract_song($data);
    $artist = rbsr_clean_track((string) ($song['artist'] ?? ''));
    $title = rbsr_clean_track((string) ($song['title'] ?? ''));
    if ($artist === '' || $title === '') {
        throw new RuntimeException('The JSON was reachable, but no artist and title were found.');
    }
    return ['artist' => $artist, 'title' => $title];
}

function rbsr_now_playing(string $stationSlug): array
{
    $station = rbsr_station($stationSlug);
    if ($station === null || empty($station['now_playing_url'])) {
        throw new RuntimeException('Unknown or unconfigured station.');
    }

    $cacheFile = RBSR_STORAGE . '/cache/now-playing-' . hash('sha256', $stationSlug) . '.json';
    if (is_file($cacheFile) && time() - (int) filemtime($cacheFile) <= 10) {
        $cached = json_decode((string) file_get_contents($cacheFile), true);
        if (is_array($cached)) {
            return $cached;
        }
    }

    $data = rbsr_fetch_json_url((string) $station['now_playing_url']);
    $current = rbsr_extract_song($data);
    $next = rbsr_extract_song($data, true);
    $artist = rbsr_clean_track((string) ($current['artist'] ?? ''));
    $title = rbsr_clean_track((string) ($current['title'] ?? ''));
    if ($artist === '' || $title === '') {
        throw new RuntimeException('The metadata source did not provide both artist and title.');
    }

    $songKey = rbsr_song_key($stationSlug, $artist, $title);
    $stream = rbsr_http_url((string) ($station['stream_url'] ?? ''), true);
    if ($stream === '') {
        $stream = rbsr_http_url((string) ($data['station']['listen_url'] ?? $data['listen_url'] ?? $data['stream'] ?? ''), true);
    }
    $payload = [
        'station' => $stationSlug,
        'stationName' => (string) ($station['name'] ?? $stationSlug),
        'stream' => $stream,
        'artist' => $artist,
        'title' => $title,
        'art' => rbsr_song_art($current),
        'songKey' => $songKey,
        'counts' => rbsr_counts($stationSlug, $songKey),
        'next' => [
            'artist' => rbsr_clean_track((string) ($next['artist'] ?? '')),
            'title' => rbsr_clean_track((string) ($next['title'] ?? '')),
            'art' => rbsr_song_art($next),
        ],
    ];

    if (is_dir(dirname($cacheFile)) && is_writable(dirname($cacheFile))) {
        @file_put_contents($cacheFile, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
    }
    return $payload;
}

function rbsr_request_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

function rbsr_secret(): string
{
    $secret = (string) (rbsr_config()['secret'] ?? '');
    return $secret !== '' ? $secret : hash('sha256', RBSR_ROOT . '|pre-install');
}

function rbsr_rate_limit(string $key, int $limit, int $windowSeconds): bool
{
    $directory = RBSR_STORAGE . '/rate-limits';
    if (!is_dir($directory)) {
        @mkdir($directory, 0750, true);
    }
    if (!is_dir($directory) || !is_writable($directory)) {
        return true;
    }

    try {
        if (random_int(1, 200) === 1) {
            foreach (glob($directory . '/*.json') ?: [] as $oldFile) {
                if (is_file($oldFile) && (int) filemtime($oldFile) < time() - 86400) {
                    @unlink($oldFile);
                }
            }
        }
    } catch (Throwable $exception) {
        // Cleanup is optional and must never block a legitimate request.
    }

    $file = $directory . '/' . hash_hmac('sha256', $key, rbsr_secret()) . '.json';
    $handle = @fopen($file, 'c+');
    if ($handle === false) {
        return true;
    }
    try {
        if (!flock($handle, LOCK_EX)) {
            return true;
        }
        $contents = stream_get_contents($handle);
        $timestamps = json_decode(is_string($contents) ? $contents : '[]', true);
        $timestamps = is_array($timestamps) ? $timestamps : [];
        $cutoff = time() - $windowSeconds;
        $timestamps = array_values(array_filter($timestamps, static fn ($time): bool => (int) $time > $cutoff));
        $allowed = count($timestamps) < $limit;
        if ($allowed) {
            $timestamps[] = time();
        }
        rewind($handle);
        ftruncate($handle, 0);
        fwrite($handle, json_encode($timestamps));
        fflush($handle);
        flock($handle, LOCK_UN);
        return $allowed;
    } finally {
        fclose($handle);
    }
}

function rbsr_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $secure = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
    session_name('rbsr_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function rbsr_csrf_token(): string
{
    rbsr_start_session();
    if (empty($_SESSION['rbsr_csrf'])) {
        $_SESSION['rbsr_csrf'] = bin2hex(random_bytes(24));
    }
    return (string) $_SESSION['rbsr_csrf'];
}

function rbsr_verify_csrf(string $token): bool
{
    rbsr_start_session();
    return isset($_SESSION['rbsr_csrf']) && hash_equals((string) $_SESSION['rbsr_csrf'], $token);
}

function rbsr_admin_logged_in(): bool
{
    rbsr_start_session();
    return !empty($_SESSION['rbsr_admin']);
}

function rbsr_admin_login(string $username, string $password): bool
{
    $config = rbsr_config();
    $admin = (array) ($config['admin'] ?? []);
    $validUser = isset($admin['username']) && hash_equals((string) $admin['username'], $username);
    $validPassword = isset($admin['password_hash']) && password_verify($password, (string) $admin['password_hash']);
    if (!$validUser || !$validPassword) {
        return false;
    }
    rbsr_start_session();
    session_regenerate_id(true);
    $_SESSION['rbsr_admin'] = true;
    return true;
}

function rbsr_admin_logout(): void
{
    rbsr_start_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
    }
    session_destroy();
}

function rbsr_labels(string $language): array
{
    if ($language === 'de') {
        return [
            'nowPlaying' => 'Läuft gerade', 'question' => 'Wie gefällt dir dieser Song?',
            'loading' => 'Aktueller Titel wird geladen …', 'offline' => 'Titelinformationen sind derzeit nicht verfügbar.',
            'unknown' => 'Unbekannter Titel', 'thanks' => 'Danke! Deine Bewertung wurde gespeichert.',
            'voteError' => 'Deine Bewertung konnte nicht gespeichert werden.', 'play' => 'Radio abspielen',
            'pause' => 'Radio pausieren', 'mute' => 'Stummschalten', 'unmute' => 'Ton einschalten',
            'volume' => 'Lautstärke', 'dislike' => 'Gefällt mir nicht', 'okay' => 'Ist okay',
            'love' => 'Gefällt mir sehr', 'live' => 'LIVE',
        ];
    }
    return [
        'nowPlaying' => 'Now Playing', 'question' => 'How do you like this song?',
        'loading' => 'Loading current song …', 'offline' => 'Track information is currently unavailable.',
        'unknown' => 'Unknown song', 'thanks' => 'Thank you! Your rating has been counted.',
        'voteError' => 'Your rating could not be saved.', 'play' => 'Play live radio',
        'pause' => 'Pause live radio', 'mute' => 'Mute', 'unmute' => 'Turn sound on',
        'volume' => 'Volume', 'dislike' => 'Dislike', 'okay' => "It's okay",
        'love' => 'Love it', 'live' => 'LIVE',
    ];
}

function rbsr_rating_status(int $dislikes, int $okay, int $loves, int $total): array
{
    if ($total < 5) {
        return ['key' => 'insufficient', 'label' => 'Not enough votes', 'icon' => '⚪'];
    }
    $positive = ($loves / $total) * 100;
    $negative = ($dislikes / $total) * 100;
    $score = (($loves - $dislikes) / $total) * 100;
    if ($negative >= 60 && $score <= -30) {
        return ['key' => 'review', 'label' => 'Review', 'icon' => '🔴'];
    }
    if ($positive >= 70 && $score >= 40) {
        return ['key' => 'popular', 'label' => 'Popular', 'icon' => '🟢'];
    }
    return ['key' => 'observe', 'label' => 'Observe', 'icon' => '🟡'];
}
