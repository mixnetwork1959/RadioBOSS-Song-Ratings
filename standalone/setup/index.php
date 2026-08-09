<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
rbsr_start_session();

if (rbsr_installed()) {
    http_response_code(409);
    setup_header('Already installed', 5);
    echo '<div class="notice success"><strong>Song Ratings is already installed.</strong><br>The setup wizard is locked to protect your configuration.</div>';
    echo '<p><a class="button primary" href="' . rbsr_h(rbsr_base_url() . '/admin/') . '">Open administration</a></p>';
    setup_footer();
    exit;
}

$step = max(1, min(5, (int) ($_GET['step'] ?? 1)));
$error = '';
$success = '';

if ($step === 2 && isset($_GET['reset'])) {
    unset($_SESSION['rbsr_setup']['database'], $_SESSION['rbsr_setup']['compatible_tables']);
    setup_redirect(2);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!rbsr_verify_csrf((string) ($_POST['csrf'] ?? ''))) {
        $error = 'The form expired. Please try again.';
    } else {
        try {
            if ($step === 1) {
                foreach (setup_requirements() as $requirement) {
                    if (!$requirement['ok'] && $requirement['required']) {
                        throw new RuntimeException('Please resolve the failed required checks before continuing.');
                    }
                }
                setup_redirect(2);
            }

            if ($step === 2) {
                $databaseAction = (string) ($_POST['database_action'] ?? 'connect');
                if ($databaseAction === 'connect') {
                    $database = [
                        'host' => trim((string) ($_POST['db_host'] ?? '')),
                        'port' => (int) ($_POST['db_port'] ?? 3306),
                        'name' => trim((string) ($_POST['db_name'] ?? '')),
                        'user' => trim((string) ($_POST['db_user'] ?? '')),
                        'password' => (string) ($_POST['db_password'] ?? ''),
                        'table_prefix' => 'rbsr_',
                    ];
                    if ($database['host'] === '' || $database['name'] === '' || $database['user'] === '') {
                        throw new RuntimeException('Enter the database server, name, and user.');
                    }
                    $pdo = rbsr_db_from_settings($database);
                    $_SESSION['rbsr_setup']['database'] = $database;
                    $_SESSION['rbsr_setup']['compatible_tables'] = rbsr_compatible_rating_tables($pdo);
                    setup_redirect(2, ['connected' => '1']);
                }

                if ($databaseAction === 'choose') {
                    $database = (array) ($_SESSION['rbsr_setup']['database'] ?? []);
                    if ($database === []) {
                        setup_redirect(2);
                    }
                    $pdo = rbsr_db_from_settings($database);
                    $choice = (string) ($_POST['table_choice'] ?? '');
                    if ($choice === 'new') {
                        $prefix = rbsr_table_prefix((string) ($_POST['table_prefix'] ?? 'rbsr_'));
                        if ($prefix === '') {
                            throw new RuntimeException('Enter a valid table prefix for the new ratings table.');
                        }
                        $database['table_prefix'] = $prefix;
                        $database['table_name'] = $prefix . 'song_votes';
                        rbsr_ensure_rating_table($pdo, $database);
                    } elseif (str_starts_with($choice, 'existing:')) {
                        $tableName = substr($choice, strlen('existing:'));
                        $knownNames = array_column((array) ($_SESSION['rbsr_setup']['compatible_tables'] ?? []), 'name');
                        if (!in_array($tableName, $knownNames, true) || !rbsr_table_has_rating_schema($pdo, $tableName)) {
                            throw new RuntimeException('The selected existing table is no longer available or compatible.');
                        }
                        $database['table_name'] = $tableName;
                        if (str_ends_with($tableName, 'song_votes')) {
                            $database['table_prefix'] = substr($tableName, 0, -strlen('song_votes'));
                        }
                    } else {
                        throw new RuntimeException('Choose whether to use an existing ratings table or create a new one.');
                    }
                    $_SESSION['rbsr_setup']['database'] = $database;
                    setup_redirect(3, ['database' => 'ok']);
                }
            }

            if ($step === 3) {
                $mode = (string) ($_POST['mode'] ?? 'below-player');
                if (!in_array($mode, ['below-player', 'separate-page', 'player'], true)) {
                    $mode = 'below-player';
                }
                $name = rbsr_clean_track((string) ($_POST['station_name'] ?? ''));
                $slug = rbsr_clean_slug((string) ($_POST['station_slug'] ?? ''));
                $api = rbsr_http_url((string) ($_POST['now_playing_url'] ?? ''));
                $stream = rbsr_http_url((string) ($_POST['stream_url'] ?? ''), true);
                $appUrl = rbsr_http_url((string) ($_POST['app_url'] ?? ''));
                if ($name === '' || $slug === '' || $api === '' || $appUrl === '') {
                    throw new RuntimeException('Application URL, station name, station ID, and Now Playing API are required.');
                }
                if ($mode === 'player' && $stream === '') {
                    throw new RuntimeException('A direct stream URL is required for the included player.');
                }
                $track = rbsr_test_now_playing($api);
                $_SESSION['rbsr_setup']['app_url'] = rtrim($appUrl, '/');
                $_SESSION['rbsr_setup']['mode'] = $mode;
                $_SESSION['rbsr_setup']['station'] = [
                    'enabled' => true,
                    'name' => $name,
                    'slug' => $slug,
                    'now_playing_url' => $api,
                    'stream_url' => $stream,
                ];
                $_SESSION['rbsr_setup']['tested_track'] = $track;
                setup_redirect(4, ['metadata' => 'ok']);
            }

            if ($step === 4) {
                $station = (array) ($_SESSION['rbsr_setup']['station'] ?? []);
                if ($station === []) {
                    setup_redirect(3);
                }
                $username = trim((string) ($_POST['admin_username'] ?? ''));
                $password = (string) ($_POST['admin_password'] ?? '');
                if (!preg_match('/^[a-zA-Z0-9_.-]{3,60}$/', $username)) {
                    throw new RuntimeException('The administrator username must contain 3–60 letters, numbers, dots, dashes, or underscores.');
                }
                if (strlen($password) < 10) {
                    throw new RuntimeException('Use an administrator password with at least 10 characters.');
                }
                $language = (string) ($_POST['language'] ?? 'en');
                $radius = (string) ($_POST['radius'] ?? 'rounded');
                $size = (string) ($_POST['size'] ?? 'normal');
                $customCss = trim((string) ($_POST['custom_css'] ?? ''));
                $customCss = str_ireplace(['<style', '</style', '<?', '?>'], '', substr($customCss, 0, 5000));
                $station['logo_url'] = rbsr_http_url((string) ($_POST['logo_url'] ?? ''), true);
                $station['language'] = in_array($language, ['en', 'de'], true) ? $language : 'en';
                $station['theme'] = [
                    'accent' => rbsr_color((string) ($_POST['accent'] ?? ''), '#2563eb'),
                    'background' => rbsr_color((string) ($_POST['background'] ?? ''), '#111827'),
                    'text' => rbsr_color((string) ($_POST['text'] ?? ''), '#eef4ff'),
                    'radius' => in_array($radius, ['rounded', 'square'], true) ? $radius : 'rounded',
                    'size' => in_array($size, ['normal', 'compact'], true) ? $size : 'normal',
                    'show_cover' => isset($_POST['show_cover']),
                    'custom_css' => $customCss,
                ];
                $_SESSION['rbsr_setup']['station'] = $station;
                $_SESSION['rbsr_setup']['admin'] = [
                    'username' => $username,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ];
                setup_redirect(5);
            }

            if ($step === 5) {
                $setup = (array) ($_SESSION['rbsr_setup'] ?? []);
                if (!isset($setup['database'], $setup['station'], $setup['admin'], $setup['app_url'])) {
                    throw new RuntimeException('Setup data is incomplete. Please restart the wizard.');
                }
                $pdo = rbsr_db_from_settings((array) $setup['database']);
                rbsr_ensure_rating_table($pdo, (array) $setup['database']);
                $station = (array) $setup['station'];
                $slug = (string) $station['slug'];
                $config = [
                    'version' => RBSR_STANDALONE_VERSION,
                    'schema_version' => 1,
                    'app_url' => rtrim((string) $setup['app_url'], '/'),
                    'secret' => bin2hex(random_bytes(32)),
                    'database' => (array) $setup['database'],
                    'admin' => (array) $setup['admin'],
                    'setup_mode' => (string) ($setup['mode'] ?? 'below-player'),
                    'stations' => [$slug => $station],
                ];
                rbsr_write_config($config);
                if (file_put_contents(RBSR_STORAGE . '/setup.lock', gmdate('c') . "\n", LOCK_EX) === false) {
                    throw new RuntimeException('The setup lock could not be written. Check the storage directory permissions.');
                }
                unset($_SESSION['rbsr_setup']);
                session_regenerate_id(true);
                $_SESSION['rbsr_admin'] = true;
                header('Location: ' . $config['app_url'] . '/admin/?page=integration&setup=complete');
                exit;
            }
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }
    }
}

