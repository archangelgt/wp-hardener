<?php
/**
 * Uninstall cleanup — options only; never deletes host backups outside plugin storage.
 *
 * @package Seraph\WPHardening
 */

declare(strict_types=1);

if (! defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

$options = array(
	'seraph_wph_settings',
	'seraph_wph_wizard_done',
	'seraph_wph_profile',
	'seraph_wph_applied',
	'seraph_wph_score_cache',
	'seraph_wph_changelog',
	'seraph_wph_login_attempts',
);

foreach ($options as $option) {
	delete_option($option);
	delete_site_option($option);
}

$upload = wp_upload_dir();
$dir    = trailingslashit($upload['basedir']) . 'seraph-wph-backups';
if (is_dir($dir)) {
	$files = glob($dir . '/*');
	if (is_array($files)) {
		foreach ($files as $file) {
			if (is_file($file)) {
				unlink($file); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			}
		}
	}
}
