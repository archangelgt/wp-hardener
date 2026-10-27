<?php
/**
 * Login security: hide errors + rate limiting.
 *
 * @package Seraph\WPHardening
 */

declare(strict_types=1);

namespace Seraph\WPHardening\Login;

use Seraph\WPHardening\Helpers\Options;

final class Security {
	public static function register(): void {
		$settings = Options::settings();
		$applied  = Options::applied();

		if (! empty($settings['hide_login_errors']) || ! empty($applied['hide_login_errors'])) {
			add_filter('login_errors', static fn(): string => __('Invalid credentials.', 'wp-hardening'));
		}

		if (! empty($settings['login_rate_limit']) || ! empty($applied['login_rate_limit'])) {
			add_filter('authenticate', array(self::class, 'check_lockout'), 30, 3);
			add_action('wp_login_failed', array(self::class, 'record_failure'));
			add_action('wp_login', array(self::class, 'clear_failures'), 10, 2);
		}
	}

	/**
	 * @param null|\WP_User|\WP_Error $user User.
	 * @return null|\WP_User|\WP_Error
	 */
	public static function check_lockout($user, string $username, string $password) {
		if ($username === '') {
			return $user;
		}
		$key = self::key($username);
		$data = get_transient($key);
		if (! is_array($data)) {
			return $user;
		}
		$settings = Options::settings();
		$max      = max(1, (int) $settings['login_max_attempts']);
		if ((int) ($data['count'] ?? 0) >= $max) {
			$mins = max(1, (int) $settings['login_lockout_mins']);
			return new \WP_Error(
				'seraph_wph_locked',
				sprintf(
					/* translators: %d minutes */
					__('Too many failed login attempts. Try again in %d minutes.', 'wp-hardening'),
					$mins
				)
			);
		}
		return $user;
	}

	public static function record_failure(string $username): void {
		$key      = self::key($username);
		$settings = Options::settings();
		$max      = max(1, (int) $settings['login_max_attempts']);
		$mins     = max(1, (int) $settings['login_lockout_mins']);
		$data     = get_transient($key);
		$count    = is_array($data) ? ((int) ($data['count'] ?? 0) + 1) : 1;
		set_transient($key, array( 'count' => $count ), $mins * MINUTE_IN_SECONDS);

		if ($count >= $max) {
			$admin = get_option('admin_email');
			if (is_string($admin) && is_email($admin)) {
				wp_mail(
					$admin,
					sprintf('[WP Hardening] %s', __('Login lockout triggered', 'wp-hardening')),
					sprintf("Username: %s\nIP: %s\n", $username, self::ip())
				);
			}
		}
	}

	public static function clear_failures(string $user_login, \WP_User $user): void { // phpcs:ignore
		delete_transient(self::key($user_login));
	}

	private static function key(string $username): string {
		return 'seraph_wph_login_' . md5(strtolower($username) . '|' . self::ip());
	}

	private static function ip(): string {
		return sanitize_text_field((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'));
	}
}
