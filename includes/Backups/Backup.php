<?php
/**
 * Timestamped backups for .htaccess / wp-config.php.
 *
 * @package Seraph\WPHardening
 */

declare(strict_types=1);

namespace Seraph\WPHardening\Backups;

final class Backup {
	public static function dir(): string {
		$upload = wp_upload_dir();
		$dir    = trailingslashit($upload['basedir']) . 'seraph-wph-backups';
		if (! is_dir($dir)) {
			wp_mkdir_p($dir);
			file_put_contents($dir . '/index.php', "<?php\n// Silence is golden.\n"); // phpcs:ignore
			file_put_contents($dir . '/.htaccess', "Deny from all\n"); // phpcs:ignore
		}
		return $dir;
	}

	/**
	 * @return string|WP_Error Backup path.
	 */
	public static function backup_file(string $absolute_path, string $label) {
		if (! is_readable($absolute_path)) {
			return new \WP_Error('seraph_wph_missing', sprintf(/* translators: %s path */ __('File not readable: %s', 'wp-hardening'), $absolute_path));
		}

		$stamp = gmdate('Ymd-His');
		$dest  = self::dir() . '/' . sanitize_file_name($label) . '-' . $stamp . '.bak';
		$ok    = copy($absolute_path, $dest);
		if (! $ok) {
			return new \WP_Error('seraph_wph_backup_fail', __('Could not create backup.', 'wp-hardening'));
		}

		return $dest;
	}

	/**
	 * @return array<int, array{file:string,mtime:int,size:int}>
	 */
	public static function list_backups(): array {
		$files = glob(self::dir() . '/*.bak') ?: array();
		$out   = array();
		foreach ($files as $file) {
			$out[] = array(
				'file'  => basename($file),
				'mtime' => (int) filemtime($file),
				'size'  => (int) filesize($file),
			);
		}
		usort(
			$out,
			static fn(array $a, array $b): int => $b['mtime'] <=> $a['mtime']
		);
		return $out;
	}

	public static function restore(string $basename, string $target_absolute): bool|\WP_Error {
		$basename = basename($basename);
		$source   = self::dir() . '/' . $basename;
		if (! is_readable($source)) {
			return new \WP_Error('seraph_wph_restore_missing', __('Backup not found.', 'wp-hardening'));
		}
		self::backup_file($target_absolute, 'pre-restore');
		$ok = copy($source, $target_absolute);
		return $ok ? true : new \WP_Error('seraph_wph_restore_fail', __('Restore failed.', 'wp-hardening'));
	}
}