$setup = (array) ($_SESSION['rbsr_setup'] ?? []);
if ($step >= 3 && empty($setup['database']['table_name'])) {
    setup_redirect(2, ['connected' => '1']);
}
if ($step >= 4 && empty($setup['station'])) {
    setup_redirect(3);
}
if ($step >= 5 && empty($setup['admin'])) {
    setup_redirect(4);
}

setup_header('Setup Wizard', $step);
if ($error !== '') {
    echo '<div class="notice error"><strong>Setup could not continue.</strong><br>' . rbsr_h($error) . '</div>';
}
if (isset($_GET['database'])) {
    echo '<div class="notice success">Database table selected successfully.</div>';
}
if (isset($_GET['connected'])) {
    echo '<div class="notice success">Database connection successful. Choose an existing compatible table or create a new one.</div>';
}
if (isset($_GET['metadata']) && isset($setup['tested_track'])) {
    $track = (array) $setup['tested_track'];
    echo '<div class="notice success">Metadata test successful: <strong>' . rbsr_h((string) ($track['artist'] ?? '')) . ' — ' . rbsr_h((string) ($track['title'] ?? '')) . '</strong></div>';
}

if ($step === 1) {
    echo '<h2>1. Server requirements</h2><p>The standalone edition needs PHP and access to an existing MySQL or MariaDB database. It can reuse a compatible ratings table or create a new prefixed table.</p>';
    echo '<div class="checks">';
    foreach (setup_requirements() as $requirement) {
        echo '<div class="check ' . ($requirement['ok'] ? 'ok' : 'bad') . '"><span>' . ($requirement['ok'] ? '✓' : '×') . '</span><div><strong>' . rbsr_h($requirement['label']) . '</strong><small>' . rbsr_h($requirement['detail']) . '</small></div></div>';
    }
    echo '</div><form method="post"><input type="hidden" name="csrf" value="' . rbsr_h(rbsr_csrf_token()) . '"><button class="button primary">Continue to database</button></form>';
}

