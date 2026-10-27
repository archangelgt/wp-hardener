<?php
/**
 * Admin menus and page router.
 *
 * @package Seraph\WPHardening
 */

declare(strict_types=1);

namespace Seraph\WPHardening\Admin;

use Seraph\WPHardening\Backups\Backup;
use Seraph\WPHardening\Database\Utilities as DbUtilities;
use Seraph\WPHardening\Helpers\Options;
use Seraph\WPHardening\Htaccess\Manager as HtaccessManager;
use Seraph\WPHardening\Permissions\Permissions;
use Seraph\WPHardening\Scanner\Scanner;
use Seraph\WPHardening\Security\Applier;
use Seraph\WPHardening\Security\Rules;
use Seraph\WPHardening\StealthLogin\Stealth;

final class Menu {
	public static function register(): void {
		add_action('admin_menu', array(self::class, 'menu'));
		add_action('admin_enqueue_scripts', array(self::class, 'assets'));
		add_action('admin_init', array(self::class, 'handle_posts'));
		add_action('admin_notices', array(self::class, 'wizard_notice'));
	}

	public static function menu(): void {
		add_menu_page(
			__('WP Hardening', 'wp-hardening'),
			__('WP Hardening', 'wp-hardening'),
			'manage_options',
			'seraph-wph',
			array(self::class, 'render_dashboard'),
			'dashicons-shield',
			58
		);

		$pages = array(
			'seraph-wph'           => array( __('Dashboard', 'wp-hardening'), array(self::class, 'render_dashboard') ),
			'seraph-wph-scanner'   => array( __('Scanner', 'wp-hardening'), array(self::class, 'render_scanner') ),
			'seraph-wph-hardening' => array( __('Hardening', 'wp-hardening'), array(self::class, 'render_hardening') ),
			'seraph-wph-permissions' => array( __('Permissions', 'wp-hardening'), array(self::class, 'render_permissions') ),
			'seraph-wph-htaccess'  => array( __(' .htaccess', 'wp-hardening'), array(self::class, 'render_htaccess') ),
			'seraph-wph-wpconfig'  => array( __('wp-config', 'wp-hardening'), array(self::class, 'render_wpconfig') ),
			'seraph-wph-login'     => array( __('Login Security', 'wp-hardening'), array(self::class, 'render_login') ),
			'seraph-wph-stealth'   => array( __('Stealth Login', 'wp-hardening'), array(self::class, 'render_stealth') ),
			'seraph-wph-recaptcha' => array( __('reCAPTCHA', 'wp-hardening'), array(self::class, 'render_recaptcha') ),
			'seraph-wph-database'  => array( __('Database', 'wp-hardening'), array(self::class, 'render_database') ),
			'seraph-wph-reports'   => array( __('Reports', 'wp-hardening'), array(self::class, 'render_reports') ),
			'seraph-wph-settings'  => array( __('Settings', 'wp-hardening'), array(self::class, 'render_settings') ),
			'seraph-wph-about'     => array( __('About', 'wp-hardening'), array(self::class, 'render_about') ),
			'seraph-wph-wizard'    => array( __('Setup Wizard', 'wp-hardening'), array(self::class, 'render_wizard') ),
		);

		foreach ($pages as $slug => $cfg) {
			add_submenu_page('seraph-wph', $cfg[0], $cfg[0], 'manage_options', $slug, $cfg[1]);
		}
	}

	public static function assets(string $hook): void {
		if (! str_contains($hook, 'seraph-wph')) {
			return;
		}
		wp_enqueue_style('seraph-wph-admin', SERAPH_WPH_URL . 'assets/css/admin.css', array(), SERAPH_WPH_VERSION);
		wp_enqueue_style('seraph-wph-wizard', SERAPH_WPH_URL . 'assets/css/wizard.css', array( 'seraph-wph-admin' ), SERAPH_WPH_VERSION);
		wp_enqueue_script('seraph-wph-admin', SERAPH_WPH_URL . 'assets/js/admin.js', array(), SERAPH_WPH_VERSION, true);
	}

