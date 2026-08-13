<?php
/**
 * Plugin Name: RadioBOSS Song Ratings
 * Description: Adds listener song ratings, a neutral demo player, and a WordPress ratings dashboard for one or more radio stations.
 * Version: 1.1.1
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: Radio Music Tools Community
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: radioboss-song-ratings
 */

if (!defined('ABSPATH')) {
    exit;
}

define('RBSR_VERSION', '1.1.1');
define('RBSR_FILE', __FILE__);
define('RBSR_DIR', plugin_dir_path(__FILE__));
define('RBSR_URL', plugin_dir_url(__FILE__));

require_once RBSR_DIR . 'includes/class-rbsr-core.php';
require_once RBSR_DIR . 'includes/class-rbsr-rest.php';
require_once RBSR_DIR . 'includes/class-rbsr-shortcodes.php';
require_once RBSR_DIR . 'includes/class-rbsr-admin.php';

register_activation_hook(__FILE__, ['RBSR_Core', 'activate']);
RBSR_Core::boot();
