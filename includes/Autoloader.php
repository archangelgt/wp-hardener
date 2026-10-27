<?php
/**
 * Simple PSR-4 style autoloader for Seraph\WPHardening\.
 *
 * @package Seraph\WPHardening
 */

declare(strict_types=1);

namespace Seraph\WPHardening;

final class Autoloader {
	private static string $base = '';

	public static function register(string $base_path): void {
		self::$base = rtrim($base_path, '/\\') . DIRECTORY_SEPARATOR;
		spl_autoload_register(array(self::class, 'load'));
	}

	public static function load(string $class): void {
		$prefix = __NAMESPACE__ . '\\';
		if (! str_starts_with($class, $prefix)) {
			return;
		}

		$relative = substr($class, strlen($prefix));
		$path     = self::$base . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';

		if (is_readable($path)) {
			require_once $path;
		}
	}
}
