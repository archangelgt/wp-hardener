<?php
/**
 * Main plugin bootstrap.
 *
 * @package Seraph\WPHardening
 */

declare(strict_types=1);

namespace Seraph\WPHardening;

use Seraph\WPHardening\Admin\Menu;
use Seraph\WPHardening\Comments\Hardening as CommentsHardening;
use Seraph\WPHardening\Helpers\Options;
use Seraph\WPHardening\Installer\Wizard;
use Seraph\WPHardening\Login\Security as LoginSecurity;
use Seraph\WPHardening\Recaptcha\Recaptcha;
use Seraph\WPHardening\Security\Runtime;
use Seraph\WPHardening\StealthLogin\Stealth;

final class Plugin {
	private static ?self $instance = null;

	public static function instance(): self {
		return self::$instance ??= new self();
	}

	public static function activate(): void {
		if (! get_option(Options::SETTINGS)) {
			Options::update_settings(Options::settings());
		}
		if (! get_option(Options::APPLIED)) {
			Options::update_applied(array());
		}
		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		flush_rewrite_rules();
	}

	public function boot(): void {
		load_plugin_textdomain('wp-hardening', false, dirname(SERAPH_WPH_BASENAME) . '/languages');

		Runtime::register();
		LoginSecurity::register();
		Stealth::register();
		CommentsHardening::register();
		Recaptcha::register();

		if (is_admin()) {
			Menu::register();
			Wizard::register();
		}
	}
}
