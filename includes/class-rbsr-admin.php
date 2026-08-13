<?php

if (!defined('ABSPATH')) {
    exit;
}

final class RBSR_Admin
{
    public static function register_menu(): void
    {
        add_menu_page(
            __('Song Ratings', 'radioboss-song-ratings'),
            __('Song Ratings', 'radioboss-song-ratings'),
            'manage_options',
            'rbsr-ratings',
            [self::class, 'ratings_page'],
            'dashicons-heart',
            58
        );
        add_submenu_page(
            'rbsr-ratings',
            __('Song Ratings', 'radioboss-song-ratings'),
            __('Ratings', 'radioboss-song-ratings'),
            'manage_options',
            'rbsr-ratings',
            [self::class, 'ratings_page']
        );
        add_submenu_page(
            'rbsr-ratings',
            __('Song Ratings Setup Wizard', 'radioboss-song-ratings'),
            __('Setup Wizard', 'radioboss-song-ratings'),
            'manage_options',
            'rbsr-setup',
            [self::class, 'wizard_page']
        );
        add_submenu_page(
            'rbsr-ratings',
            __('Song Ratings Settings', 'radioboss-song-ratings'),
            __('Settings', 'radioboss-song-ratings'),
            'manage_options',
            'rbsr-settings',
            [self::class, 'settings_page']
        );
    }

    public static function enqueue_assets(string $hook): void
    {
        if (!str_contains($hook, 'rbsr-')) {
            return;
        }
        wp_enqueue_style('rbsr-admin', RBSR_URL . 'assets/rbsr-admin.css', [], RBSR_VERSION);
    }

    public static function register_settings(): void
    {
        register_setting('rbsr_settings_group', RBSR_Core::OPTION, [
            'type' => 'array',
            'sanitize_callback' => [self::class, 'sanitize_settings'],
            'default' => RBSR_Core::defaults(),
        ]);
    }

    public static function sanitize_settings($input): array
    {
        $input = is_array($input) ? $input : [];
        $output = [];
        $source = sanitize_key((string) ($input['default_source'] ?? 'api'));
        $output['default_source'] = in_array($source, ['api', 'external'], true) ? $source : 'api';

        for ($index = 1; $index <= RBSR_Core::MAX_STATIONS; $index++) {
            $output["station_{$index}_enabled"] = empty($input["station_{$index}_enabled"]) ? '0' : '1';
            $output["station_{$index}_name"] = sanitize_text_field((string) ($input["station_{$index}_name"] ?? ''));
            $output["station_{$index}_slug"] = sanitize_key((string) ($input["station_{$index}_slug"] ?? ''));
            $output["station_{$index}_api"] = esc_url_raw((string) ($input["station_{$index}_api"] ?? ''));
            $output["station_{$index}_stream"] = esc_url_raw((string) ($input["station_{$index}_stream"] ?? ''));
            $output["station_{$index}_catalog"] = esc_url_raw((string) ($input["station_{$index}_catalog"] ?? ''));
            $output["station_{$index}_color"] = sanitize_hex_color((string) ($input["station_{$index}_color"] ?? '')) ?: '#2563eb';
        }

        return $output;
    }

    public static function setup_notice(): void
    {
        if (
            !current_user_can('manage_options')
            || get_option('rbsr_setup_complete') === '1'
            || (isset($_GET['page']) && sanitize_key((string) wp_unslash($_GET['page'])) === 'rbsr-setup')
        ) {
            return;
        }
        ?>
        <div class="notice notice-info rbsr-setup-notice">
            <p>
                <strong><?php esc_html_e('RadioBOSS Song Ratings is ready for setup.', 'radioboss-song-ratings'); ?></strong>
                <?php esc_html_e('Choose whether to connect an existing player, a metadata API, or the included demo player.', 'radioboss-song-ratings'); ?>
            </p>
            <p><a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=rbsr-setup')); ?>"><?php esc_html_e('Start Setup Wizard', 'radioboss-song-ratings'); ?></a></p>
        </div>
        <?php
    }

    public static function wizard_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $step = max(1, min(4, absint(wp_unslash($_GET['step'] ?? 1))));
        $settings = RBSR_Core::settings();
        $mode = (string) get_option('rbsr_setup_mode', 'api');
        if (!in_array($mode, ['existing', 'api', 'player'], true)) {
            $mode = 'api';
        }
        $error = '';
        $success = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            check_admin_referer('rbsr_wizard_step_' . $step);

            if ($step === 1) {
                $mode = sanitize_key((string) wp_unslash($_POST['mode'] ?? 'api'));
                if (!in_array($mode, ['existing', 'api', 'player'], true)) {
                    $mode = 'api';
                }
                update_option('rbsr_setup_mode', $mode, false);
                $settings['default_source'] = $mode === 'existing' ? 'external' : 'api';
                update_option(RBSR_Core::OPTION, $settings, false);
                self::wizard_redirect(2);
            }

