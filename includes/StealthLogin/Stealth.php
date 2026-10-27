<?php
/**
 * Stealth login endpoint.
 *
 * Recovery: define('SERAPH_DISABLE_LOGIN_PROTECTION', true);
 * or create wp-content/seraph-wph-disable-login.lock
 *
 * @package Seraph\WPHardening
 */

declare(strict_types=1);

namespace Seraph\WPHardening\StealthLogin;

use Seraph\WPHardening\Helpers\Options;

final class Stealth {
	public static function register(): void {
		if (self::disabled()) {
			return;
		}

		$settings = Options::settings();
		if (empty($settings['stealth_enabled'])) {
			return;
		}

		LoginUI::register();

		add_action('init', array(self::class, 'add_rewrite'));
		add_action('login_init', array(self::class, 'maybe_block_wp_login'), 1);
		add_filter('site_url', array(self::class, 'filter_login_url'), 10, 3);
		add_filter('network_site_url', array(self::class, 'filter_login_url'), 10, 3);
		add_filter('wp_redirect', array(self::class, 'filter_redirect'));
		add_action('template_redirect', array(self::class, 'handle_custom_slug'));
	}

	public static function disabled(): bool {
		if (defined('SERAPH_DISABLE_LOGIN_PROTECTION') && SERAPH_DISABLE_LOGIN_PROTECTION) {
			return true;
		}
		return file_exists(WP_CONTENT_DIR . '/seraph-wph-disable-login.lock');
	}

	public static function slug(): string {
		$settings = Options::settings();
		$slug     = sanitize_title((string) ($settings['stealth_slug'] ?? 'secure-login'));
		return $slug !== '' ? $slug : 'secure-login';
	}

	public static function add_rewrite(): void {
		add_rewrite_rule('^' . preg_quote(self::slug(), '/') . '/?$', 'index.php?seraph_wph_login=1', 'top');
		add_rewrite_tag('%seraph_wph_login%', '1');
	}

	public static function handle_custom_slug(): void {
		if ((int) get_query_var('seraph_wph_login') !== 1) {
			$path = (string) (wp_parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '');
			$path = trim($path, '/');
			$home = trim((string) (wp_parse_url(home_url('/'), PHP_URL_PATH) ?: ''), '/');
			if ($home !== '' && str_starts_with($path, $home . '/')) {
				$path = substr($path, strlen($home) + 1);
			}
			if ($path !== self::slug()) {
				return;
			}
		}
		// Allow wp-login.php bootstrap from the stealth URL without 404/403.
		if (! defined('SERAPH_WPH_STEALTH_BOOT')) {
			define('SERAPH_WPH_STEALTH_BOOT', true);
		}
		status_header(200);
		nocache_headers();
		require ABSPATH . 'wp-login.php';
		exit;
	}

	public static function maybe_block_wp_login(): void {
		if (defined('SERAPH_WPH_STEALTH_BOOT') && SERAPH_WPH_STEALTH_BOOT) {
			return;
		}
		if (self::request_is_stealth()) {
			return;
		}

		// Allow logout / post auth flows with nonce cookies from same session.
		$action = isset($_REQUEST['action']) ? sanitize_key((string) $_REQUEST['action']) : ''; // phpcs:ignore
		if (in_array($action, array( 'logout', 'postpass' ), true)) {
			return;
		}

		$mode = (string) (Options::settings()['stealth_block_mode'] ?? '404');
		if ($mode === 'home') {
			wp_safe_redirect(home_url('/'));
			exit;
		}
		if ($mode === '403') {
			status_header(403);
			nocache_headers();
			wp_die(esc_html__('Forbidden', 'wp-hardening'), 403);
		}
		global $wp_query;
		$wp_query->set_404();
		status_header(404);
		nocache_headers();
		$template = get_404_template();
		if ($template) {
			include $template;
		}
		exit;
	}

	/**
	 * @param string      $url URL.
	 * @param string      $path Path.
	 * @param string|null $scheme Scheme.
	 */
	public static function filter_login_url(string $url, string $path, $scheme = null): string { // phpcs:ignore
		if (str_contains($url, 'wp-login.php') && ! self::disabled()) {
			$url = home_url('/' . self::slug() . '/');
		}
		return $url;
	}

	public static function filter_redirect(string $location): string {
		if (str_contains($location, 'wp-login.php') && ! self::disabled()) {
			$location = str_replace('wp-login.php', self::slug() . '/', $location);
			$location = str_replace('//' . self::slug(), '/' . self::slug(), $location);
		}
		return $location;
	}

	private static function request_is_stealth(): bool {
		$uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
		return str_contains($uri, '/' . self::slug());
	}
}
