<?php
/**
 * Plugin options helper.
 *
 * @package Seraph\WPHardening
 */

declare(strict_types=1);

namespace Seraph\WPHardening\Helpers;

final class Options {
	public const SETTINGS = 'seraph_wph_settings';
	public const APPLIED  = 'seraph_wph_applied';
	public const WIZARD   = 'seraph_wph_wizard_done';
	public const PROFILE  = 'seraph_wph_profile';
	public const LOG      = 'seraph_wph_changelog';

	/**
	 * @return array<string, mixed>
	 */
	public static function settings(): array {
		$defaults = array(
			'stealth_enabled'     => false,
			'stealth_slug'        => 'secure-login',
			'stealth_block_mode'  => '404',
			'login_rate_limit'    => true,
			'login_max_attempts'  => 5,
			'login_lockout_mins'  => 15,
			'hide_login_errors'   => true,
			'block_user_enum'     => true,
			'comments_disabled'   => false,
			'recaptcha_enabled'   => false,
			'recaptcha_site_key'  => '',
			'recaptcha_secret'    => '',
			'recaptcha_version'   => 'v3',
			'htaccess_rules'      => array(),
		);

		$saved = get_option(self::SETTINGS, array());
		return is_array($saved) ? array_merge($defaults, $saved) : $defaults;
	}

	/**
	 * @param array<string, mixed> $settings Settings.
	 */
	public static function update_settings(array $settings): void {
		update_option(self::SETTINGS, $settings, false);
	}

	/**
	 * @return array<string, bool>
	 */
	public static function applied(): array {
		$applied = get_option(self::APPLIED, array());
		return is_array($applied) ? $applied : array();
	}

	/**
	 * @param array<string, bool> $applied Applied map.
	 */
	public static function update_applied(array $applied): void {
		update_option(self::APPLIED, $applied, false);
	}

	/**
	 * @param string               $action Action key.
	 * @param array<string, mixed> $meta   Meta.
	 */
	public static function log(string $action, array $meta = array()): void {
		$log   = get_option(self::LOG, array());
		$log   = is_array($log) ? $log : array();
		$log[] = array(
			'time'   => time(),
			'action' => $action,
			'user'   => get_current_user_id(),
			'meta'   => $meta,
		);
		if (count($log) > 100) {
			$log = array_slice($log, -100);
		}
		update_option(self::LOG, $log, false);
	}
}