	public static function wizard_notice(): void {
		if (! current_user_can('manage_options') || get_option(Options::WIZARD)) {
			return;
		}
		$screen = function_exists('get_current_screen') ? get_current_screen() : null;
		if ($screen && str_contains((string) $screen->id, 'seraph-wph-wizard')) {
			return;
		}
		printf(
			'<div class="notice notice-info"><p>%s <a class="button button-primary" href="%s">%s</a></p></div>',
			esc_html__('Finish Seraph WP Hardening setup to improve your security score.', 'wp-hardening'),
			esc_url(admin_url('admin.php?page=seraph-wph-wizard')),
			esc_html__('Open Setup Wizard', 'wp-hardening')
		);
	}

	public static function handle_posts(): void {
		if (! current_user_can('manage_options')) {
			return;
		}
		if (empty($_POST['seraph_wph_action'])) { // phpcs:ignore
			return;
		}
		check_admin_referer('seraph_wph_action');
		$action = sanitize_key((string) wp_unslash($_POST['seraph_wph_action'])); // phpcs:ignore

		switch ($action) {
			case 'apply_rules':
				$rules = isset($_POST['rules']) && is_array($_POST['rules']) // phpcs:ignore
					? array_map('sanitize_key', wp_unslash($_POST['rules'])) // phpcs:ignore
					: array();
				$result = Applier::apply($rules);
				set_transient('seraph_wph_flash', $result, 60);
				wp_safe_redirect(admin_url('admin.php?page=seraph-wph-hardening&applied=1'));
				exit;

			case 'save_stealth':
				$s = Options::settings();
				$s['stealth_enabled']    = ! empty($_POST['stealth_enabled']);
				$s['stealth_slug']       = sanitize_title((string) wp_unslash($_POST['stealth_slug'] ?? 'secure-login'));
				$s['stealth_block_mode'] = sanitize_key((string) wp_unslash($_POST['stealth_block_mode'] ?? '404'));
				Options::update_settings($s);
				flush_rewrite_rules();
				wp_safe_redirect(admin_url('admin.php?page=seraph-wph-stealth&saved=1'));
				exit;

			case 'save_login':
				$s = Options::settings();
				$s['hide_login_errors']  = ! empty($_POST['hide_login_errors']);
				$s['login_rate_limit']   = ! empty($_POST['login_rate_limit']);
				$s['login_max_attempts'] = max(1, (int) ($_POST['login_max_attempts'] ?? 5));
				$s['login_lockout_mins'] = max(1, (int) ($_POST['login_lockout_mins'] ?? 15));
				$s['block_user_enum']    = ! empty($_POST['block_user_enum']);
				Options::update_settings($s);
				wp_safe_redirect(admin_url('admin.php?page=seraph-wph-login&saved=1'));
				exit;

			case 'save_recaptcha':
				$s = Options::settings();
				$s['recaptcha_enabled']  = ! empty($_POST['recaptcha_enabled']);
				$s['recaptcha_site_key'] = sanitize_text_field((string) wp_unslash($_POST['recaptcha_site_key'] ?? ''));
				$s['recaptcha_secret']   = sanitize_text_field((string) wp_unslash($_POST['recaptcha_secret'] ?? ''));
				$s['recaptcha_version']  = sanitize_key((string) wp_unslash($_POST['recaptcha_version'] ?? 'v3'));
				Options::update_settings($s);
				wp_safe_redirect(admin_url('admin.php?page=seraph-wph-recaptcha&saved=1'));
				exit;

			case 'apply_htaccess':
				$r = HtaccessManager::apply_hardening_block();
				set_transient('seraph_wph_flash', array( 'htaccess' => $r ), 60);
				wp_safe_redirect(admin_url('admin.php?page=seraph-wph-htaccess&saved=1'));
				exit;

			case 'optimize_db':
				$r = DbUtilities::optimize_tables();
				set_transient('seraph_wph_flash', $r, 60);
				wp_safe_redirect(admin_url('admin.php?page=seraph-wph-database&optimized=1'));
				exit;

			case 'wizard_finish':
				$profile = sanitize_key((string) wp_unslash($_POST['profile'] ?? 'standard'));
				$rules   = isset($_POST['rules']) && is_array($_POST['rules'])
					? array_map('sanitize_key', wp_unslash($_POST['rules']))
					: Rules::for_profile($profile);
				update_option(Options::PROFILE, $profile, false);
				$result = Applier::apply($rules);
				update_option(Options::WIZARD, 1, false);
				set_transient('seraph_wph_flash', $result, 120);
				wp_safe_redirect(admin_url('admin.php?page=seraph-wph&wizard=done'));
				exit;
		}
	}

