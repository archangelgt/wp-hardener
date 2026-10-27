<?php
/**
 * Installation wizard.
 *
 * @package Seraph\WPHardening
 */

declare(strict_types=1);

namespace Seraph\WPHardening\Installer;

use Seraph\WPHardening\Scanner\Scanner;
use Seraph\WPHardening\Security\Rules;

final class Wizard {
	public static function register(): void {
		// Reserved for future AJAX steps.
	}

	public static function render(): void {
		$scan     = Scanner::run();
		$profiles = array(
			'standard'    => array(
				'label' => __('Standard', 'wp-hardening'),
				'desc'  => __('Recommended for business websites.', 'wp-hardening'),
			),
			'blog'        => array(
				'label' => __('Blog', 'wp-hardening'),
				'desc'  => __('Keeps comments enabled.', 'wp-hardening'),
			),
			'woocommerce' => array(
				'label' => __('WooCommerce', 'wp-hardening'),
				'desc'  => __('Optimized for WooCommerce stores.', 'wp-hardening'),
			),
			'custom'      => array(
				'label' => __('Custom', 'wp-hardening'),
				'desc'  => __('Select every hardening rule manually.', 'wp-hardening'),
			),
		);
		$rules = Rules::all();
		?>
		<div class="wrap seraph-wph seraph-wph-wizard">
			<div class="seraph-wph-wizard__stage" aria-hidden="true">
				<div class="seraph-wph-wizard__aurora"></div>
				<div class="seraph-wph-wizard__grid"></div>
			</div>

			<div class="seraph-wph-wizard__panel">
				<header class="seraph-wph-wizard__hero">
					<p class="seraph-wph-wizard__brand">Seraph Systems</p>
					<h1><?php esc_html_e('WP Hardening', 'wp-hardening'); ?></h1>
					<p><?php esc_html_e('Secure WordPress through configuration and best practices — not unnecessary complexity.', 'wp-hardening'); ?></p>
					<p class="seraph-wph-wizard__ver">v<?php echo esc_html(SERAPH_WPH_VERSION); ?></p>
				</header>

				<form method="post" class="seraph-wph-wizard__form" id="seraph-wph-wizard-form">
					<?php wp_nonce_field('seraph_wph_action'); ?>
					<input type="hidden" name="seraph_wph_action" value="wizard_finish" />

					<section class="seraph-wph-step" data-step="1">
						<h2><?php esc_html_e('1 · Welcome', 'wp-hardening'); ?></h2>
						<p><?php esc_html_e('This wizard analyzes your environment, explains recommendations, backs up critical files, applies only what you approve, and verifies results.', 'wp-hardening'); ?></p>
						<ol class="seraph-wph-flow">
							<li><?php esc_html_e('Analyze', 'wp-hardening'); ?></li>
							<li><?php esc_html_e('Explain', 'wp-hardening'); ?></li>
							<li><?php esc_html_e('Backup', 'wp-hardening'); ?></li>
							<li><?php esc_html_e('Apply', 'wp-hardening'); ?></li>
							<li><?php esc_html_e('Verify', 'wp-hardening'); ?></li>
						</ol>
					</section>

					<section class="seraph-wph-step" data-step="2">
						<h2><?php esc_html_e('2 · Server compatibility', 'wp-hardening'); ?></h2>
						<ul class="seraph-wph-compat">
							<li><strong>PHP</strong> <?php echo esc_html((string) $scan['php']['version']); ?></li>
							<li><strong>WordPress</strong> <?php echo esc_html((string) $scan['wordpress']['version']); ?></li>
							<li><strong>HTTPS</strong> <?php echo ! empty($scan['server']['https']) ? 'yes' : 'no'; ?></li>
							<li><strong>Server</strong> <?php echo esc_html((string) $scan['server']['software']); ?></li>
							<li><strong>Memory</strong> <?php echo esc_html((string) $scan['php']['memory_limit']); ?></li>
						</ul>
					</section>

					<section class="seraph-wph-step" data-step="3">
						<h2><?php esc_html_e('3 · Security profile', 'wp-hardening'); ?></h2>
						<div class="seraph-wph-profiles">
							<?php foreach ($profiles as $id => $profile) : ?>
								<label class="seraph-wph-profile">
									<input type="radio" name="profile" value="<?php echo esc_attr($id); ?>" <?php checked($id, 'standard'); ?> data-profile="<?php echo esc_attr($id); ?>" />
									<span class="seraph-wph-profile__title"><?php echo esc_html($profile['label']); ?></span>
									<span class="seraph-wph-profile__desc"><?php echo esc_html($profile['desc']); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
					</section>

					<section class="seraph-wph-step" data-step="4">
						<h2><?php esc_html_e('4 · Recommendations', 'wp-hardening'); ?></h2>
						<div class="seraph-wph-rules">
							<?php foreach ($rules as $id => $rule) : ?>
								<label class="seraph-wph-rule" data-profiles="<?php echo esc_attr(implode(',', $rule['profiles'])); ?>">
									<input type="checkbox" name="rules[]" value="<?php echo esc_attr($id); ?>" checked />
									<span>
										<strong><?php echo esc_html((string) $rule['label']); ?></strong>
										<em>+<?php echo esc_html((string) $rule['points']); ?></em>
										<small><?php echo esc_html((string) $rule['description']); ?></small>
									</span>
								</label>
							<?php endforeach; ?>
						</div>
					</section>

					<section class="seraph-wph-step" data-step="5">
						<h2><?php esc_html_e('5 · Apply changes', 'wp-hardening'); ?></h2>
						<p><?php esc_html_e('Before modifying .htaccess / wp-config.php, WP Hardening creates timestamped backups you can restore later.', 'wp-hardening'); ?></p>
						<?php submit_button(__('Backup, apply & finish', 'wp-hardening'), 'primary large', 'submit', false); ?>
					</section>
				</form>
			</div>
		</div>
		<script src="<?php echo esc_url(SERAPH_WPH_URL . 'assets/js/wizard.js?ver=' . rawurlencode(SERAPH_WPH_VERSION)); ?>"></script>
		<?php
	}
}