if ($step === 2) {
    $db = (array) ($setup['database'] ?? []);
    echo '<h2>2. Database connection</h2><p>You may use the same database as WordPress or another application. Song Ratings will not modify unrelated tables.</p>';
    if ($db === []) {
        echo '<form method="post" class="form-grid"><input type="hidden" name="csrf" value="' . rbsr_h(rbsr_csrf_token()) . '"><input type="hidden" name="database_action" value="connect">';
        setup_field('Database server', 'db_host', (string) ($db['host'] ?? 'localhost'), 'text', 'For example localhost or the database host from your provider');
        setup_field('Port', 'db_port', (string) ($db['port'] ?? '3306'), 'number');
        setup_field('Database name', 'db_name', (string) ($db['name'] ?? ''), 'text');
        setup_field('Database user', 'db_user', (string) ($db['user'] ?? ''), 'text');
        setup_field('Database password', 'db_password', (string) ($db['password'] ?? ''), 'password');
        setup_buttons(1, 'Test connection and find rating tables');
        echo '</form>';
    } else {
        $compatible = (array) ($setup['compatible_tables'] ?? []);
        echo '<form method="post"><input type="hidden" name="csrf" value="' . rbsr_h(rbsr_csrf_token()) . '"><input type="hidden" name="database_action" value="choose"><div class="table-choice-list">';
        foreach ($compatible as $table) {
            echo '<label class="table-choice"><input type="radio" name="table_choice" value="existing:' . rbsr_h((string) $table['name']) . '" required><span><strong>Use existing table <code>' . rbsr_h((string) $table['name']) . '</code></strong><small>' . number_format((int) $table['votes']) . ' votes across ' . number_format((int) $table['songs']) . ' songs. Choose this when WordPress or Analytics already uses this table.</small></span></label>';
        }
        if ($compatible === []) {
            echo '<p class="empty-choice">No compatible existing Song Ratings table was found.</p>';
        }
        echo '<label class="table-choice"><input type="radio" name="table_choice" value="new" required><span><strong>Create a new standalone table</strong><small>Use this for a new installation that should not share ratings with another system.</small><span class="prefix-field">Table prefix <input name="table_prefix" value="rbsr_" pattern="[a-zA-Z][a-zA-Z0-9_]{0,39}"></span></span></label></div>';
        echo '<div class="form-actions"><a class="button" href="?step=2&amp;reset=1">← Change database connection</a><button class="button primary" type="submit">Use selected table →</button></div></form>';
    }
}