	public static function render_dashboard(): void {
		$scan = Scanner::run();
		self::shell(
			__('Dashboard', 'wp-hardening'),
			static function () use ($scan): void {
				$grade = $scan['grade'];
				echo '<div class="seraph-wph-score seraph-wph-score--' . esc_attr($grade) . '">';
				echo '<div class="seraph-wph-score__value">' . esc_html((string) $scan['score']) . '</div>';
				echo '<div class="seraph-wph-score__label">' . esc_html(ucfirst($grade)) . '</div>';
				echo '</div>';
				echo '<div class="seraph-wph-grid">';
				echo '<div class="seraph-wph-card"><h2>' . esc_html__('WordPress', 'wp-hardening') . '</h2><pre>' . esc_html(wp_json_encode($scan['wordpress'], JSON_PRETTY_PRINT)) . '</pre></div>';
				echo '<div class="seraph-wph-card"><h2>' . esc_html__('PHP', 'wp-hardening') . '</h2><pre>' . esc_html(wp_json_encode($scan['php'], JSON_PRETTY_PRINT)) . '</pre></div>';
				echo '<div class="seraph-wph-card"><h2>' . esc_html__('Server', 'wp-hardening') . '</h2><pre>' . esc_html(wp_json_encode($scan['server'], JSON_PRETTY_PRINT)) . '</pre></div>';
				echo '</div>';
				$log = get_option(Options::LOG, array());
				echo '<div class="seraph-wph-card"><h2>' . esc_html__('Recent changes', 'wp-hardening') . '</h2><ul>';
				if (is_array($log) && $log) {
					foreach (array_reverse(array_slice($log, -8)) as $row) {
						printf(
							'<li><code>%s</code> — %s</li>',
							esc_html(gmdate('Y-m-d H:i', (int) ($row['time'] ?? 0))),
							esc_html((string) ($row['action'] ?? ''))
						);
					}
				} else {
					echo '<li>' . esc_html__('No changes yet.', 'wp-hardening') . '</li>';
				}
				echo '</ul></div>';
			}
		);
	}

	public static function render_scanner(): void {
		$scan = Scanner::run();
		self::shell(
			__('Security Scanner', 'wp-hardening'),
			static function () use ($scan): void {
				echo '<table class="widefat striped seraph-wph-table"><thead><tr><th>' . esc_html__('Check', 'wp-hardening') . '</th><th>' . esc_html__('Status', 'wp-hardening') . '</th><th>' . esc_html__('Points', 'wp-hardening') . '</th><th>' . esc_html__('Detail', 'wp-hardening') . '</th></tr></thead><tbody>';
				foreach ($scan['checks'] as $check) {
					printf(
						'<tr class="%s"><td>%s</td><td>%s</td><td>+%d</td><td>%s</td></tr>',
						! empty($check['pass']) ? 'is-pass' : 'is-fail',
						esc_html((string) $check['label']),
						! empty($check['pass']) ? '✓' : '✗',
						(int) $check['points'],
						esc_html((string) $check['detail'])
					);
				}
				echo '</tbody></table>';
			}
		);
	}

	public static function render_hardening(): void {
		$rules   = Rules::all();
		$applied = Options::applied();
		$flash   = get_transient('seraph_wph_flash');
		delete_transient('seraph_wph_flash');
		self::shell(
			__('Hardening', 'wp-hardening'),
			static function () use ($rules, $applied, $flash): void {
				if (is_array($flash)) {
					echo '<div class="notice notice-success"><p>' . esc_html__('Hardening applied.', 'wp-hardening') . '</p></div>';
				}
				echo '<form method="post">';
				wp_nonce_field('seraph_wph_action');
				echo '<input type="hidden" name="seraph_wph_action" value="apply_rules" />';
				echo '<table class="widefat striped"><thead><tr><th></th><th>' . esc_html__('Recommendation', 'wp-hardening') . '</th><th>' . esc_html__('Impact', 'wp-hardening') . '</th><th>' . esc_html__('Status', 'wp-hardening') . '</th></tr></thead><tbody>';
				foreach ($rules as $id => $rule) {
					$is_on = ! empty($applied[ $id ]);
					printf(
						'<tr><td><input type="checkbox" name="rules[]" value="%s" %s /></td><td><strong>%s</strong><p class="description">%s</p></td><td>+%d</td><td>%s</td></tr>',
						esc_attr($id),
						$is_on ? 'checked' : '',
						esc_html((string) $rule['label']),
						esc_html((string) $rule['description']),
						(int) $rule['points'],
						$is_on ? esc_html__('Applied', 'wp-hardening') : esc_html__('Pending', 'wp-hardening')
					);
				}
				echo '</tbody></table>';
				submit_button(__('Backup & apply selected', 'wp-hardening'));
				echo '</form>';
			}
		);
	}

