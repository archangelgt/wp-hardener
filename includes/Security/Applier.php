<?php
/**
 * Apply selected hardening rules with backup → apply → verify.
 *
 * @package Seraph\WPHardening
 */

declare(strict_types=1);

namespace Seraph\WPHardening\Security;

use Seraph\WPHardening\Backups\Backup;
use Seraph\WPHardening\Helpers\Options;
use Seraph\WPHardening\Htaccess\Manager as HtaccessManager;
use Seraph\WPHardening\WpConfig\Manager as WpConfigManager;

final class Applier {
	/**
	 * @param list<string> $rule_ids Rule IDs.
	 * @return array{ok:list<string>,fail:array<string,string>,backups:list<string>}
	 */
	public static function apply(array $rule_ids): array {
		$ok      = array();
		$fail    = array();
		$backups = array();
		$applied = Options::applied();
		$settings = Options::settings();
		$catalog = Rules::all();

		$needs_htaccess = array_intersect($rule_ids, array( 'protect_htaccess' ));
		$needs_wpconfig = array_intersect($rule_ids, array( 'disable_file_edit', 'force_ssl_admin' ));

		if ($needs_htaccess && file_exists(ABSPATH . '.htaccess')) {
			$bak = Backup::backup_file(ABSPATH . '.htaccess', 'htaccess');
			if (is_wp_error($bak)) {
				$fail['protect_htaccess'] = $bak->get_error_message();
			} else {
				$backups[] = (string) $bak;
			}
		}

		if ($needs_wpconfig && file_exists(ABSPATH . 'wp-config.php')) {
			$bak = Backup::backup_file(ABSPATH . 'wp-config.php', 'wp-config');
			if (is_wp_error($bak)) {
				$fail['disable_file_edit'] = $bak->get_error_message();
			} else {
				$backups[] = (string) $bak;
			}
		}

		foreach ($rule_ids as $id) {
			if (! isset($catalog[ $id ])) {
				$fail[ $id ] = __('Unknown rule.', 'wp-hardening');
				continue;
			}
			if (isset($fail[ $id ])) {
				continue;
			}

			$result = self::apply_one($id, $settings);
			if ($result === true) {
				$applied[ $id ] = true;
				$ok[]           = $id;
			} else {
				$fail[ $id ] = is_string($result) ? $result : __('Failed.', 'wp-hardening');
			}
		}

		Options::update_applied($applied);
		Options::update_settings($settings);
		Options::log('apply_rules', array( 'ok' => $ok, 'fail' => array_keys($fail) ));

		return compact('ok', 'fail', 'backups');
	}

	/**
	 * @param array<string, mixed> $settings Settings by ref.
	 */
	private static function apply_one(string $id, array &$settings): bool|string {
		switch ($id) {
			case 'disable_xmlrpc':
			case 'block_user_enum':
			case 'hide_wp_version':
			case 'disable_pingbacks':
			case 'remove_rsd_wlw':
			case 'disable_attachment_pages':
				return true;

			case 'hide_login_errors':
				$settings['hide_login_errors'] = true;
				return true;

			case 'login_rate_limit':
				$settings['login_rate_limit'] = true;
				return true;

			case 'disable_comments':
				$settings['comments_disabled'] = true;
				update_option('default_comment_status', 'closed');
				update_option('default_ping_status', 'closed');
				return true;

			case 'disable_file_edit':
				return WpConfigManager::ensure_constant('DISALLOW_FILE_EDIT', 'true');

			case 'force_ssl_admin':
				if (! is_ssl() && ! str_starts_with((string) home_url(), 'https://')) {
					return __('Site is not on HTTPS; FORCE_SSL_ADMIN skipped.', 'wp-hardening');
				}
				return WpConfigManager::ensure_constant('FORCE_SSL_ADMIN', 'true');

			case 'protect_htaccess':
				return HtaccessManager::apply_hardening_block();

			default:
				return __('Unsupported rule.', 'wp-hardening');
		}
	}
}