            if ($step === 2) {
                $name = sanitize_text_field((string) wp_unslash($_POST['station_name'] ?? ''));
                $slug = sanitize_key((string) wp_unslash($_POST['station_slug'] ?? ''));
                if ($name === '' || $slug === '') {
                    $error = __('Station name and station ID are required.', 'radioboss-song-ratings');
                } else {
                    $settings['station_1_enabled'] = '1';
                    $settings['station_1_name'] = $name;
                    $settings['station_1_slug'] = $slug;
                    $settings['station_1_color'] = sanitize_hex_color((string) wp_unslash($_POST['station_color'] ?? '')) ?: '#2563eb';
                    update_option(RBSR_Core::OPTION, $settings, false);
                    self::wizard_redirect(3);
                }
            }

            if ($step === 3) {
                $api = esc_url_raw((string) wp_unslash($_POST['station_api'] ?? ''));
                $stream = esc_url_raw((string) wp_unslash($_POST['station_stream'] ?? ''));
                $catalog = esc_url_raw((string) wp_unslash($_POST['station_catalog'] ?? ''));

                if ($catalog === '') {
                    $error = __('A SongSync songs.json URL is required.', 'radioboss-song-ratings');
                } else {
                    $catalog_test = self::test_catalog_source($catalog);
                    if (is_wp_error($catalog_test)) {
                        $error = $catalog_test->get_error_message();
                    }
                }

                if ($error === '' && $mode !== 'existing' && $api === '') {
                    $error = __('A Now Playing API URL is required for this setup mode.', 'radioboss-song-ratings');
                } elseif ($error === '' && $mode === 'player' && $stream === '') {
                    $error = __('A stream URL is required for the included demo player.', 'radioboss-song-ratings');
                } elseif ($error === '' && $api !== '') {
                    $test = self::test_metadata_source($api);
                    if (is_wp_error($test)) {
                        $error = $test->get_error_message();
                    } else {
                        $success = sprintf(
                            __('Metadata test successful: %1$s — %2$s', 'radioboss-song-ratings'),
                            $test['artist'],
                            $test['title']
                        );
                    }
                }

                if ($error === '') {
                    $settings['station_1_api'] = $api;
                    $settings['station_1_stream'] = $stream;
                    $settings['station_1_catalog'] = $catalog;
                    update_option(RBSR_Core::OPTION, $settings, false);
                    self::wizard_redirect(4, $success !== '' ? ['tested' => '1'] : []);
                }
            }

