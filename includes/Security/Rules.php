<?php
/**
 * Hardening rule catalog.
 *
 * @package Seraph\WPHardening
 */

declare(strict_types=1);

namespace Seraph\WPHardening\Security;

final class Rules {
	/**
	 * @return array<string, array<string, mixed>>
	 */
	public static function all(): array {
		return array(
			'disable_xmlrpc'         => array(
				'label'       => __('Disable XML-RPC', 'wp-hardening'),
				'description' => __('Blocks XML-RPC interface commonly abused for brute force and amplification.', 'wp-hardening'),
				'points'      => 4,
				'profiles'    => array( 'standard', 'blog', 'woocommerce', 'custom' ),
				'group'       => 'wordpress',
			),
			'disable_file_edit'      => array(
				'label'       => __('Disable File Editor', 'wp-hardening'),
				'description' => __('Sets DISALLOW_FILE_EDIT so theme/plugin editors are unavailable in admin.', 'wp-hardening'),
				'points'      => 5,
				'profiles'    => array( 'standard', 'blog', 'woocommerce', 'custom' ),
				'group'       => 'wpconfig',
			),
			'block_user_enum'        => array(
				'label'       => __('Disable User Enumeration', 'wp-hardening'),
				'description' => __('Blocks ?author=1 style probes and REST user listing for anonymous users.', 'wp-hardening'),
				'points'      => 4,
				'profiles'    => array( 'standard', 'blog', 'woocommerce', 'custom' ),
				'group'       => 'wordpress',
			),
			'hide_wp_version'        => array(
				'label'       => __('Hide WordPress Version', 'wp-hardening'),
				'description' => __('Removes generator meta and version query strings from assets.', 'wp-hardening'),
				'points'      => 2,
				'profiles'    => array( 'standard', 'blog', 'woocommerce', 'custom' ),
				'group'       => 'wordpress',
			),
			'disable_pingbacks'      => array(
				'label'       => __('Disable Pingbacks', 'wp-hardening'),
				'description' => __('Turns off pingbacks / trackbacks for new content.', 'wp-hardening'),
				'points'      => 2,
				'profiles'    => array( 'standard', 'woocommerce', 'custom' ),
				'group'       => 'wordpress',
			),
			'remove_rsd_wlw'         => array(
				'label'       => __('Remove RSD & WLW Manifest', 'wp-hardening'),
				'description' => __('Removes Really Simple Discovery and Windows Live Writer discovery links.', 'wp-hardening'),
				'points'      => 1,
				'profiles'    => array( 'standard', 'blog', 'woocommerce', 'custom' ),
				'group'       => 'wordpress',
			),
			'disable_attachment_pages' => array(
				'label'       => __('Disable Attachment Pages', 'wp-hardening'),
				'description' => __('Redirects attachment pages to the file URL or parent post.', 'wp-hardening'),
				'points'      => 2,
				'profiles'    => array( 'standard', 'woocommerce', 'custom' ),
				'group'       => 'wordpress',
			),
			'protect_htaccess'       => array(
				'label'       => __('Protect critical files via .htaccess', 'wp-hardening'),
				'description' => __('Adds Apache rules: no directory listing, protect wp-config, block PHP in uploads, security headers.', 'wp-hardening'),
				'points'      => 8,
				'profiles'    => array( 'standard', 'blog', 'woocommerce', 'custom' ),
				'group'       => 'htaccess',
			),
			'force_ssl_admin'        => array(
				'label'       => __('Force SSL Admin', 'wp-hardening'),
				'description' => __('Sets FORCE_SSL_ADMIN when the site already runs on HTTPS.', 'wp-hardening'),
				'points'      => 3,
				'profiles'    => array( 'standard', 'woocommerce', 'custom' ),
				'group'       => 'wpconfig',
			),
			'disable_comments'       => array(
				'label'       => __('Disable Comments', 'wp-hardening'),
				'description' => __('Hardens comments: closes new comments, removes feeds and UI clutter.', 'wp-hardening'),
				'points'      => 5,
				'profiles'    => array( 'standard', 'woocommerce', 'custom' ),
				'group'       => 'comments',
			),
			'hide_login_errors'      => array(
				'label'       => __('Hide Login Errors', 'wp-hardening'),
				'description' => __('Generic authentication failure messages to avoid username discovery.', 'wp-hardening'),
				'points'      => 3,
				'profiles'    => array( 'standard', 'blog', 'woocommerce', 'custom' ),
				'group'       => 'login',
			),
			'login_rate_limit'       => array(
				'label'       => __('Login Rate Limiting', 'wp-hardening'),
				'description' => __('Temporary lockout after repeated failed logins.', 'wp-hardening'),
				'points'      => 5,
				'profiles'    => array( 'standard', 'blog', 'woocommerce', 'custom' ),
				'group'       => 'login',
			),
		);
	}

	/**
	 * @return list<string>
	 */
	public static function for_profile(string $profile): array {
		$ids = array();
		foreach (self::all() as $id => $rule) {
			$profiles = $rule['profiles'] ?? array();
			if ($profile === 'custom' || in_array($profile, $profiles, true)) {
				if ($profile === 'blog' && $id === 'disable_comments') {
					continue;
				}
				$ids[] = $id;
			}
		}
		return $ids;
	}
}
