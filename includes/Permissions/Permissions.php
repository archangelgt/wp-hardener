<?php
/**
 * Filesystem permissions audit helpers.
 *
 * @package Seraph\WPHardening
 */

declare(strict_types=1);

namespace Seraph\WPHardening\Permissions;

final class Permissions {
	/**
	 * @return list<array<string,mixed>>
	 */
	public static function audit(): array {
		$items = array(
			array( 'path' => ABSPATH, 'expect' => array( 0755 ), 'label' => 'ABSPATH' ),
			array( 'path' => ABSPATH . 'wp-config.php', 'expect' => array( 0400, 0440, 0600, 0640 ), 'label' => 'wp-config.php' ),
			array( 'path' => ABSPATH . '.htaccess', 'expect' => array( 0644, 0600 ), 'label' => '.htaccess' ),
			array( 'path' => WP_CONTENT_DIR, 'expect' => array( 0755 ), 'label' => 'wp-content' ),
			array( 'path' => WP_CONTENT_DIR . '/uploads', 'expect' => array( 0755 ), 'label' => 'uploads' ),
		);

		$out = array();
		foreach ($items as $item) {
			$path = $item['path'];
			$exists = file_exists($path);
			$perms  = $exists ? (fileperms($path) & 0777) : null;
			$out[]  = array(
				'label'  => $item['label'],
				'path'   => $path,
				'exists' => $exists,
				'perms'  => $perms !== null ? decoct($perms) : '—',
				'ok'     => $exists && in_array($perms, $item['expect'], true),
				'expect' => array_map(static fn(int $m): string => decoct($m), $item['expect']),
			);
		}
		return $out;
	}
}
