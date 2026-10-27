<?php
/**
 * Runtime hardening effects based on applied rules / settings.
 *
 * @package Seraph\WPHardening
 */

declare(strict_types=1);

namespace Seraph\WPHardening\Security;

use Seraph\WPHardening\Helpers\Options;

final class Runtime {
	public static function register(): void {
		$applied  = Options::applied();
		$settings = Options::settings();

		if (! empty($applied['disable_xmlrpc']) || ! empty($settings['disable_xmlrpc'])) {
			add_filter('xmlrpc_enabled', '__return_false');
		}

		if (! empty($applied['block_user_enum']) || ! empty($settings['block_user_enum'])) {
			add_action('init', array(self::class, 'block_author_enum'), 1);
			add_filter('rest_endpoints', array(self::class, 'filter_rest_users'));
		}

		if (! empty($applied['hide_wp_version'])) {
			remove_action('wp_head', 'wp_generator');
			add_filter('the_generator', '__return_empty_string');
			add_filter('style_loader_src', array(self::class, 'strip_ver'), 9999);
			add_filter('script_loader_src', array(self::class, 'strip_ver'), 9999);
		}

		if (! empty($applied['remove_rsd_wlw'])) {
			remove_action('wp_head', 'rsd_link');
			remove_action('wp_head', 'wlwmanifest_link');
		}

		if (! empty($applied['disable_pingbacks'])) {
			add_filter('xmlrpc_methods', static function (array $methods): array {
				unset($methods['pingback.ping'], $methods['pingback.extensions.getPingbacks']);
				return $methods;
			});
			add_filter('pings_open', '__return_false', 20, 2);
		}

		if (! empty($applied['disable_attachment_pages'])) {
			add_action('template_redirect', array(self::class, 'redirect_attachments'));
		}
	}

	public static function block_author_enum(): void {
		if (is_admin() || is_user_logged_in()) {
			return;
		}
		if (isset($_GET['author'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_safe_redirect(home_url('/'), 301);
			exit;
		}
	}

	/**
	 * @param array<string, mixed> $endpoints Endpoints.
	 * @return array<string, mixed>
	 */
	public static function filter_rest_users(array $endpoints): array {
		if (! is_user_logged_in()) {
			unset($endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)']);
		}
		return $endpoints;
	}

	public static function strip_ver(string $src): string {
		if (str_contains($src, 'ver=')) {
			$src = remove_query_arg('ver', $src);
		}
		return $src;
	}

	public static function redirect_attachments(): void {
		if (! is_attachment()) {
			return;
		}
		$url = wp_get_attachment_url(get_queried_object_id());
		if (is_string($url) && $url !== '') {
			wp_safe_redirect($url, 301);
			exit;
		}
	}
}