	public static function render_permissions(): void {
		$rows = Permissions::audit();
		self::shell(
			__('Permissions', 'wp-hardening'),
			static function () use ($rows): void {
				echo '<table class="widefat striped"><thead><tr><th>Item</th><th>Path</th><th>Current</th><th>Expected</th><th>OK</th></tr></thead><tbody>';
				foreach ($rows as $row) {
					printf(
						'<tr><td>%s</td><td><code>%s</code></td><td>%s</td><td>%s</td><td>%s</td></tr>',
						esc_html($row['label']),
						esc_html($row['path']),
						esc_html((string) $row['perms']),
						esc_html(implode(', ', $row['expect'])),
						! empty($row['ok']) ? '✓' : '✗'
					);
				}
				echo '</tbody></table>';
				echo '<p class="description">' . esc_html__('Permission fixes may require SSH/hosting panel access; WP Hardening reports status and never forces unsafe chmod on shared hosts.', 'wp-hardening') . '</p>';
			}
		);
	}

	public static function render_htaccess(): void {
		self::shell(
			__('.htaccess Manager', 'wp-hardening'),
			static function (): void {
				echo '<pre class="seraph-wph-code">' . esc_html(HtaccessManager::rules_block()) . '</pre>';
				echo '<form method="post">';
				wp_nonce_field('seraph_wph_action');
				echo '<input type="hidden" name="seraph_wph_action" value="apply_htaccess" />';
				submit_button(__('Backup & write hardening block', 'wp-hardening'));
				echo '</form>';
				echo '<h2>' . esc_html__('Backups', 'wp-hardening') . '</h2><ul>';
				foreach (Backup::list_backups() as $b) {
					printf('<li><code>%s</code> (%s)</li>', esc_html($b['file']), esc_html(size_format($b['size'])));
				}
				echo '</ul>';
			}
		);
	}

	public static function render_wpconfig(): void {
		self::shell(
			__('wp-config Manager', 'wp-hardening'),
			static function (): void {
				$map = array(
					'DISALLOW_FILE_EDIT' => defined('DISALLOW_FILE_EDIT') ? (DISALLOW_FILE_EDIT ? 'true' : 'false') : 'undefined',
					'FORCE_SSL_ADMIN'    => defined('FORCE_SSL_ADMIN') ? (FORCE_SSL_ADMIN ? 'true' : 'false') : 'undefined',
					'WP_DEBUG'           => defined('WP_DEBUG') ? (WP_DEBUG ? 'true' : 'false') : 'undefined',
					'WP_DEBUG_DISPLAY'   => defined('WP_DEBUG_DISPLAY') ? (WP_DEBUG_DISPLAY ? 'true' : 'false') : 'undefined',
					'WP_DEBUG_LOG'       => defined('WP_DEBUG_LOG') ? (WP_DEBUG_LOG ? 'true' : 'false') : 'undefined',
				);
				echo '<table class="widefat striped"><tbody>';
				foreach ($map as $k => $v) {
					printf('<tr><th>%s</th><td><code>%s</code></td></tr>', esc_html($k), esc_html($v));
				}
				echo '</tbody></table>';
				echo '<p class="description">' . esc_html__('Constants are added via Hardening rules without overwriting unknown custom code.', 'wp-hardening') . '</p>';
			}
		);
	}

