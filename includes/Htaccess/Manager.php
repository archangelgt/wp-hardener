<?php
/**
 * .htaccess hardening block manager.
 *
 * @package Seraph\WPHardening
 */

declare(strict_types=1);

namespace Seraph\WPHardening\Htaccess;

final class Manager {
	private const BEGIN = '# BEGIN Seraph WP Hardening';
	private const END   = '# END Seraph WP Hardening';

	public static function is_apache(): bool {
		$soft = strtolower((string) ($_SERVER['SERVER_SOFTWARE'] ?? ''));
		return str_contains($soft, 'apache') || str_contains($soft, 'litespeed') || file_exists(ABSPATH . '.htaccess');
	}

	public static function rules_block(): string {
		$uploads = wp_upload_dir();
		$rel     = '';
		if (! empty($uploads['basedir']) && str_starts_with($uploads['basedir'], ABSPATH)) {
			$rel = trim(str_replace('\\', '/', substr($uploads['basedir'], strlen(ABSPATH))), '/');
		}

		$lines = array(
			self::BEGIN,
			'Options -Indexes',
			'<Files wp-config.php>',
			'  Require all denied',
			'</Files>',
			'<FilesMatch "^(\\.(htaccess|htpasswd)|readme\\.html|license\\.txt)$">',
			'  Require all denied',
			'</FilesMatch>',
			'<IfModule mod_rewrite.c>',
			'  RewriteEngine On',
			'  RewriteCond %{REQUEST_METHOD} ^(TRACE|TRACK)',
			'  RewriteRule .* - [F]',
			'</IfModule>',
			'<IfModule mod_headers.c>',
			'  Header always set X-Content-Type-Options "nosniff"',
			'  Header always set X-Frame-Options "SAMEORIGIN"',
			'  Header always set Referrer-Policy "strict-origin-when-cross-origin"',
			'  Header always set Permissions-Policy "geolocation=(), microphone=(), camera=()"',
			'</IfModule>',
		);

		if ($rel !== '') {
			$lines[] = '<IfModule mod_rewrite.c>';
			$lines[] = '  RewriteRule ^' . preg_quote($rel, '/') . '/.*\\.(?:php[0-9]?|phtml)$ - [F,L]';
			$lines[] = '</IfModule>';
		}

		$lines[] = self::END;
		return implode("\n", $lines) . "\n";
	}

	public static function apply_hardening_block(): bool|string {
		$path = ABSPATH . '.htaccess';
		if (! self::is_apache()) {
			return __('Server does not appear to use Apache/.htaccess; rule skipped.', 'wp-hardening');
		}
		if (! file_exists($path) && ! is_writable(ABSPATH)) {
			return __('Cannot create .htaccess.', 'wp-hardening');
		}

		$current = file_exists($path) ? (string) file_get_contents($path) : '';
		$block   = self::rules_block();

		if (str_contains($current, self::BEGIN)) {
			$updated = preg_replace(
				'/' . preg_quote(self::BEGIN, '/') . '.*?' . preg_quote(self::END, '/') . '\s*/s',
				$block,
				$current
			);
			$current = is_string($updated) ? $updated : $current;
		} else {
			$current = rtrim($current) . "\n\n" . $block;
		}

		$written = file_put_contents($path, $current); // phpcs:ignore
		return $written !== false ? true : __('Failed writing .htaccess.', 'wp-hardening');
	}

	public static function remove_hardening_block(): bool|string {
		$path = ABSPATH . '.htaccess';
		if (! file_exists($path)) {
			return true;
		}
		$current = (string) file_get_contents($path);
		$updated = preg_replace(
			'/' . preg_quote(self::BEGIN, '/') . '.*?' . preg_quote(self::END, '/') . '\s*/s',
			'',
			$current
		);
		if (! is_string($updated)) {
			return __('Failed updating .htaccess.', 'wp-hardening');
		}
		return file_put_contents($path, $updated) !== false ? true : __('Failed writing .htaccess.', 'wp-hardening'); // phpcs:ignore
	}
}
