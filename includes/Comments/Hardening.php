<?php
/**
 * Comments hardening.
 *
 * @package Seraph\WPHardening
 */

declare(strict_types=1);

namespace Seraph\WPHardening\Comments;

use Seraph\WPHardening\Helpers\Options;

final class Hardening {
	public static function register(): void {
		$settings = Options::settings();
		$applied  = Options::applied();
		if (empty($settings['comments_disabled']) && empty($applied['disable_comments'])) {
			return;
		}

		add_filter('comments_open', '__return_false', 20, 2);
		add_filter('pings_open', '__return_false', 20, 2);
		add_filter('comments_array', '__return_empty_array', 10, 2);
		add_action('admin_menu', static function (): void {
			remove_menu_page('edit-comments.php');
		});
		add_action('admin_init', static function (): void {
			remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal');
		});
		add_action('init', static function (): void {
			if (is_admin_bar_showing()) {
				remove_action('admin_bar_menu', 'wp_admin_bar_comments_menu', 60);
			}
		});
		add_filter('feed_links_show_comments_feed', '__return_false');
		add_filter('rest_endpoints', static function (array $endpoints): array {
			unset($endpoints['/wp/v2/comments'], $endpoints['/wp/v2/comments/(?P<id>[\d]+)']);
			return $endpoints;
		});
	}
}
