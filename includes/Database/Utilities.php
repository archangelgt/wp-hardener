<?php
/**
 * Lightweight DB utilities.
 *
 * @package Seraph\WPHardening
 */

declare(strict_types=1);

namespace Seraph\WPHardening\Database;

final class Utilities {
	/**
	 * @return array<string, mixed>
	 */
	public static function info(): array {
		global $wpdb;
		return array(
			'prefix'    => $wpdb->prefix,
			'charset'   => $wpdb->charset,
			'collate'   => $wpdb->collate,
			'db_version'=> get_option('db_version'),
		);
	}

	/**
	 * @return array{optimized:int,errors:list<string>}
	 */
	public static function optimize_tables(): array {
		global $wpdb;
		$tables = $wpdb->get_col('SHOW TABLES');
		$ok     = 0;
		$errors = array();
		if (! is_array($tables)) {
			return array( 'optimized' => 0, 'errors' => array( 'No tables.' ) );
		}
		foreach ($tables as $table) {
			$table = (string) $table;
			$res   = $wpdb->query("OPTIMIZE TABLE `{$table}`"); // phpcs:ignore
			if ($res === false) {
				$errors[] = $table;
			} else {
				++$ok;
			}
		}
		return array( 'optimized' => $ok, 'errors' => $errors );
	}
}
