<?php
/**
 * Plugin Name:       Seraph Systems WP Hardening
 * Plugin URI:        https://seraphsystems.com/
 * Description:       Professional WordPress hardening — reduce attack surface with secure defaults, reversible changes, and administrator control. No malware scanner, no cloud, no telemetry.
 * Version:           1.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.2
 * Author:            Seraph Systems
 * Author URI:        https://seraphsystems.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-hardening
 * Domain Path:       /languages
 *
 * @package Seraph\WPHardening
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	exit;
}

define('SERAPH_WPH_VERSION', '1.1.0');
define('SERAPH_WPH_FILE', __FILE__);
define('SERAPH_WPH_PATH', plugin_dir_path(__FILE__));
define('SERAPH_WPH_URL', plugin_dir_url(__FILE__));
define('SERAPH_WPH_BASENAME', plugin_basename(__FILE__));

require_once SERAPH_WPH_PATH . 'includes/Autoloader.php';

Seraph\WPHardening\Autoloader::register(SERAPH_WPH_PATH . 'includes/');

register_activation_hook(__FILE__, array(Seraph\WPHardening\Plugin::class, 'activate'));
register_deactivation_hook(__FILE__, array(Seraph\WPHardening\Plugin::class, 'deactivate'));

add_action(
	'plugins_loaded',
	static function (): void {
		Seraph\WPHardening\Plugin::instance()->boot();
	}
);
