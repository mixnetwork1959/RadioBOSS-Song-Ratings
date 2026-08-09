<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (!rbsr_installed()) {
    header('Location: ../setup/');
    exit;
}

header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
rbsr_start_session();

if (($_GET['action'] ?? '') === 'logout') {
    rbsr_admin_logout();
    header('Location: ./');
    exit;
}

$loginError = '';
if (!rbsr_admin_logged_in() && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!rbsr_verify_csrf((string) ($_POST['csrf'] ?? ''))) {
        $loginError = 'The login form expired. Please try again.';
    } elseif (!rbsr_rate_limit('admin-login|' . rbsr_request_ip(), 10, 900)) {
        $loginError = 'Too many login attempts. Please wait before trying again.';
    } elseif (!rbsr_admin_login(trim((string) ($_POST['username'] ?? '')), (string) ($_POST['password'] ?? ''))) {
        $loginError = 'Incorrect username or password.';
    } else {
        header('Location: ./');
        exit;
    }
}

if (!rbsr_admin_logged_in()) {
    admin_login_page($loginError);
    exit;
}

$page = (string) ($_GET['page'] ?? 'dashboard');
if (!in_array($page, ['dashboard', 'settings', 'integration'], true)) {
    $page = 'dashboard';
}
$notice = '';
$error = '';

if ($page === 'settings' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!rbsr_verify_csrf((string) ($_POST['csrf'] ?? ''))) {
        $error = 'The form expired. Please try again.';
    } else {
        try {
            $config = rbsr_config();
            $appUrl = rbsr_http_url((string) ($_POST['app_url'] ?? ''));
            if ($appUrl === '') {
                throw new RuntimeException('Enter a valid application URL.');
            }
            $stations = [];
            for ($index = 1; $index <= 4; $index++) {
                $name = rbsr_clean_track((string) ($_POST["station_{$index}_name"] ?? ''));
                $slug = rbsr_clean_slug((string) ($_POST["station_{$index}_slug"] ?? ''));
                if ($name === '' && $slug === '') {
                    continue;
                }
                if ($name === '' || $slug === '') {
                    throw new RuntimeException("Station {$index} needs both a name and station ID.");
                }
                if (isset($stations[$slug])) {
                    throw new RuntimeException('Every station ID must be unique.');
                }
                $enabled = isset($_POST["station_{$index}_enabled"]);
                $api = rbsr_http_url((string) ($_POST["station_{$index}_api"] ?? ''), !$enabled);
                if ($enabled && $api === '') {
                    throw new RuntimeException("Station {$index} needs a valid Now Playing API URL.");
                }
                $language = (string) ($_POST["station_{$index}_language"] ?? 'en');
                $radius = (string) ($_POST["station_{$index}_radius"] ?? 'rounded');
                $size = (string) ($_POST["station_{$index}_size"] ?? 'normal');
                $customCss = trim((string) ($_POST["station_{$index}_custom_css"] ?? ''));
                $customCss = str_ireplace(['<style', '</style', '<?', '?>'], '', substr($customCss, 0, 5000));
                $stations[$slug] = [
                    'enabled' => $enabled,
                    'name' => $name,
                    'slug' => $slug,
                    'now_playing_url' => $api,
                    'stream_url' => rbsr_http_url((string) ($_POST["station_{$index}_stream"] ?? ''), true),
                    'logo_url' => rbsr_http_url((string) ($_POST["station_{$index}_logo"] ?? ''), true),
                    'language' => in_array($language, ['en', 'de'], true) ? $language : 'en',
                    'theme' => [
                        'accent' => rbsr_color((string) ($_POST["station_{$index}_accent"] ?? ''), '#2563eb'),
                        'background' => rbsr_color((string) ($_POST["station_{$index}_background"] ?? ''), '#111827'),
                        'text' => rbsr_color((string) ($_POST["station_{$index}_text"] ?? ''), '#eef4ff'),
                        'radius' => in_array($radius, ['rounded', 'square'], true) ? $radius : 'rounded',
                        'size' => in_array($size, ['normal', 'compact'], true) ? $size : 'normal',
                        'show_cover' => isset($_POST["station_{$index}_show_cover"]),
                        'custom_css' => $customCss,
                    ],
                ];
            }
            if (array_filter($stations, static fn (array $station): bool => !empty($station['enabled'])) === []) {
                throw new RuntimeException('Enable at least one station.');
            }
            $config['version'] = RBSR_STANDALONE_VERSION;
            $config['app_url'] = rtrim($appUrl, '/');
            $config['stations'] = $stations;
            $username = trim((string) ($_POST['admin_username'] ?? ''));
            if (!preg_match('/^[a-zA-Z0-9_.-]{3,60}$/', $username)) {
                throw new RuntimeException('Enter a valid administrator username.');
            }
            $config['admin']['username'] = $username;
            $newPassword = (string) ($_POST['admin_password'] ?? '');
            if ($newPassword !== '') {
                if (strlen($newPassword) < 10) {
                    throw new RuntimeException('The new administrator password must contain at least 10 characters.');
                }
                $config['admin']['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
            }
            rbsr_write_config($config);
            header('Location: ?page=settings&saved=1');
            exit;
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }
    }
}