if ($step === 3) {
    $station = (array) ($setup['station'] ?? []);
    $mode = (string) ($setup['mode'] ?? 'below-player');
    echo '<h2>3. Station and integration</h2><p>Choose how listeners will use the ratings. The Now Playing API supplies the current artist and title independently from the player.</p>';
    echo '<form method="post"><input type="hidden" name="csrf" value="' . rbsr_h(rbsr_csrf_token()) . '"><div class="mode-grid">';
    setup_mode('below-player', $mode, '▣', 'Widget under an existing player', 'Recommended: keep your current player and place the rating widget directly below it.');
    setup_mode('separate-page', $mode, '↗', 'Separate rating page', 'The player stays open in one tab and the listener rates the song in another tab.');
    setup_mode('player', $mode, '▶', 'Included neutral player', 'Play the stream, show the current song, and rate it in one configurable element.');
    echo '</div><div class="form-grid">';
    setup_field('Application URL', 'app_url', (string) ($setup['app_url'] ?? rbsr_detect_app_url()), 'url', 'Public URL of this uploaded folder, without /setup.');
    setup_field('Station name', 'station_name', (string) ($station['name'] ?? 'Example Radio'), 'text');
    setup_field('Station ID', 'station_slug', (string) ($station['slug'] ?? 'example-radio'), 'text', 'Stable lowercase identifier, for example main-station. Do not change it after collecting votes.');
    setup_field('Now Playing API', 'now_playing_url', (string) ($station['now_playing_url'] ?? ''), 'url', 'AzuraCast and simple JSON with artist and title are supported.');
    setup_field('Direct stream URL', 'stream_url', (string) ($station['stream_url'] ?? ''), 'url', 'Required for the included player; optional for rating-only modes.', false);
    echo '</div>';
    setup_buttons(2, 'Test metadata and continue');
    echo '</form>';
}