	public static function render_login(): void {
		$s = Options::settings();
		self::shell(
			__('Login Security', 'wp-hardening'),
			static function () use ($s): void {
				echo '<form method="post">';
				wp_nonce_field('seraph_wph_action');
				echo '<input type="hidden" name="seraph_wph_action" value="save_login" />';
				echo '<table class="form-table"><tr><th>' . esc_html__('Hide login errors', 'wp-hardening') . '</th><td><label><input type="checkbox" name="hide_login_errors" value="1" ' . checked(! empty($s['hide_login_errors']), true, false) . '> ' . esc_html__('Enabled', 'wp-hardening') . '</label></td></tr>';
				echo '<tr><th>' . esc_html__('Block user enumeration', 'wp-hardening') . '</th><td><label><input type="checkbox" name="block_user_enum" value="1" ' . checked(! empty($s['block_user_enum']), true, false) . '> ' . esc_html__('Enabled', 'wp-hardening') . '</label></td></tr>';
				echo '<tr><th>' . esc_html__('Rate limiting', 'wp-hardening') . '</th><td><label><input type="checkbox" name="login_rate_limit" value="1" ' . checked(! empty($s['login_rate_limit']), true, false) . '> ' . esc_html__('Enabled', 'wp-hardening') . '</label></td></tr>';
				echo '<tr><th>' . esc_html__('Max attempts', 'wp-hardening') . '</th><td><input type="number" name="login_max_attempts" value="' . esc_attr((string) $s['login_max_attempts']) . '" min="1" /></td></tr>';
				echo '<tr><th>' . esc_html__('Lockout minutes', 'wp-hardening') . '</th><td><input type="number" name="login_lockout_mins" value="' . esc_attr((string) $s['login_lockout_mins']) . '" min="1" /></td></tr>';
				echo '</table>';
				submit_button();
				echo '</form>';
			}
		);
	}

	public static function render_stealth(): void {
		$s = Options::settings();
		self::shell(
			__('Stealth Login', 'wp-hardening'),
			static function () use ($s): void {
				if (Stealth::disabled()) {
					echo '<div class="notice notice-warning"><p>' . esc_html__('Login protection temporarily disabled by recovery constant/file.', 'wp-hardening') . '</p></div>';
				}
				echo '<form method="post">';
				wp_nonce_field('seraph_wph_action');
				echo '<input type="hidden" name="seraph_wph_action" value="save_stealth" />';
				echo '<table class="form-table">';
				echo '<tr><th>' . esc_html__('Enable', 'wp-hardening') . '</th><td><label><input type="checkbox" name="stealth_enabled" value="1" ' . checked(! empty($s['stealth_enabled']), true, false) . '> ' . esc_html__('Replace wp-login.php', 'wp-hardening') . '</label></td></tr>';
				echo '<tr><th>' . esc_html__('Custom slug', 'wp-hardening') . '</th><td><code>' . esc_html(home_url('/')) . '</code><input type="text" name="stealth_slug" value="' . esc_attr((string) $s['stealth_slug']) . '" /></td></tr>';
				echo '<tr><th>' . esc_html__('Block mode', 'wp-hardening') . '</th><td><select name="stealth_block_mode">';
				foreach (array( '404' => '404', '403' => '403', 'home' => 'Redirect home' ) as $k => $label) {
					printf('<option value="%s" %s>%s</option>', esc_attr($k), selected($s['stealth_block_mode'], $k, false), esc_html($label));
				}
				echo '</select></td></tr></table>';
				echo '<p class="description">' . esc_html__('Recovery: define SERAPH_DISABLE_LOGIN_PROTECTION as true, or create wp-content/seraph-wph-disable-login.lock', 'wp-hardening') . '</p>';
				submit_button();
				echo '</form>';
			}
		);
	}

	public static function render_recaptcha(): void {
		$s = Options::settings();
		self::shell(
			__('Google reCAPTCHA', 'wp-hardening'),
			static function () use ($s): void {
				echo '<form method="post">';
				wp_nonce_field('seraph_wph_action');
				echo '<input type="hidden" name="seraph_wph_action" value="save_recaptcha" />';
				echo '<table class="form-table">';
				echo '<tr><th>Enable</th><td><label><input type="checkbox" name="recaptcha_enabled" value="1" ' . checked(! empty($s['recaptcha_enabled']), true, false) . '> Enabled</label></td></tr>';
				echo '<tr><th>Version</th><td><select name="recaptcha_version">';
				foreach (array( 'v2', 'invisible', 'v3' ) as $v) {
					printf('<option value="%s" %s>%s</option>', esc_attr($v), selected($s['recaptcha_version'], $v, false), esc_html($v));
				}
				echo '</select></td></tr>';
				echo '<tr><th>Site key</th><td><input class="regular-text" type="text" name="recaptcha_site_key" value="' . esc_attr((string) $s['recaptcha_site_key']) . '" /></td></tr>';
				echo '<tr><th>Secret</th><td><input class="regular-text" type="password" name="recaptcha_secret" value="' . esc_attr((string) $s['recaptcha_secret']) . '" autocomplete="new-password" /></td></tr>';
				echo '</table>';
				echo '<p class="description">' . esc_html__('Protects WordPress login/register/lost password and injects a widget into Contact Form 7 forms when enabled.', 'wp-hardening') . '</p>';
				submit_button();
				echo '</form>';
			}
		);
	}