if (isset($_GET['saved'])) {
    $notice = 'Settings saved successfully.';
}
if (isset($_GET['setup'])) {
    $notice = 'Setup completed successfully. Your standalone Song Ratings installation is ready.';
}

admin_header($page);
if ($notice !== '') {
    echo '<div class="notice success">' . rbsr_h($notice) . '</div>';
}
if ($error !== '') {
    echo '<div class="notice error"><strong>Settings could not be saved.</strong><br>' . rbsr_h($error) . '</div>';
}

if ($page === 'dashboard') {
    admin_dashboard();
} elseif ($page === 'settings') {
    admin_settings();
} else {
    admin_integration();
}

admin_footer();

function admin_login_page(string $error): void
{
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Administration — Song Ratings</title><link rel="stylesheet" href="../assets/admin.css"></head><body>';
    echo '<main class="login-card"><header class="brand"><div class="brand-icon">♥</div><div><strong>Song Ratings Administration</strong><span>Standalone Edition</span></div></header>';
    if ($error !== '') {
        echo '<div class="notice error">' . rbsr_h($error) . '</div>';
    }
    echo '<form class="login-form" method="post"><input type="hidden" name="csrf" value="' . rbsr_h(rbsr_csrf_token()) . '"><label><span>Username</span><input name="username" autocomplete="username" required autofocus></label><label><span>Password</span><input type="password" name="password" autocomplete="current-password" required></label><button class="button primary">Sign in</button></form></main></body></html>';
}

function admin_header(string $page): void
{
    $links = ['dashboard' => 'Ratings', 'settings' => 'Settings', 'integration' => 'Embed & Integration'];
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Administration — Song Ratings</title><link rel="stylesheet" href="../assets/admin.css"></head><body><main class="admin-shell"><div class="admin-top"><header class="brand"><div class="brand-icon">♥</div><div><strong>RadioBOSS Song Ratings</strong><span>Standalone Administration</span></div></header><a class="button" href="?action=logout">Sign out</a></div><nav class="admin-nav">';
    foreach ($links as $key => $label) {
        echo '<a href="?page=' . rbsr_h($key) . '"' . ($page === $key ? ' class="active"' : '') . '>' . rbsr_h($label) . '</a>';
    }
    echo '</nav>';
}

function admin_footer(): void
{
    echo '<footer>RadioBOSS Song Ratings Standalone ' . rbsr_h(RBSR_STANDALONE_VERSION) . ' · GPL-2.0-or-later</footer></main></body></html>';
}