            if ($step === 4) {
                update_option('rbsr_setup_complete', '1', false);
                wp_safe_redirect(admin_url('admin.php?page=rbsr-ratings&rbsr_setup=complete'));
                exit;
            }
        }

        $settings = RBSR_Core::settings();
        $name = (string) ($settings['station_1_name'] ?? 'Example Radio');
        $slug = (string) ($settings['station_1_slug'] ?? 'example-radio');
        $color = (string) ($settings['station_1_color'] ?? '#2563eb');
        $api = (string) ($settings['station_1_api'] ?? '');
        $stream = (string) ($settings['station_1_stream'] ?? '');
        $catalog = (string) ($settings['station_1_catalog'] ?? '');

        ?>
        <div class="wrap rbsr-admin rbsr-wizard">
            <h1><?php esc_html_e('RadioBOSS Song Ratings Setup', 'radioboss-song-ratings'); ?></h1>
            <ol class="rbsr-steps" aria-label="<?php esc_attr_e('Setup progress', 'radioboss-song-ratings'); ?>">
                <?php foreach ([1 => __('Integration', 'radioboss-song-ratings'), 2 => __('Station', 'radioboss-song-ratings'), 3 => __('Metadata', 'radioboss-song-ratings'), 4 => __('Finish', 'radioboss-song-ratings')] as $number => $label): ?>
                    <li class="<?php echo $number === $step ? 'is-current' : ($number < $step ? 'is-done' : ''); ?>">
                        <span><?php echo (int) $number; ?></span><?php echo esc_html($label); ?>
                    </li>
                <?php endforeach; ?>
            </ol>

            <?php if ($error !== ''): ?>
                <div class="notice notice-error inline"><p><?php echo esc_html($error); ?></p></div>
            <?php endif; ?>

            <div class="rbsr-wizard-card">
                <?php if ($step === 1): ?>
                    <h2><?php esc_html_e('How should ratings receive the current song?', 'radioboss-song-ratings'); ?></h2>
                    <p><?php esc_html_e('The listener can keep any existing radio player. The included player is optional.', 'radioboss-song-ratings'); ?></p>
                    <form method="post">
                        <?php wp_nonce_field('rbsr_wizard_step_1'); ?>
                        <div class="rbsr-mode-grid">
                            <?php self::mode_option('existing', $mode, '🔌', __('Use an existing player', 'radioboss-song-ratings'), __('Your player sends artist and title to the rating widget.', 'radioboss-song-ratings')); ?>
                            <?php self::mode_option('api', $mode, '📡', __('Read a metadata API', 'radioboss-song-ratings'), __('The widget reads the current song from an AzuraCast or compatible JSON endpoint.', 'radioboss-song-ratings')); ?>
                            <?php self::mode_option('player', $mode, '▶️', __('Use the demo player', 'radioboss-song-ratings'), __('The included neutral player reads metadata and plays the configured stream.', 'radioboss-song-ratings')); ?>
                        </div>
                        <?php submit_button(__('Continue', 'radioboss-song-ratings')); ?>
                    </form>

                <?php elseif ($step === 2): ?>
                    <h2><?php esc_html_e('Configure the first station', 'radioboss-song-ratings'); ?></h2>
                    <form method="post">
                        <?php wp_nonce_field('rbsr_wizard_step_2'); ?>
                        <table class="form-table" role="presentation">
                            <tr><th><label for="rbsr-wizard-name"><?php esc_html_e('Station name', 'radioboss-song-ratings'); ?></label></th><td><input class="regular-text" id="rbsr-wizard-name" name="station_name" value="<?php echo esc_attr($name); ?>" required></td></tr>
                            <tr><th><label for="rbsr-wizard-slug"><?php esc_html_e('Station ID', 'radioboss-song-ratings'); ?></label></th><td><input class="regular-text" id="rbsr-wizard-slug" name="station_slug" value="<?php echo esc_attr($slug); ?>" pattern="[a-z0-9_-]+" required><p class="description"><?php esc_html_e('Lowercase identifier, for example: main-station', 'radioboss-song-ratings'); ?></p></td></tr>
                            <tr><th><label for="rbsr-wizard-color"><?php esc_html_e('Accent color', 'radioboss-song-ratings'); ?></label></th><td><input type="color" id="rbsr-wizard-color" name="station_color" value="<?php echo esc_attr($color); ?>"></td></tr>
                        </table>
                        <?php self::wizard_buttons(1); ?>
                    </form>

                <?php elseif ($step === 3): ?>
                    <h2><?php esc_html_e('Connect the metadata', 'radioboss-song-ratings'); ?></h2>
                    <form method="post">
                        <?php wp_nonce_field('rbsr_wizard_step_3'); ?>
                        <?php if ($mode === 'existing'): ?>
                            <p><?php esc_html_e('A metadata URL is optional because your existing player can send artist and title directly. Enter one only if the widget should also be able to read the current song itself.', 'radioboss-song-ratings'); ?></p>
                        <?php endif; ?>
                        <?php if ($mode !== 'player'): ?>
                            <input type="hidden" name="station_stream" value="<?php echo esc_attr($stream); ?>">
                        <?php endif; ?>
                        <table class="form-table" role="presentation">
                            <tr>
                                <th><label for="rbsr-wizard-catalog"><?php esc_html_e('SongSync songs.json URL', 'radioboss-song-ratings'); ?></label></th>
                                <td><input type="url" class="large-text" id="rbsr-wizard-catalog" name="station_catalog" value="<?php echo esc_attr($catalog); ?>" placeholder="https://radio.example/wp-content/radioboss-data/main/public/songs.json" required><p class="description"><?php esc_html_e('Required music catalog generated by SongSync. The wizard checks that the JSON is reachable and contains songs with artist and title. RadioBOSS may use MySQL or SQLite; no RadioBOSS database login is required here.', 'radioboss-song-ratings'); ?></p></td>
                            </tr>
                            <tr>
                                <th><label for="rbsr-wizard-api"><?php esc_html_e('Now Playing API', 'radioboss-song-ratings'); ?></label></th>
                                <td><input type="url" class="large-text" id="rbsr-wizard-api" name="station_api" value="<?php echo esc_attr($api); ?>" placeholder="https://radio.example/api/nowplaying/station"><p class="description"><?php esc_html_e('AzuraCast public Now Playing JSON and simple artist/title JSON are supported.', 'radioboss-song-ratings'); ?></p></td>
                            </tr>
                            <?php if ($mode === 'player'): ?>
                                <tr><th><label for="rbsr-wizard-stream"><?php esc_html_e('Stream URL', 'radioboss-song-ratings'); ?></label></th><td><input type="url" class="large-text" id="rbsr-wizard-stream" name="station_stream" value="<?php echo esc_attr($stream); ?>" placeholder="https://radio.example/listen/station/radio.mp3" required></td></tr>
                            <?php endif; ?>
                        </table>
                        <?php self::wizard_buttons(2, $mode === 'existing' ? __('Continue', 'radioboss-song-ratings') : __('Test and continue', 'radioboss-song-ratings')); ?>
                    </form>

                <?php else: ?>
                    <h2><?php esc_html_e('Setup complete', 'radioboss-song-ratings'); ?></h2>
                    <?php if (isset($_GET['tested'])): ?><div class="notice notice-success inline"><p><?php esc_html_e('The metadata endpoint responded successfully.', 'radioboss-song-ratings'); ?></p></div><?php endif; ?>
                    <p><?php esc_html_e('Add the following shortcode to a WordPress page:', 'radioboss-song-ratings'); ?></p>
                    <?php if ($mode === 'existing'): ?>
                        <pre><code>[radioboss_song_ratings station="<?php echo esc_html($slug); ?>" source="external"]</code></pre>
                        <p><?php esc_html_e('Whenever the track changes, your existing player sends its metadata like this:', 'radioboss-song-ratings'); ?></p>
                        <pre><code>window.RBSR.setTrack({
  station: '<?php echo esc_html($slug); ?>',
  artist: currentArtist,
  title: currentTitle,
  art: currentCoverUrl
});</code></pre>
                    <?php elseif ($mode === 'player'): ?>
                        <pre><code>[radioboss_rating_player station="<?php echo esc_html($slug); ?>"]</code></pre>
                    <?php else: ?>
                        <pre><code>[radioboss_song_ratings station="<?php echo esc_html($slug); ?>"]</code></pre>
                    <?php endif; ?>
                    <form method="post">
                        <?php wp_nonce_field('rbsr_wizard_step_4'); ?>
                        <?php submit_button(__('Finish Setup', 'radioboss-song-ratings')); ?>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    public static function settings_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $settings = RBSR_Core::settings();
        ?>
        <div class="wrap rbsr-admin">
            <h1><?php esc_html_e('Song Ratings Settings', 'radioboss-song-ratings'); ?></h1>
            <p><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=rbsr-setup')); ?>"><?php esc_html_e('Run Setup Wizard', 'radioboss-song-ratings'); ?></a></p>
            <form method="post" action="options.php">
                <?php settings_fields('rbsr_settings_group'); ?>
                <h2><?php esc_html_e('Default metadata source', 'radioboss-song-ratings'); ?></h2>
                <select name="<?php echo esc_attr(RBSR_Core::OPTION); ?>[default_source]">
                    <option value="api" <?php selected($settings['default_source'], 'api'); ?>><?php esc_html_e('Now Playing API', 'radioboss-song-ratings'); ?></option>
                    <option value="external" <?php selected($settings['default_source'], 'external'); ?>><?php esc_html_e('Existing player', 'radioboss-song-ratings'); ?></option>
                </select>

                <?php for ($index = 1; $index <= RBSR_Core::MAX_STATIONS; $index++): ?>
                    <div class="rbsr-settings-station">
                        <h2><?php echo esc_html(sprintf(__('Station %d', 'radioboss-song-ratings'), $index)); ?></h2>
                        <p><label><input type="checkbox" name="<?php echo esc_attr(RBSR_Core::OPTION); ?>[station_<?php echo (int) $index; ?>_enabled]" value="1" <?php checked($settings["station_{$index}_enabled"], '1'); ?>> <?php esc_html_e('Enable this station', 'radioboss-song-ratings'); ?></label></p>
                        <table class="form-table" role="presentation">
                            <?php self::station_setting_row($index, 'name', __('Station name', 'radioboss-song-ratings'), $settings, 'text'); ?>
                            <?php self::station_setting_row($index, 'slug', __('Station ID', 'radioboss-song-ratings'), $settings, 'text'); ?>
                            <?php self::station_setting_row($index, 'api', __('Now Playing API', 'radioboss-song-ratings'), $settings, 'url'); ?>
                            <?php self::station_setting_row($index, 'stream', __('Stream URL', 'radioboss-song-ratings'), $settings, 'url'); ?>
                            <?php self::station_setting_row($index, 'catalog', __('SongSync songs.json URL', 'radioboss-song-ratings'), $settings, 'url'); ?>
                            <?php self::station_setting_row($index, 'color', __('Accent color', 'radioboss-song-ratings'), $settings, 'color'); ?>
                        </table>
                    </div>
                <?php endfor; ?>
                <?php submit_button(); ?>
            </form>
            <?php self::shortcodes_panel(); ?>
        </div>
        <?php
    }

    public static function ratings_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (isset($_GET['view']) && sanitize_key((string) wp_unslash($_GET['view'])) === 'song') {
            self::song_detail_page();
            return;
        }

        global $wpdb;
        $table = RBSR_Core::table();
        $stationFilter = sanitize_key((string) wp_unslash($_GET['station'] ?? ''));
        $statusFilter = sanitize_key((string) wp_unslash($_GET['status'] ?? ''));
        $search = sanitize_text_field((string) wp_unslash($_GET['search'] ?? ''));
        $minimumVotes = max(0, absint(wp_unslash($_GET['minimum_votes'] ?? 0)));
        $page = max(1, absint(wp_unslash($_GET['paged'] ?? 1)));
        $perPage = 50;

        $sortMap = [
            'station' => 'station', 'artist' => 'artist', 'title' => 'title',
            'dislikes' => 'dislikes', 'okay_votes' => 'okay_votes',
            'love_votes' => 'love_votes', 'total_votes' => 'total_votes',
            'score' => 'score', 'last_vote' => 'last_vote',
        ];
        $sort = sanitize_key((string) wp_unslash($_GET['sort'] ?? 'last_vote'));
        $sortSql = $sortMap[$sort] ?? 'last_vote';
        $order = strtolower((string) wp_unslash($_GET['order'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';

        $where = [];
        $params = [];
        if ($stationFilter !== '') {
            $where[] = 'station = %s';
            $params[] = $stationFilter;
        }
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(artist LIKE %s OR title LIKE %s)';
            $params[] = $like;
            $params[] = $like;
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $sql = "SELECT station, song_key, MAX(artist) AS artist, MAX(title) AS title,
                    SUM(rating = 'dislike') AS dislikes,
                    SUM(rating = 'ok') AS okay_votes,
                    SUM(rating = 'love') AS love_votes,
                    COUNT(*) AS total_votes,
                    ROUND(((SUM(rating = 'love') - SUM(rating = 'dislike')) / COUNT(*)) * 100) AS score,
                    MAX(updated_at) AS last_vote
                FROM {$table} {$whereSql}
                GROUP BY station, song_key
                HAVING COUNT(*) >= %d
                ORDER BY {$sortSql} {$order}";
        $params[] = $minimumVotes;
        $prepared = $wpdb->prepare($sql, ...$params);
        $rows = (array) $wpdb->get_results($prepared, ARRAY_A);

        if ($statusFilter !== '') {
            $rows = array_values(array_filter($rows, static function (array $row) use ($statusFilter): bool {
                $status = RBSR_Core::status((int) $row['dislikes'], (int) $row['okay_votes'], (int) $row['love_votes'], (int) $row['total_votes']);
                return $status['key'] === $statusFilter;
            }));
        }

        $totalSongs = count($rows);
        $pages = max(1, (int) ceil($totalSongs / $perPage));
        $page = min($page, $pages);
        $rows = array_slice($rows, ($page - 1) * $perPage, $perPage);

        $stats = $wpdb->get_row(
            "SELECT COUNT(*) AS votes, COUNT(DISTINCT CONCAT(station, ':', song_key)) AS songs,
                SUM(rating = 'dislike') AS dislikes, SUM(rating = 'ok') AS okay_votes,
                SUM(rating = 'love') AS love_votes FROM {$table}",
            ARRAY_A
        ) ?: [];

        $stations = RBSR_Core::stations();
        ?>
        <div class="wrap rbsr-admin">
            <h1><?php esc_html_e('Song Ratings', 'radioboss-song-ratings'); ?></h1>
            <?php if (isset($_GET['rbsr_setup'])): ?><div class="notice notice-success inline"><p><?php esc_html_e('Setup completed successfully.', 'radioboss-song-ratings'); ?></p></div><?php endif; ?>
            <div class="rbsr-stat-grid">
                <?php self::stat_card(__('Total votes', 'radioboss-song-ratings'), (int) ($stats['votes'] ?? 0), '🗳️'); ?>
                <?php self::stat_card(__('Rated songs', 'radioboss-song-ratings'), (int) ($stats['songs'] ?? 0), '🎵'); ?>
                <?php self::stat_card(__('Love it', 'radioboss-song-ratings'), (int) ($stats['love_votes'] ?? 0), '❤️'); ?>
                <?php self::stat_card(__("It's okay", 'radioboss-song-ratings'), (int) ($stats['okay_votes'] ?? 0), '😐'); ?>
                <?php self::stat_card(__('Dislike', 'radioboss-song-ratings'), (int) ($stats['dislikes'] ?? 0), '👎'); ?>
            </div>

            <form class="rbsr-filters" method="get">
                <input type="hidden" name="page" value="rbsr-ratings">
                <select name="station"><option value=""><?php esc_html_e('All stations', 'radioboss-song-ratings'); ?></option><?php foreach ($stations as $slug => $station): ?><option value="<?php echo esc_attr($slug); ?>" <?php selected($stationFilter, $slug); ?>><?php echo esc_html($station['name']); ?></option><?php endforeach; ?></select>
                <select name="status"><option value=""><?php esc_html_e('All recommendations', 'radioboss-song-ratings'); ?></option><option value="popular" <?php selected($statusFilter, 'popular'); ?>>🟢 <?php esc_html_e('Popular', 'radioboss-song-ratings'); ?></option><option value="observe" <?php selected($statusFilter, 'observe'); ?>>🟡 <?php esc_html_e('Observe', 'radioboss-song-ratings'); ?></option><option value="review" <?php selected($statusFilter, 'review'); ?>>🔴 <?php esc_html_e('Review', 'radioboss-song-ratings'); ?></option><option value="insufficient" <?php selected($statusFilter, 'insufficient'); ?>>⚪ <?php esc_html_e('Not enough votes', 'radioboss-song-ratings'); ?></option></select>
                <select name="minimum_votes"><?php foreach ([0, 5, 10, 20, 50] as $minimum): ?><option value="<?php echo (int) $minimum; ?>" <?php selected($minimumVotes, $minimum); ?>><?php echo esc_html($minimum === 0 ? __('Any number of votes', 'radioboss-song-ratings') : sprintf(__('At least %d votes', 'radioboss-song-ratings'), $minimum)); ?></option><?php endforeach; ?></select>
                <input type="search" name="search" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search artist or title', 'radioboss-song-ratings'); ?>">
                <button class="button button-primary"><?php esc_html_e('Filter', 'radioboss-song-ratings'); ?></button>
                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=rbsr-ratings')); ?>"><?php esc_html_e('Reset', 'radioboss-song-ratings'); ?></a>
            </form>

            <p><strong><?php echo number_format_i18n($totalSongs); ?></strong> <?php esc_html_e('rated songs found', 'radioboss-song-ratings'); ?></p>
            <div class="rbsr-table-wrap"><table class="widefat striped rbsr-table">
                <thead><tr><th><?php self::sort_link(__('Station', 'radioboss-song-ratings'), 'station', $sort, $order); ?></th><th><?php self::sort_link(__('Artist', 'radioboss-song-ratings'), 'artist', $sort, $order); ?></th><th><?php self::sort_link(__('Title', 'radioboss-song-ratings'), 'title', $sort, $order); ?></th><th>👎</th><th>😐</th><th>❤️</th><th><?php self::sort_link(__('Score', 'radioboss-song-ratings'), 'score', $sort, $order); ?></th><th><?php esc_html_e('Status', 'radioboss-song-ratings'); ?></th></tr></thead>
                <tbody>
                <?php if ($rows === []): ?><tr><td colspan="8"><?php esc_html_e('No ratings match the selected filters.', 'radioboss-song-ratings'); ?></td></tr><?php endif; ?>
                <?php foreach ($rows as $row): $status = RBSR_Core::status((int) $row['dislikes'], (int) $row['okay_votes'], (int) $row['love_votes'], (int) $row['total_votes']); $detail = add_query_arg(['page' => 'rbsr-ratings', 'view' => 'song', 'station' => $row['station'], 'song_key' => $row['song_key']], admin_url('admin.php')); ?>
                    <tr><td><?php echo esc_html($stations[$row['station']]['name'] ?? ucwords(str_replace(['-', '_'], ' ', (string) $row['station']))); ?></td><td><?php echo esc_html((string) $row['artist']); ?></td><td><a href="<?php echo esc_url($detail); ?>"><?php echo esc_html((string) $row['title']); ?></a></td><td><?php echo (int) $row['dislikes']; ?></td><td><?php echo (int) $row['okay_votes']; ?></td><td><?php echo (int) $row['love_votes']; ?></td><td><?php echo (int) $row['score'] > 0 ? '+' : ''; ?><?php echo (int) $row['score']; ?></td><td><span class="rbsr-status rbsr-status-<?php echo esc_attr($status['key']); ?>"><?php echo esc_html($status['icon'] . ' ' . $status['label']); ?></span></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <?php if ($pages > 1): ?><div class="tablenav"><div class="tablenav-pages"><?php echo wp_kses_post(paginate_links(['base' => add_query_arg('paged', '%#%'), 'format' => '', 'current' => $page, 'total' => $pages])); ?></div></div><?php endif; ?>
        </div>
        <?php
    }

    private static function song_detail_page(): void
    {
        global $wpdb;
        $station = sanitize_key((string) wp_unslash($_GET['station'] ?? ''));
        $songKey = preg_replace('/[^a-f0-9]/', '', strtolower((string) wp_unslash($_GET['song_key'] ?? ''))) ?? '';
        $table = RBSR_Core::table();

        $summary = $wpdb->get_row($wpdb->prepare(
            "SELECT station, song_key, MAX(artist) AS artist, MAX(title) AS title,
                SUM(rating = 'dislike') AS dislikes, SUM(rating = 'ok') AS okay_votes,
                SUM(rating = 'love') AS love_votes, COUNT(*) AS total_votes,
                MIN(created_at) AS first_vote, MAX(updated_at) AS last_vote
             FROM {$table} WHERE station = %s AND song_key = %s GROUP BY station, song_key",
            $station,
            $songKey
        ), ARRAY_A);

        if (!$summary) {
            wp_die(esc_html__('Song rating not found.', 'radioboss-song-ratings'));
        }

        $events = (array) $wpdb->get_results($wpdb->prepare(
            "SELECT rating, created_at, updated_at FROM {$table}
             WHERE station = %s AND song_key = %s ORDER BY updated_at DESC LIMIT 100",
            $station,
            $songKey
        ), ARRAY_A);
        $status = RBSR_Core::status((int) $summary['dislikes'], (int) $summary['okay_votes'], (int) $summary['love_votes'], (int) $summary['total_votes']);
        ?>
        <div class="wrap rbsr-admin">
            <p><a href="<?php echo esc_url(admin_url('admin.php?page=rbsr-ratings')); ?>">← <?php esc_html_e('Back to Song Ratings', 'radioboss-song-ratings'); ?></a></p>
            <h1><?php echo esc_html((string) $summary['title']); ?></h1>
            <p class="rbsr-detail-artist"><?php echo esc_html((string) $summary['artist']); ?></p>
            <div class="rbsr-stat-grid">
                <?php self::stat_card(__('Total votes', 'radioboss-song-ratings'), (int) $summary['total_votes'], '🗳️'); ?>
                <?php self::stat_card(__('Love it', 'radioboss-song-ratings'), (int) $summary['love_votes'], '❤️'); ?>
                <?php self::stat_card(__("It's okay", 'radioboss-song-ratings'), (int) $summary['okay_votes'], '😐'); ?>
                <?php self::stat_card(__('Dislike', 'radioboss-song-ratings'), (int) $summary['dislikes'], '👎'); ?>
            </div>
            <p><span class="rbsr-status rbsr-status-<?php echo esc_attr($status['key']); ?>"><?php echo esc_html($status['icon'] . ' ' . $status['label']); ?></span></p>
            <h2><?php esc_html_e('Recent rating activity', 'radioboss-song-ratings'); ?></h2>
            <table class="widefat striped"><thead><tr><th><?php esc_html_e('Rating', 'radioboss-song-ratings'); ?></th><th><?php esc_html_e('First submitted', 'radioboss-song-ratings'); ?></th><th><?php esc_html_e('Last updated', 'radioboss-song-ratings'); ?></th></tr></thead><tbody><?php foreach ($events as $event): ?><tr><td><?php echo esc_html(self::rating_icon((string) $event['rating'])); ?></td><td><?php echo esc_html((string) $event['created_at']); ?> UTC</td><td><?php echo esc_html((string) $event['updated_at']); ?> UTC</td></tr><?php endforeach; ?></tbody></table>
        </div>
        <?php
    }

    private static function test_catalog_source(string $url)
    {
        $response = wp_remote_get($url, ['timeout' => 8, 'redirection' => 3]);
        if (is_wp_error($response)) {
            return $response;
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            return new WP_Error('rbsr_test_catalog_http', sprintf(__('The SongSync catalog returned HTTP %d.', 'radioboss-song-ratings'), $code));
        }
        $data = json_decode((string) wp_remote_retrieve_body($response), true);
        if (!is_array($data)) {
            return new WP_Error('rbsr_test_catalog_json', __('The SongSync catalog is not valid JSON.', 'radioboss-song-ratings'));
        }
        foreach ($data as $track) {
            if (is_array($track) && !empty($track['artist']) && !empty($track['title'])) {
                return true;
            }
        }
        return new WP_Error('rbsr_test_catalog_tracks', __('The SongSync catalog is reachable, but no song with artist and title was found.', 'radioboss-song-ratings'));
    }

    private static function test_metadata_source(string $url)
    {
        $response = wp_safe_remote_get($url, ['timeout' => 10, 'redirection' => 3, 'headers' => ['Accept' => 'application/json']]);
        if (is_wp_error($response)) {
            return new WP_Error('rbsr_test_failed', sprintf(__('Connection failed: %s', 'radioboss-song-ratings'), $response->get_error_message()));
        }
        if (wp_remote_retrieve_response_code($response) !== 200) {
            return new WP_Error('rbsr_test_http', sprintf(__('The metadata URL returned HTTP %d.', 'radioboss-song-ratings'), wp_remote_retrieve_response_code($response)));
        }
        try {
            $data = json_decode(wp_remote_retrieve_body($response), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            return new WP_Error('rbsr_test_json', __('The metadata URL did not return valid JSON.', 'radioboss-song-ratings'));
        }
        if (!is_array($data)) {
            return new WP_Error('rbsr_test_data', __('The metadata response is not a JSON object.', 'radioboss-song-ratings'));
        }
        $candidates = [$data['now_playing']['song'] ?? null, $data['current']['song'] ?? null, $data['current'] ?? null, $data['current_song'] ?? null, $data['song'] ?? null, $data];
        foreach ($candidates as $candidate) {
            if (is_array($candidate) && !empty($candidate['artist']) && !empty($candidate['title'])) {
                return ['artist' => sanitize_text_field((string) $candidate['artist']), 'title' => sanitize_text_field((string) $candidate['title'])];
            }
        }
        return new WP_Error('rbsr_test_track', __('The JSON was reachable, but no artist and title were found.', 'radioboss-song-ratings'));
    }

    private static function wizard_redirect(int $step, array $extra = []): void
    {
        wp_safe_redirect(add_query_arg(array_merge(['page' => 'rbsr-setup', 'step' => $step], $extra), admin_url('admin.php')));
        exit;
    }

    private static function wizard_buttons(int $backStep, string $continueLabel = ''): void
    {
        $continueLabel = $continueLabel !== '' ? $continueLabel : __('Continue', 'radioboss-song-ratings');
        ?>
        <p class="submit"><a class="button" href="<?php echo esc_url(add_query_arg(['page' => 'rbsr-setup', 'step' => $backStep], admin_url('admin.php'))); ?>">← <?php esc_html_e('Back', 'radioboss-song-ratings'); ?></a> <button type="submit" class="button button-primary"><?php echo esc_html($continueLabel); ?></button></p>
        <?php
    }

    private static function mode_option(string $value, string $current, string $icon, string $title, string $description): void
    {
        ?>
        <label class="rbsr-mode-option"><input type="radio" name="mode" value="<?php echo esc_attr($value); ?>" <?php checked($current, $value); ?>><span class="rbsr-mode-icon"><?php echo esc_html($icon); ?></span><strong><?php echo esc_html($title); ?></strong><small><?php echo esc_html($description); ?></small></label>
        <?php
    }

    private static function station_setting_row(int $index, string $key, string $label, array $settings, string $type): void
    {
        $name = 'station_' . $index . '_' . $key;
        $value = (string) ($settings[$name] ?? '');
        $class = $type === 'url' ? 'large-text' : 'regular-text';
        ?>
        <tr><th><label for="rbsr-<?php echo esc_attr($name); ?>"><?php echo esc_html($label); ?></label></th><td><input type="<?php echo esc_attr($type); ?>" class="<?php echo esc_attr($class); ?>" id="rbsr-<?php echo esc_attr($name); ?>" name="<?php echo esc_attr(RBSR_Core::OPTION); ?>[<?php echo esc_attr($name); ?>]" value="<?php echo esc_attr($value); ?>"></td></tr>
        <?php
    }

    private static function shortcodes_panel(): void
    {
        $stations = RBSR_Core::stations();
        if ($stations === []) {
            return;
        }
        ?>
        <section class="rbsr-shortcodes-panel">
            <h2><?php esc_html_e('Shortcodes', 'radioboss-song-ratings'); ?></h2>
            <p><?php esc_html_e('These shortcodes remain available here after the setup wizard is complete.', 'radioboss-song-ratings'); ?></p>
            <?php foreach ($stations as $slug => $station): ?>
                <div class="rbsr-shortcode-station">
                    <h3><?php echo esc_html($station['name']); ?> <code><?php echo esc_html($slug); ?></code></h3>
                    <?php self::shortcode_row(
                        __('Rating widget with metadata API', 'radioboss-song-ratings'),
                        '[radioboss_song_ratings station="' . $slug . '"]'
                    ); ?>
                    <?php self::shortcode_row(
                        __('Rating widget for an existing player', 'radioboss-song-ratings'),
                        '[radioboss_song_ratings station="' . $slug . '" source="external"]'
                    ); ?>
                    <?php self::shortcode_row(
                        __('Optional demo player with ratings', 'radioboss-song-ratings'),
                        '[radioboss_rating_player station="' . $slug . '"]'
                    ); ?>
                </div>
            <?php endforeach; ?>
        </section>
        <?php
    }

    private static function shortcode_row(string $label, string $shortcode): void
    {
        ?>
        <label class="rbsr-shortcode-row">
            <span><?php echo esc_html($label); ?></span>
            <input type="text" class="large-text code" value="<?php echo esc_attr($shortcode); ?>" readonly onclick="this.select();">
        </label>
        <?php
    }

    private static function stat_card(string $label, int $value, string $icon): void
    {
        ?><div class="rbsr-stat"><span><?php echo esc_html($icon); ?></span><strong><?php echo number_format_i18n($value); ?></strong><small><?php echo esc_html($label); ?></small></div><?php
    }

    private static function sort_link(string $label, string $column, string $current, string $order): void
    {
        $next = $current === $column && $order === 'ASC' ? 'desc' : 'asc';
        $arrow = $current === $column ? ($order === 'ASC' ? ' ▲' : ' ▼') : '';
        $url = add_query_arg(['sort' => $column, 'order' => $next, 'paged' => 1]);
        ?><a href="<?php echo esc_url($url); ?>"><?php echo esc_html($label . $arrow); ?></a><?php
    }

    private static function rating_icon(string $rating): string
    {
        return $rating === 'love' ? '❤️ Love it' : ($rating === 'dislike' ? '👎 Dislike' : "😐 It's okay");
    }
}
