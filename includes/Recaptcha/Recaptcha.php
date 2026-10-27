<?php
/**
 * Google reCAPTCHA integration (login / register / lostpassword / CF7 hook point).
 *
 * @package Seraph\WPHardening
 */

declare(strict_types=1);

namespace Seraph\WPHardening\Recaptcha;

use Seraph\WPHardening\Helpers\Options;

final class Recaptcha {
	public static function register(): void {
		$settings = Options::settings();
		if (empty($settings['recaptcha_enabled']) || empty($settings['recaptcha_site_key']) || empty($settings['recaptcha_secret'])) {
			return;
		}

		add_action('login_enqueue_scripts', array(self::class, 'enqueue'));
		add_action('login_form', array(self::class, 'render'));
		add_action('register_form', array(self::class, 'render'));
		add_action('lostpassword_form', array(self::class, 'render'));
		add_filter('wp_authenticate_user', array(self::class, 'verify_login'), 20, 2);
		add_action('wpcf7_init', array(self::class, 'cf7_support'));
	}

	public static function enqueue(): void {
		$settings = Options::settings();
		$ver      = (string) $settings['recaptcha_version'];
		$url      = $ver === 'v3'
			? 'https://www.google.com/recaptcha/api.js?render=' . rawurlencode((string) $settings['recaptcha_site_key'])
			: 'https://www.google.com/recaptcha/api.js';
		wp_enqueue_script('seraph-wph-recaptcha', $url, array(), null, true);
		if ($ver === 'v3') {
			wp_add_inline_script(
				'seraph-wph-recaptcha',
				'grecaptcha.ready(function(){grecaptcha.execute(' . wp_json_encode($settings['recaptcha_site_key']) . ',{action:"login"}).then(function(t){var i=document.getElementById("seraph_wph_recaptcha"); if(i){i.value=t;}});});'
			);
		}
	}

	public static function render(): void {
		$settings = Options::settings();
		$ver      = (string) $settings['recaptcha_version'];
		echo '<p class="seraph-wph-recaptcha">';
		if ($ver === 'v3') {
			echo '<input type="hidden" name="g-recaptcha-response" id="seraph_wph_recaptcha" value="" />';
		} else {
			printf(
				'<div class="g-recaptcha" data-sitekey="%s"%s></div>',
				esc_attr((string) $settings['recaptcha_site_key']),
				$ver === 'invisible' ? ' data-size="invisible"' : ''
			);
		}
		echo '</p>';
	}

	/**
	 * @param \WP_User|\WP_Error $user User.
	 * @return \WP_User|\WP_Error
	 */
	public static function verify_login($user, string $password) { // phpcs:ignore
		if (is_wp_error($user)) {
			return $user;
		}
		$token = isset($_POST['g-recaptcha-response']) ? sanitize_text_field(wp_unslash((string) $_POST['g-recaptcha-response'])) : ''; // phpcs:ignore
		if (! self::verify($token)) {
			return new \WP_Error('seraph_wph_recaptcha', __('reCAPTCHA verification failed.', 'wp-hardening'));
		}
		return $user;
	}

	public static function verify(string $token): bool {
		if ($token === '') {
			return false;
		}
		$settings = Options::settings();
		$response = wp_remote_post(
			'https://www.google.com/recaptcha/api/siteverify',
			array(
				'timeout' => 8,
				'body'    => array(
					'secret'   => (string) $settings['recaptcha_secret'],
					'response' => $token,
					'remoteip' => sanitize_text_field((string) ($_SERVER['REMOTE_ADDR'] ?? '')),
				),
			)
		);
		if (is_wp_error($response)) {
			return false;
		}
		$data = json_decode((string) wp_remote_retrieve_body($response), true);
		return is_array($data) && ! empty($data['success']);
	}

	public static function cf7_support(): void {
		// Marker for future CF7 tag; verification can be attached via wpcf7_validate.
		add_filter(
			'wpcf7_form_elements',
			static function (string $content): string {
				ob_start();
				self::render();
				return $content . (string) ob_get_clean();
			}
		);
	}
}