function admin_dashboard(): void
{
    $stationFilter = rbsr_clean_slug((string) ($_GET['station'] ?? ''));
    $search = rbsr_clean_track((string) ($_GET['search'] ?? ''));
    $minimum = max(0, (int) ($_GET['minimum'] ?? 0));
    $sortMap = ['station' => 'station', 'artist' => 'artist', 'title' => 'title', 'dislikes' => 'dislikes', 'okay_votes' => 'okay_votes', 'love_votes' => 'love_votes', 'score' => 'score', 'last_vote' => 'last_vote'];
    $sort = (string) ($_GET['sort'] ?? 'last_vote');
    $sortSql = $sortMap[$sort] ?? 'last_vote';
    $order = strtolower((string) ($_GET['order'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
    $table = rbsr_quote_identifier(rbsr_table_name());

    try {
        $stats = rbsr_db()->query("SELECT COUNT(*) AS votes, COUNT(DISTINCT CONCAT(station, ':', song_key)) AS songs, SUM(rating = 'dislike') AS dislikes, SUM(rating = 'ok') AS okay_votes, SUM(rating = 'love') AS love_votes FROM {$table}")->fetch() ?: [];
        $where = [];
        $params = [];
        if ($stationFilter !== '') {
            $where[] = 'station = ?';
            $params[] = $stationFilter;
        }
        if ($search !== '') {
            $where[] = '(artist LIKE ? OR title LIKE ?)';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $sql = "SELECT station, song_key, MAX(artist) AS artist, MAX(title) AS title,
                    SUM(rating = 'dislike') AS dislikes, SUM(rating = 'ok') AS okay_votes,
                    SUM(rating = 'love') AS love_votes, COUNT(*) AS total_votes,
                    ROUND(((SUM(rating = 'love') - SUM(rating = 'dislike')) / COUNT(*)) * 100) AS score,
                    MAX(updated_at) AS last_vote
                FROM {$table} {$whereSql}
                GROUP BY station, song_key HAVING COUNT(*) >= ?
                ORDER BY {$sortSql} {$order} LIMIT 500";
        $params[] = $minimum;
        $statement = rbsr_db()->prepare($sql);
        $statement->execute($params);
        $rows = $statement->fetchAll();
    } catch (Throwable $exception) {
        echo '<div class="notice error">The ratings database could not be read.</div>';
        return;
    }
    $stations = rbsr_enabled_stations();
    echo '<section class="admin-card"><h2>Song Ratings</h2><p>Listener feedback across all configured stations.</p><div class="stat-grid">';
    admin_stat('🗳️', (int) ($stats['votes'] ?? 0), 'Total votes');
    admin_stat('🎵', (int) ($stats['songs'] ?? 0), 'Rated songs');
    admin_stat('❤️', (int) ($stats['love_votes'] ?? 0), 'Love it');
    admin_stat('😐', (int) ($stats['okay_votes'] ?? 0), "It's okay");
    admin_stat('👎', (int) ($stats['dislikes'] ?? 0), 'Dislike');
    echo '</div><form class="filters" method="get"><input type="hidden" name="page" value="dashboard"><select name="station"><option value="">All stations</option>';
    foreach ($stations as $slug => $station) {
        echo '<option value="' . rbsr_h((string) $slug) . '"' . ($stationFilter === $slug ? ' selected' : '') . '>' . rbsr_h((string) ($station['name'] ?? $slug)) . '</option>';
    }
    echo '</select><select name="minimum">';
    foreach ([0, 5, 10, 20, 50] as $value) {
        echo '<option value="' . $value . '"' . ($minimum === $value ? ' selected' : '') . '>' . ($value === 0 ? 'Any number of votes' : 'At least ' . $value . ' votes') . '</option>';
    }
    echo '</select><input type="search" name="search" value="' . rbsr_h($search) . '" placeholder="Search artist or title"><button class="button primary">Filter</button><a class="button" href="?page=dashboard">Reset</a></form>';
    echo '<p><strong>' . count($rows) . '</strong> rated songs found (maximum 500 displayed).</p><div class="table-wrap"><table><thead><tr><th>Station</th><th>Artist</th><th>Title</th><th class="number">👎</th><th class="number">😐</th><th class="number">❤️</th><th class="number">Score</th><th>Status</th><th>Last vote</th></tr></thead><tbody>';
    if ($rows === []) {
        echo '<tr><td colspan="9">No ratings match the selected filters.</td></tr>';
    }
    foreach ($rows as $row) {
        $status = rbsr_rating_status((int) $row['dislikes'], (int) $row['okay_votes'], (int) $row['love_votes'], (int) $row['total_votes']);
        $stationName = (string) ($stations[$row['station']]['name'] ?? ucwords(str_replace(['-', '_'], ' ', (string) $row['station'])));
        echo '<tr><td>' . rbsr_h($stationName) . '</td><td>' . rbsr_h((string) $row['artist']) . '</td><td>' . rbsr_h((string) $row['title']) . '</td><td class="number">' . (int) $row['dislikes'] . '</td><td class="number">' . (int) $row['okay_votes'] . '</td><td class="number">' . (int) $row['love_votes'] . '</td><td class="number">' . ((int) $row['score'] > 0 ? '+' : '') . (int) $row['score'] . '</td><td><span class="status status-' . rbsr_h($status['key']) . '">' . rbsr_h($status['icon'] . ' ' . $status['label']) . '</span></td><td>' . rbsr_h((string) $row['last_vote']) . ' UTC</td></tr>';
    }
    echo '</tbody></table></div></section>';
}

function admin_stat(string $icon, int $value, string $label): void
{
    echo '<div class="stat"><span>' . rbsr_h($icon) . '</span><strong>' . number_format($value) . '</strong><small>' . rbsr_h($label) . '</small></div>';
}

function admin_settings(): void
{
    $config = rbsr_config();
    $stations = array_values((array) ($config['stations'] ?? []));
    echo '<section class="admin-card"><h2>Settings</h2><p>Up to four stations can share the same installation and database table. Keep a station ID unchanged after ratings have been collected.</p><p><strong>Active ratings table:</strong> <code>' . rbsr_h(rbsr_table_name((array) ($config['database'] ?? []))) . '</code></p><form method="post"><input type="hidden" name="csrf" value="' . rbsr_h(rbsr_csrf_token()) . '"><div class="form-grid">';
    admin_field('Application URL', 'app_url', (string) ($config['app_url'] ?? rbsr_detect_app_url()), 'url', 'Public URL of this installation.');
    admin_field('Administrator username', 'admin_username', (string) ($config['admin']['username'] ?? 'admin'));
    admin_field('New administrator password', 'admin_password', '', 'password', 'Leave blank to keep the current password.');
    echo '</div>';
    for ($index = 1; $index <= 4; $index++) {
        $station = (array) ($stations[$index - 1] ?? []);
        $theme = (array) ($station['theme'] ?? []);
        echo '<div class="station-settings"><h3>Station ' . $index . '</h3><label class="checkbox"><input type="checkbox" name="station_' . $index . '_enabled" value="1"' . (!empty($station['enabled']) ? ' checked' : '') . '> Enable this station</label><div class="form-grid">';
        admin_field('Station name', "station_{$index}_name", (string) ($station['name'] ?? ''));
        admin_field('Station ID', "station_{$index}_slug", (string) ($station['slug'] ?? ''), 'text', 'Stable lowercase identifier.');
        admin_field('Now Playing API', "station_{$index}_api", (string) ($station['now_playing_url'] ?? ''), 'url');
        admin_field('Direct stream URL', "station_{$index}_stream", (string) ($station['stream_url'] ?? ''), 'url', 'Needed only for the included player.');
        admin_field('Logo URL', "station_{$index}_logo", (string) ($station['logo_url'] ?? ''), 'url');
        admin_field('Accent color', "station_{$index}_accent", (string) ($theme['accent'] ?? '#2563eb'), 'color');
        admin_field('Background color', "station_{$index}_background", (string) ($theme['background'] ?? '#111827'), 'color');
        admin_field('Text color', "station_{$index}_text", (string) ($theme['text'] ?? '#eef4ff'), 'color');
        echo '<label><span>Language</span><select name="station_' . $index . '_language"><option value="en"' . (($station['language'] ?? 'en') === 'en' ? ' selected' : '') . '>English</option><option value="de"' . (($station['language'] ?? '') === 'de' ? ' selected' : '') . '>Deutsch</option></select></label>';
        echo '<label><span>Corner style</span><select name="station_' . $index . '_radius"><option value="rounded"' . (($theme['radius'] ?? 'rounded') === 'rounded' ? ' selected' : '') . '>Rounded</option><option value="square"' . (($theme['radius'] ?? '') === 'square' ? ' selected' : '') . '>Square</option></select></label>';
        echo '<label><span>Widget size</span><select name="station_' . $index . '_size"><option value="normal"' . (($theme['size'] ?? 'normal') === 'normal' ? ' selected' : '') . '>Normal</option><option value="compact"' . (($theme['size'] ?? '') === 'compact' ? ' selected' : '') . '>Compact</option></select></label>';
        echo '<label class="checkbox"><input type="checkbox" name="station_' . $index . '_show_cover" value="1"' . (!array_key_exists('show_cover', $theme) || !empty($theme['show_cover']) ? ' checked' : '') . '> <span>Show cover or station logo</span></label>';
        echo '<label class="wide"><span>Custom CSS</span><textarea name="station_' . $index . '_custom_css" rows="3" maxlength="5000">' . rbsr_h((string) ($theme['custom_css'] ?? '')) . '</textarea></label>';
        if (!empty($station['slug']) && !empty($station['enabled'])) {
            echo '<p class="wide"><a class="button" target="_blank" rel="noopener" href="' . rbsr_h(rbsr_base_url() . '/widget.php?station=' . rawurlencode((string) $station['slug']) . '&mode=player') . '">Open player preview ↗</a></p>';
        }
        echo '</div></div>';
    }
    echo '<div class="form-actions"><span></span><button class="button primary">Save settings</button></div></form></section>';
}

function admin_field(string $label, string $name, string $value, string $type = 'text', string $hint = ''): void
{
    echo '<label><span>' . rbsr_h($label) . '</span><input type="' . rbsr_h($type) . '" name="' . rbsr_h($name) . '" value="' . rbsr_h($value) . '">';
    if ($hint !== '') {
        echo '<small>' . rbsr_h($hint) . '</small>';
    }
    echo '</label>';
}

function admin_integration(): void
{
    $base = rbsr_base_url();
    echo '<section class="admin-card"><h2>Embed & Integration</h2><p>The widget gets artist and title directly from the configured Now Playing API. It does not need to replace or communicate with an existing player.</p>';
    foreach (rbsr_enabled_stations() as $slug => $station) {
        $name = (string) ($station['name'] ?? $slug);
        $ratingEmbed = '<div class="rbsr-embed" data-station="' . $slug . '" data-mode="ratings"></div>' . "\n" . '<script src="' . $base . '/embed.js" defer></script>';
        $playerEmbed = '<div class="rbsr-embed" data-station="' . $slug . '" data-mode="player"></div>' . "\n" . '<script src="' . $base . '/embed.js" defer></script>';
        $iframe = '<iframe src="' . $base . '/widget.php?station=' . rawurlencode((string) $slug) . '&mode=ratings" title="Song rating" style="width:100%;max-width:680px;height:330px;border:0" loading="lazy"></iframe>';
        $playerIframe = '<iframe src="' . $base . '/widget.php?station=' . rawurlencode((string) $slug) . '&mode=player" title="Radio player and song rating" style="width:100%;max-width:680px;height:390px;border:0" loading="lazy" allow="autoplay"></iframe>';
        echo '<div class="station-settings"><h3>' . rbsr_h($name) . ' <code>' . rbsr_h((string) $slug) . '</code></h3>';
        echo '<div class="integration-option"><h3>1. Recommended: directly under an existing player</h3><p>Paste this code immediately below the current web player. The player remains unchanged.</p><div class="code-block">' . rbsr_h($ratingEmbed) . '</div></div>';
        echo '<div class="integration-option"><h3>2. Separate rating page or second tab</h3><p>Link a “Rate the current song” button to this URL. The stream may continue playing in the original tab.</p><div class="code-block">' . rbsr_h($base . '/?station=' . rawurlencode((string) $slug)) . '</div></div>';
        echo '<div class="integration-option"><h3>3. Included neutral player with ratings</h3><p>This version includes play/pause, volume, cover, current title, and rating buttons.</p><div class="code-block">' . rbsr_h($playerEmbed) . '</div></div>';
        echo '<details><summary>Plain iFrame alternatives</summary><p>Rating-only widget:</p><div class="code-block">' . rbsr_h($iframe) . '</div><p>Included player with ratings:</p><div class="code-block">' . rbsr_h($playerIframe) . '</div></details></div>';
    }
    echo '<p><strong>Important:</strong> When several widgets are added to the same page, include <code>embed.js</code> only once at the end of the page.</p></section>';
}