if ($step === 4) {
    $station = (array) $setup['station'];
    $theme = (array) ($station['theme'] ?? []);
    echo '<h2>4. Player design and administration</h2><p>Customize the neutral player so it fits the website. These settings also style the rating-only widget.</p>';
    echo '<form method="post"><input type="hidden" name="csrf" value="' . rbsr_h(rbsr_csrf_token()) . '"><div class="design-layout"><div class="form-grid">';
    setup_field('Logo URL', 'logo_url', (string) ($station['logo_url'] ?? ''), 'url', 'Used when the current song has no cover.', false);
    setup_field('Accent color', 'accent', (string) ($theme['accent'] ?? '#2563eb'), 'color');
    setup_field('Background color', 'background', (string) ($theme['background'] ?? '#111827'), 'color');
    setup_field('Text color', 'text', (string) ($theme['text'] ?? '#eef4ff'), 'color');
    echo '<label><span>Corner style</span><select name="radius" id="preview-radius"><option value="rounded">Rounded</option><option value="square">Square</option></select></label>';
    echo '<label><span>Player size</span><select name="size" id="preview-size"><option value="normal">Normal</option><option value="compact">Compact</option></select></label>';
    echo '<label><span>Widget language</span><select name="language"><option value="en">English</option><option value="de">Deutsch</option></select></label>';
    echo '<label class="checkbox"><input type="checkbox" name="show_cover" value="1" checked> <span>Show cover or station logo</span></label>';
    echo '<label class="wide"><span>Custom CSS (optional)</span><textarea name="custom_css" rows="4" maxlength="5000" placeholder="/* Advanced custom styling */"></textarea></label>';
    setup_field('Administrator username', 'admin_username', 'admin', 'text');
    setup_field('Administrator password', 'admin_password', '', 'password', 'At least 10 characters. This protects the standalone dashboard.');
    echo '</div><div><h3>Live preview</h3><div id="setup-preview" class="widget-preview"><div class="preview-head"><strong>' . rbsr_h((string) $station['name']) . '</strong><b>LIVE</b></div><div class="preview-track"><div class="preview-cover">♪</div><div><small>NOW PLAYING</small><strong>Example Song</strong><span>Example Artist</span></div></div><div class="preview-controls">▶ <input type="range" value="75"></div><div class="preview-rating"><span>How do you like this song?</span><div>👎 0 &nbsp; 😐 0 &nbsp; ❤️ 0</div></div></div></div></div>';
    setup_buttons(3, 'Review installation');
    echo '</form>';
    echo '<script src="../assets/setup-preview.js"></script>';
}

if ($step === 5) {
    $station = (array) $setup['station'];
    $db = (array) $setup['database'];
    $modeLabels = ['below-player' => 'Widget under an existing player', 'separate-page' => 'Separate rating page', 'player' => 'Included neutral player with ratings'];
    echo '<h2>5. Ready to install</h2><p>The wizard will write the protected configuration and lock the setup directory.</p><dl class="summary">';
    echo '<dt>Station</dt><dd>' . rbsr_h((string) $station['name']) . ' <code>' . rbsr_h((string) $station['slug']) . '</code></dd>';
    echo '<dt>Integration</dt><dd>' . rbsr_h($modeLabels[(string) ($setup['mode'] ?? '')] ?? 'Rating widget') . '</dd>';
    echo '<dt>Metadata test</dt><dd>' . rbsr_h((string) (($setup['tested_track']['artist'] ?? '') . ' — ' . ($setup['tested_track']['title'] ?? ''))) . '</dd>';
    echo '<dt>Database table</dt><dd><code>' . rbsr_h(rbsr_table_name($db)) . '</code> inside the existing database</dd>';
    echo '</dl><form method="post"><input type="hidden" name="csrf" value="' . rbsr_h(rbsr_csrf_token()) . '">';
    setup_buttons(4, 'Install Song Ratings');
    echo '</form>';
}

setup_footer();

