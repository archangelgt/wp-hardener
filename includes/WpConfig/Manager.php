<?php
/**
 * Cautious wp-config.php constant manager.
 *
 * @package Seraph\WPHardening
 */

declare(strict_types=1);

namespace Seraph\WPHardening\WpConfig;

final class Manager {
	private const BEGIN = '/* BEGIN Seraph WP Hardening */';
	private const END   = '/* END Seraph WP Hardening */';

	public static function ensure_constant(string $name, string $value_php): bool|string {
		$path = ABSPATH . 'wp-config.php';
		if (! is_readable($path) || ! is_writable($path)) {
			return __('wp-config.php is not writable.', 'wp-hardening');
		}

		$content = (string) file_get_contents($path);
		if (preg_match('/define\s*\(\s*[\'"]' . preg_quote($name, '/') . '[\'"]\s*,/i', $content)) {
			// Already defined elsewhere — do not overwrite unknown custom code.
			return true;
		}

		$line = "define( '{$name}', {$value_php} );";
		if (str_contains($content, self::BEGIN) && str_contains($content, self::END)) {
			$block = self::BEGIN . "\n" . $line . "\n" . self::END;
			// Append constant inside block if missing.
			$updated = preg_replace_callback(
				'/' . preg_quote(self::BEGIN, '/') . '(.*?)' . preg_quote(self::END, '/') . '/s',
				static function (array $m) use ($line, $name): string {
					$inner = $m[1];
					if (str_contains($inner, $name)) {
						return $m[0];
					}
					return self::BEGIN . rtrim($inner) . "\n" . $line . "\n" . self::END;
				},
				$content
			);
			$content = is_string($updated) ? $updated : $content;
		} else {
			$insert  = self::BEGIN . "\n" . $line . "\n" . self::END . "\n";
			$anchor  = "/* That's all, stop editing!";
			if (str_contains($content, $anchor)) {
				$content = str_replace($anchor, $insert . "\n" . $anchor, $content);
			} else {
				$content .= "\n" . $insert;
			}
		}

		$ok = file_put_contents($path, $content); // phpcs:ignore
		return $ok !== false ? true : __('Failed writing wp-config.php.', 'wp-hardening');
	}
}
