<?php
/**
 * Security scanner.
 *
 * @package Seraph\WPHardening
 */

declare(strict_types=1);

namespace Seraph\WPHardening\Scanner;

use Seraph\WPHardening\Helpers\Options;
use Seraph\WPHardening\Security\Rules;

final class Scanner {
	/**
	 * @return array{
	 *   checks: list<array<string,mixed>>,
	 *   score: int,
	 *   grade: string,
	 *   server: array<string,mixed>,
	 *   php: array<string,mixed>,
	 *   wordpress: array<string,mixed>
	 * }
	 */
	public static function run(): array {
		$checks = array();
		$applied = Options::applied();
		$settings = Options::settings();

		$checks[] = self::check(
			'https',
			__('HTTPS', 'wp-hardening'),
			is_ssl() || str_starts_with((string) home_url(), 'https://'),
			__('Site should be served over HTTPS.', 'wp-hardening'),
			6
		);

		$checks[] = self::check(
			'debug_off',
			__('WP_DEBUG off in production', 'wp-hardening'),
			! (defined('WP_DEBUG') && WP_DEBUG),
			__('Debug mode should be disabled on production sites.', 'wp-hardening'),
			5
		);

		$checks[] = self::check(
			'xmlrpc',
			__('XML-RPC disabled', 'wp-hardening'),
			! empty($applied['disable_xmlrpc']),
			__('XML-RPC increases attack surface.', 'wp-hardening'),
			4
		);

		$checks[] = self::check(
			'file_edit',
			__('File editor disabled', 'wp-hardening'),
			defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT,
			__('Theme/plugin file editor should be disabled.', 'wp-hardening'),
			5
		);

		$checks[] = self::check(
			'user_enum',
			__('User enumeration blocked', 'wp-hardening'),
			! empty($applied['block_user_enum']) || ! empty($settings['block_user_enum']),
			__('Author probes reveal usernames.', 'wp-hardening'),
			4
		);

		$checks[] = self::check(
			'readme',
			__('readme.html removed/blocked', 'wp-hardening'),
			! file_exists(ABSPATH . 'readme.html') || ! empty($applied['protect_htaccess']),
			__('readme.html discloses WordPress version.', 'wp-hardening'),
			2
		);

		$checks[] = self::check(
			'wpconfig_perms',
			__('wp-config.php permissions', 'wp-hardening'),
			self::perm_ok(ABSPATH . 'wp-config.php', array( 0400, 0440, 0600, 0640 )),
			__('Prefer 400/440/600 for wp-config.php.', 'wp-hardening'),
			6
		);

		$checks[] = self::check(
			'uploads_php',
			__('PHP execution blocked in uploads (recommended)', 'wp-hardening'),
			! empty($applied['protect_htaccess']),
			__('Uploads directory should not execute PHP.', 'wp-hardening'),
			6
		);

		$checks[] = self::check(
			'login_errors',
			__('Login errors hidden', 'wp-hardening'),
			! empty($applied['hide_login_errors']) || ! empty($settings['hide_login_errors']),
			__('Generic login errors reduce username discovery.', 'wp-hardening'),
			3
		);

		$checks[] = self::check(
			'rate_limit',
			__('Login rate limiting', 'wp-hardening'),
			! empty($applied['login_rate_limit']) || ! empty($settings['login_rate_limit']),
			__('Rate limiting slows brute-force attacks.', 'wp-hardening'),
			5
		);

		$php_ok = version_compare(PHP_VERSION, '8.2', '>=');
		$checks[] = self::check(
			'php_version',
			__('PHP 8.2+', 'wp-hardening'),
			$php_ok,
			sprintf(/* translators: %s version */ __('Current PHP: %s', 'wp-hardening'), PHP_VERSION),
			4
		);

		$expose = (string) ini_get('expose_php');
		$checks[] = self::check(
			'expose_php',
			__('expose_php disabled', 'wp-hardening'),
			$expose === '0' || $expose === '' || strtolower($expose) === 'off',
			__('expose_php reveals PHP version in headers.', 'wp-hardening'),
			2
		);

		$display = (string) ini_get('display_errors');
		$checks[] = self::check(
			'display_errors',
			__('display_errors off', 'wp-hardening'),
			$display === '0' || $display === '' || strtolower($display) === 'off',
			__('Errors should not be displayed publicly.', 'wp-hardening'),
			3
		);

		$score = self::score_from_checks($checks);
		return array(
			'checks'    => $checks,
			'score'     => $score,
			'grade'     => self::grade($score),
			'server'    => self::server_info(),
			'php'       => self::php_info(),
			'wordpress' => self::wp_info(),
			'rules'     => Rules::all(),
		);
	}

	/**
	 * @param list<array<string,mixed>> $checks Checks.
	 */
	private static function score_from_checks(array $checks): int {
		$total = 0;
		$got   = 0;
		foreach ($checks as $check) {
			$points = (int) ($check['points'] ?? 0);
			$total += $points;
			if (! empty($check['pass'])) {
				$got += $points;
			}
		}
		if ($total <= 0) {
			return 0;
		}
		return (int) round(($got / $total) * 100);
	}

	public static function grade(int $score): string {
		if ($score >= 95) {
			return 'excellent';
		}
		if ($score >= 85) {
			return 'good';
		}
		if ($score >= 70) {
			return 'fair';
		}
		if ($score >= 50) {
			return 'poor';
		}
		return 'critical';
	}

	/**
	 * @param list<int> $allowed Allowed modes.
	 */
	private static function perm_ok(string $path, array $allowed): bool {
		if (! file_exists($path)) {
			return false;
		}
		$perms = fileperms($path) & 0777;
		return in_array($perms, $allowed, true);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function check(string $id, string $label, bool $pass, string $detail, int $points): array {
		return compact('id', 'label', 'pass', 'detail', 'points');
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function server_info(): array {
		return array(
			'software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
			'https'    => is_ssl(),
			'abspath'  => ABSPATH,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function php_info(): array {
		return array(
			'version'                 => PHP_VERSION,
			'memory_limit'            => ini_get('memory_limit'),
			'expose_php'              => ini_get('expose_php'),
			'display_errors'          => ini_get('display_errors'),
			'allow_url_include'       => ini_get('allow_url_include'),
			'session.cookie_httponly' => ini_get('session.cookie_httponly'),
			'session.cookie_secure'   => ini_get('session.cookie_secure'),
			'session.use_strict_mode' => ini_get('session.use_strict_mode'),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function wp_info(): array {
		global $wp_version;
		return array(
			'version'   => $wp_version,
			'locale'    => get_locale(),
			'multisite' => is_multisite(),
			'debug'     => defined('WP_DEBUG') && WP_DEBUG,
		);
	}
}