	public static function render_database(): void {
		$info  = DbUtilities::info();
		$flash = get_transient('seraph_wph_flash');
		delete_transient('seraph_wph_flash');
		self::shell(
			__('Database', 'wp-hardening'),
			static function () use ($info, $flash): void {
				if (is_array($flash) && isset($flash['optimized'])) {
					printf('<div class="notice notice-success"><p>%s</p></div>', esc_html(sprintf(__('Optimized %d tables.', 'wp-hardening'), (int) $flash['optimized'])));
				}
				echo '<pre class="seraph-wph-code">' . esc_html(wp_json_encode($info, JSON_PRETTY_PRINT)) . '</pre>';
				echo '<form method="post">';
				wp_nonce_field('seraph_wph_action');
				echo '<input type="hidden" name="seraph_wph_action" value="optimize_db" />';
				submit_button(__('Optimize tables', 'wp-hardening'));
				echo '</form>';
			}
		);
	}

	public static function render_reports(): void {
		$scan = Scanner::run();
		self::shell(
			__('Reports', 'wp-hardening'),
			static function () use ($scan): void {
				echo '<div class="seraph-wph-card">';
				echo '<h2>' . esc_html__('Security report', 'wp-hardening') . '</h2>';
				echo '<p>' . esc_html(sprintf(__('Score: %d (%s)', 'wp-hardening'), $scan['score'], $scan['grade'])) . '</p>';
				$pass = count(array_filter($scan['checks'], static fn($c) => ! empty($c['pass'])));
				$fail = count($scan['checks']) - $pass;
				echo '<p>' . esc_html(sprintf(__('Passed: %d · Remaining: %d', 'wp-hardening'), $pass, $fail)) . '</p>';
				echo '<p><a class="button" href="' . esc_url(admin_url('admin.php?page=seraph-wph-scanner')) . '">' . esc_html__('Open scanner', 'wp-hardening') . '</a></p>';
				echo '</div>';
			}
		);
	}

	public static function render_settings(): void {
		self::shell(
			__('Settings', 'wp-hardening'),
			static function (): void {
				echo '<p>' . esc_html__('WP Hardening stores settings locally. No telemetry, license servers, or remote APIs.', 'wp-hardening') . '</p>';
				echo '<p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=seraph-wph-wizard')) . '">' . esc_html__('Re-run setup wizard', 'wp-hardening') . '</a></p>';
			}
		);
	}

	public static function render_about(): void {
		self::shell(
			__('About', 'wp-hardening'),
			static function (): void {
				echo '<div class="seraph-wph-card seraph-wph-about">';
				echo '<h2>Seraph Systems · WP Hardening</h2>';
				echo '<p>' . esc_html__('Version', 'wp-hardening') . ' <strong>' . esc_html(SERAPH_WPH_VERSION) . '</strong></p>';
				echo '<p>' . esc_html__('Reduce the WordPress attack surface while keeping the website fast, stable, and easy to maintain.', 'wp-hardening') . '</p>';
				echo '<p><a href="https://seraphsystems.com/" target="_blank" rel="noopener noreferrer">seraphsystems.com</a></p>';
				echo '</div>';
			}
		);
	}

	public static function render_wizard(): void {
		\Seraph\WPHardening\Installer\Wizard::render();
	}

	/**
	 * @param callable():void $content Content callback.
	 */
	private static function shell(string $title, callable $content): void {
		echo '<div class="wrap seraph-wph">';
		echo '<h1>' . esc_html($title) . '</h1>';
		echo '<p class="seraph-wph-sub">' . esc_html__('Seraph Systems WP Hardening', 'wp-hardening') . ' · v' . esc_html(SERAPH_WPH_VERSION) . '</p>';
		$content();
		echo '</div>';
	}
}
