<?php
/**
 * Branded secure login UI (stealth endpoint).
 *
 * @package Seraph\WPHardening
 */

declare(strict_types=1);

namespace Seraph\WPHardening\StealthLogin;

final class LoginUI {
	public static function register(): void {
		add_action('login_enqueue_scripts', array(self::class, 'assets'), 1);
		add_filter('login_body_class', array(self::class, 'body_class'));
		add_filter('login_headerurl', static fn(): string => home_url('/'));
		add_filter('login_headertext', static fn(): string => get_bloginfo('name'));
		add_filter('login_title', array(self::class, 'title'), 10, 2);
		add_action('login_header', array(self::class, 'stage_open'), 1);
		add_action('login_footer', array(self::class, 'stage_close'), 20);
		add_filter('login_message', array(self::class, 'message'));
	}

	/**
	 * @param array<int, string> $classes Classes.
	 * @return array<int, string>
	 */
	public static function body_class(array $classes): array {
		$classes[] = 'seraph-secure-login';
		return $classes;
	}

	public static function assets(): void {
		wp_enqueue_style(
			'seraph-wph-fonts',
			'https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Serif:wght@500;600;700&display=swap',
			array(),
			null
		);
		wp_enqueue_style(
			'seraph-wph-login',
			SERAPH_WPH_URL . 'assets/css/login.css',
			array('seraph-wph-fonts'),
			SERAPH_WPH_VERSION
		);
		wp_enqueue_script(
			'seraph-wph-login',
			SERAPH_WPH_URL . 'assets/js/login.js',
			array(),
			SERAPH_WPH_VERSION,
			true
		);

		$logo = self::logo_url();
		wp_add_inline_style(
			'seraph-wph-login',
			sprintf(
				':root{--seraph-login-logo:url("%s");}',
				esc_url($logo)
			)
		);
	}

	public static function logo_url(): string {
		$theme = get_theme_file_uri('assets/images/seraph-logo-white.png');
		if (is_string($theme) && $theme !== '') {
			$path = get_theme_file_path('assets/images/seraph-logo-white.png');
			if (is_string($path) && is_readable($path)) {
				return $theme;
			}
		}
		$custom = get_site_icon_url(192);
		return is_string($custom) && $custom !== '' ? $custom : home_url('/');
	}

	public static function stage_open(): void {
		echo '<div class="seraph-login-shell" data-seraph-login>';
		echo '<div class="seraph-login-stage" aria-hidden="true">';
		echo '<div class="seraph-login-stage__glow seraph-login-stage__glow--a"></div>';
		echo '<div class="seraph-login-stage__glow seraph-login-stage__glow--b"></div>';
		echo '<div class="seraph-login-stage__grid"></div>';
		echo '<div class="seraph-login-stage__orbit"></div>';
		echo '<div class="seraph-login-stage__mark"></div>';
		echo '</div>';
		echo '<div class="seraph-login-layout">';
		echo '<aside class="seraph-login-brand">';
		echo '<p class="seraph-login-brand__eyebrow">Seraph Systems</p>';
		echo '<p class="seraph-login-brand__title">' . esc_html__('Centro de control', 'wp-hardening') . '</p>';
		echo '<p class="seraph-login-brand__copy">' . esc_html__('Consola protegida para operadores. Autentícate para administrar ERP, CRM y operaciones digitales.', 'wp-hardening') . '</p>';
		echo '<ul class="seraph-login-brand__meta">';
		echo '<li>' . esc_html__('Endpoint reforzado', 'wp-hardening') . '</li>';
		echo '<li>' . esc_html__('Sesión cifrada', 'wp-hardening') . '</li>';
		echo '<li>' . esc_html__('Solo personal autorizado', 'wp-hardening') . '</li>';
		echo '</ul>';
		echo '</aside>';
		echo '<div class="seraph-login-panel">';
	}

	public static function stage_close(): void {
		echo '<p class="seraph-login-foot">';
		echo esc_html__('Solo personal autorizado. Los intentos de acceso pueden registrarse.', 'wp-hardening');
		echo ' · <a href="' . esc_url(home_url('/')) . '">' . esc_html__('Volver al sitio', 'wp-hardening') . '</a>';
		echo '</p>';
		echo '</div></div></div>';
	}

	public static function message(string $message): string {
		if ($message !== '') {
			return $message;
		}
		return '<p class="seraph-login-welcome">' . esc_html__('Inicia sesión para continuar', 'wp-hardening') . '</p>';
	}

	public static function title(string $title, string $action): string { // phpcs:ignore
		return sprintf(
			/* translators: %s: site name */
			__('Acceso seguro · %s', 'wp-hardening'),
			get_bloginfo('name')
		);
	}
}