function setup_requirements(): array
{
    foreach ([RBSR_ROOT . '/config', RBSR_STORAGE, RBSR_STORAGE . '/cache', RBSR_STORAGE . '/rate-limits'] as $directory) {
        if (!is_dir($directory)) {
            @mkdir($directory, 0750, true);
        }
    }
    return [
        ['label' => 'PHP 8.0 or newer', 'detail' => PHP_VERSION, 'ok' => version_compare(PHP_VERSION, '8.0.0', '>='), 'required' => true],
        ['label' => 'PDO MySQL extension', 'detail' => extension_loaded('pdo_mysql') ? 'Available' : 'Missing', 'ok' => extension_loaded('pdo_mysql'), 'required' => true],
        ['label' => 'JSON extension', 'detail' => extension_loaded('json') ? 'Available' : 'Missing', 'ok' => extension_loaded('json'), 'required' => true],
        ['label' => 'Metadata requests', 'detail' => function_exists('curl_init') ? 'PHP cURL available' : ((bool) ini_get('allow_url_fopen') ? 'allow_url_fopen available' : 'No HTTP client available'), 'ok' => function_exists('curl_init') || (bool) ini_get('allow_url_fopen'), 'required' => true],
        ['label' => 'Config directory writable', 'detail' => RBSR_ROOT . '/config', 'ok' => is_writable(RBSR_ROOT . '/config'), 'required' => true],
        ['label' => 'Storage directory writable', 'detail' => RBSR_STORAGE, 'ok' => is_writable(RBSR_STORAGE), 'required' => true],
    ];
}

function setup_redirect(int $step, array $query = []): never
{
    $query = array_merge(['step' => $step], $query);
    header('Location: ?' . http_build_query($query));
    exit;
}

function setup_header(string $title, int $step): void
{
    $steps = [1 => 'Requirements', 2 => 'Database', 3 => 'Station', 4 => 'Design', 5 => 'Finish'];
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . rbsr_h($title) . ' — RadioBOSS Song Ratings</title><link rel="stylesheet" href="../assets/admin.css"></head><body class="setup-page"><main class="setup-shell"><header class="brand"><div class="brand-icon">♥</div><div><strong>RadioBOSS Song Ratings</strong><span>Standalone Edition ' . rbsr_h(RBSR_STANDALONE_VERSION) . '</span></div></header><ol class="steps">';
    foreach ($steps as $number => $label) {
        $class = $number === $step ? 'current' : ($number < $step ? 'done' : '');
        echo '<li class="' . $class . '"><b>' . $number . '</b><span>' . rbsr_h($label) . '</span></li>';
    }
    echo '</ol><section class="panel">';
}

function setup_footer(): void
{
    echo '</section><footer>Unofficial community project · GPL-2.0-or-later</footer></main></body></html>';
}

function setup_field(string $label, string $name, string $value, string $type = 'text', string $hint = '', bool $required = true): void
{
    $requiredAttribute = $required ? ' required' : '';
    $min = $type === 'number' ? ' min="1" max="65535"' : '';
    echo '<label><span>' . rbsr_h($label) . '</span><input id="setup-' . rbsr_h($name) . '" type="' . rbsr_h($type) . '" name="' . rbsr_h($name) . '" value="' . rbsr_h($value) . '"' . $requiredAttribute . $min . '>';
    if ($hint !== '') {
        echo '<small>' . rbsr_h($hint) . '</small>';
    }
    echo '</label>';
}

function setup_mode(string $value, string $current, string $icon, string $title, string $description): void
{
    echo '<label class="mode"><input type="radio" name="mode" value="' . rbsr_h($value) . '"' . ($value === $current ? ' checked' : '') . '><b>' . rbsr_h($icon) . '</b><strong>' . rbsr_h($title) . '</strong><small>' . rbsr_h($description) . '</small></label>';
}

function setup_buttons(int $backStep, string $nextLabel): void
{
    echo '<div class="form-actions"><a class="button" href="?step=' . $backStep . '">← Back</a><button class="button primary" type="submit">' . rbsr_h($nextLabel) . ' →</button></div>';
}
