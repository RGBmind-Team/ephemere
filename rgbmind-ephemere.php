<?php
/**
 * Plugin Name:       RGBmind Ephemere
 * Plugin URI:        https://rgbmind.com/plugins/rgbmind-ephemere
 * Description:       Advanced temporary login and timed access manager for WordPress users. Set expiration dates, max login limits, and instant account locking with zero custom database tables and zero external API calls.
 * Version:           1.2.1
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            RGBmind
 * Author URI:        https://rgbmind.com
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       rgbmind-ephemere
 * Domain Path:       /languages
 *
 * @package RGBmind_Ephemere
 */

// Prevent direct file access for security.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin constants.
 */
define( 'RGBMIND_EPH_VERSION', '1.2.1' );
define( 'RGBMIND_EPH_PLUGIN_FILE', __FILE__ );
define( 'RGBMIND_EPH_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RGBMIND_EPH_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'RGBMIND_EPH_PRO_URL', 'https://rgbmind.com' );

/**
 * Returns supported languages for the plugin.
 *
 * @return array
 */
function rgbmind_eph_get_supported_languages() {
	return array(
		'auto'  => __( 'Automatic', 'rgbmind-ephemere' ),
		'en_US' => 'English (US)',
		'it_IT' => 'Italiano',
		'es_ES' => 'Español',
		'fr_FR' => 'Français',
		'de_DE' => 'Deutsch',
		'pt_BR' => 'Português do Brasil',
	);
}

/**
 * Filters the plugin locale to allow manual language override.
 *
 * @param string $locale Current locale.
 * @param string $domain Current textdomain.
 * @return string
 */
function rgbmind_eph_filter_plugin_locale( $locale, $domain ) {
	if ( 'rgbmind-ephemere' === $domain ) {
		$override = get_option( 'rgbmind_eph_language', 'auto' );
		if ( ! empty( $override ) && 'auto' !== $override ) {
			return preg_replace( '/[^A-Za-z0-9_]/', '', (string) $override );
		}
	}
	return $locale;
}
add_filter( 'plugin_locale', 'rgbmind_eph_filter_plugin_locale', 10, 2 );

/**
 * Load plugin textdomain for internationalization.
 *
 * Checks for user-configured manual language preference or falls back
 * automatically to active WordPress locale. Direct load_textdomain ensures
 * instant compatibility with WordPress 6.5+ and 6.7+ JIT translation controllers.
 *
 * @return void
 */
function rgbmind_eph_load_textdomain() {
	$chosen = get_option( 'rgbmind_eph_language', 'auto' );
	$locale = ( ! empty( $chosen ) && 'auto' !== $chosen ) ? preg_replace( '/[^A-Za-z0-9_]/', '', (string) $chosen ) : determine_locale();

	// Unload any prior loaded instance to ensure clean language switching
	unload_textdomain( 'rgbmind-ephemere' );

	// English is the default source language, so no MO file is needed
	if ( 'en_US' === $locale || ( 'auto' !== $chosen && 0 === strpos( $locale, 'en_' ) ) ) {
		return;
	}

	$mofile = RGBMIND_EPH_PLUGIN_DIR . 'languages/rgbmind-ephemere-' . $locale . '.mo';
	if ( file_exists( $mofile ) ) {
		load_textdomain( 'rgbmind-ephemere', $mofile, $locale );
	} else {
		// Fallback for standard WordPress translation repository
		load_plugin_textdomain(
			'rgbmind-ephemere',
			false,
			dirname( plugin_basename( RGBMIND_EPH_PLUGIN_FILE ) ) . '/languages'
		);
	}
}
add_action( 'plugins_loaded', 'rgbmind_eph_load_textdomain', 5 );
add_action( 'init', 'rgbmind_eph_load_textdomain', 5 );

/* =========================================================================
 * 1. EXTENSIBILITY & PRO ADDON HOOKS
 * ========================================================================= */

/**
 * Checks whether the commercial PRO add-on is active and enabled.
 *
 * In this free version hosted on WordPress.org, this returns false by default.
 * Commercial add-ons filter this value to true when active.
 *
 * @return bool True if PRO add-on is active, false otherwise.
 */
function rgbmind_eph_is_pro_active() {
	return (bool) apply_filters( 'rgbmind_eph_is_pro_active', false );
}

/* =========================================================================
 * 5. ADMIN INTERFACE (USER PROFILE FREE & PRO)
 * ========================================================================= */

/**
 * Enqueues administrative styles and scripts for RGBmind Ephemere.
 *
 * @param string $hook_suffix Current admin screen hook suffix.
 * @return void
 */
function rgbmind_eph_admin_enqueue_assets( $hook_suffix ) {
	$relevant_screens = array(
		'user-edit.php',
		'profile.php',
		'user-new.php',
		'toplevel_page_rgbmind-ephemere',
		'users.php',
	);

	$show_guide = (bool) get_option( 'rgbmind_eph_show_welcome_guide', false );

	if ( in_array( $hook_suffix, $relevant_screens, true ) || $show_guide ) {
		wp_enqueue_style(
			'rgbmind-ephemere-admin',
			RGBMIND_EPH_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			RGBMIND_EPH_VERSION
		);

		wp_enqueue_script(
			'rgbmind-ephemere-admin',
			RGBMIND_EPH_PLUGIN_URL . 'assets/js/admin.js',
			array(),
			RGBMIND_EPH_VERSION,
			true
		);

		wp_localize_script(
			'rgbmind-ephemere-admin',
			'rgbmindEphData',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'generatingText' => __( 'Generating...', 'rgbmind-ephemere' ),
				'copiedText'     => __( 'Copied!', 'rgbmind-ephemere' ),
				'copyText'       => __( 'Copy', 'rgbmind-ephemere' ),
				'dismissNonce'   => wp_create_nonce( 'rgbmind_eph_dismiss_welcome' ),
			)
		);
	}
}
add_action( 'admin_enqueue_scripts', 'rgbmind_eph_admin_enqueue_assets' );

/**
 * Renders temporary access management fields in the user profile form.
 *
 * @param WP_User $user Current WordPress user object.
 * @return void
 */
function rgbmind_eph_render_user_profile_fields( $user ) {
	if ( ! current_user_can( 'promote_users' ) && ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$user_id         = (int) $user->ID;
	$is_ephemeral    = (int) get_user_meta( $user_id, '_rgbmind_is_ephemeral', true );
	$expire_at       = (int) get_user_meta( $user_id, '_rgbmind_expire_at', true );
	$max_logins      = (int) get_user_meta( $user_id, '_rgbmind_max_logins', true );
	$login_count     = (int) get_user_meta( $user_id, '_rgbmind_login_count', true );
	$is_locked       = (int) get_user_meta( $user_id, '_rgbmind_is_locked', true );
	$last_login_time = (int) get_user_meta( $user_id, '_rgbmind_last_login_time', true );
	$is_pro          = rgbmind_eph_is_pro_active();

	// HTML5 datetime-local format
	$expire_input_value = $expire_at > 0 ? wp_date( 'Y-m-d\TH:i', $expire_at ) : '';

	// Status determination
	$now_ts       = time();
	$status_label = __( 'Standard Account (No restrictions)', 'rgbmind-ephemere' );
	$status_class = 'rgbmind-status-neutral';
	$status_desc  = __( 'This account has no time or login restrictions.', 'rgbmind-ephemere' );

	if ( 1 === $is_ephemeral ) {
		if ( 1 === $is_locked ) {
			$status_label = __( 'Locked', 'rgbmind-ephemere' );
			$status_class = 'rgbmind-status-locked';
			$status_desc  = __( 'Access is revoked manually or due to restriction limits.', 'rgbmind-ephemere' );
		} elseif ( $expire_at > 0 && $now_ts >= $expire_at ) {
			$status_label = __( 'Expired', 'rgbmind-ephemere' );
			$status_class = 'rgbmind-status-expired';
			$status_desc  = __( 'The expiration date and time have passed.', 'rgbmind-ephemere' );
		} elseif ( $max_logins > 0 && $login_count >= $max_logins ) {
			$status_label = __( 'Login Limit Reached', 'rgbmind-ephemere' );
			$status_class = 'rgbmind-status-exhausted';
			$status_desc  = sprintf(
				/* translators: 1: logins used, 2: max logins */
				__( 'All available logins have been used (%1$d of %2$d).', 'rgbmind-ephemere' ),
				$login_count,
				$max_logins
			);
		} else {
			$status_label = __( 'Temporary Active', 'rgbmind-ephemere' );
			$status_class = 'rgbmind-status-active';
			$status_desc  = __( 'The account meets temporary access criteria and login is allowed.', 'rgbmind-ephemere' );
		}
	}

	$timezone_string = wp_timezone_string();
	?>
	<div class="rgbmind-ephemere-wrapper" style="margin-top: 25px;">
		<div style="display: flex; align-items: center; justify-content: space-between; max-width: 850px; flex-wrap: wrap; gap: 10px;">
			<h2 style="margin: 0; display: flex; align-items: center; gap: 8px;">
				<span><?php esc_html_e( 'RGBmind Ephemere – Temporary Access Management', 'rgbmind-ephemere' ); ?></span>
				<span style="font-size: 11px; font-weight: 600; color: #646970; background: #f0f0f1; border: 1px solid #dcdcde; padding: 2px 7px; border-radius: 10px;">v<?php echo esc_html( RGBMIND_EPH_VERSION ); ?></span>
			</h2>
			<?php if ( $is_pro ) : ?>
				<span style="background: #e6f4ea; color: #137333; font-weight: 700; font-size: 11px; padding: 4px 10px; border-radius: 12px; border: 1px solid #ceead6; letter-spacing: 0.5px;">
					PRO ACTIVE
				</span>
			<?php else : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=rgbmind-ephemere&tab=settings' ) ); ?>" style="text-decoration: none;">
					<span style="background: #fef7e0; color: #b06000; font-weight: 700; font-size: 11px; padding: 4px 10px; border-radius: 12px; border: 1px solid #fce8b2; letter-spacing: 0.5px;">
						FREE VERSION (UPGRADE PRO)
					</span>
				</a>
			<?php endif; ?>
		</div>

		<?php wp_nonce_field( 'rgbmind_eph_save_profile_fields', 'rgbmind_eph_nonce' ); ?>

		<div class="rgbmind-status-box" style="margin: 15px 0; padding: 12px 16px; border-left: 4px solid #2271b1; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.05); max-width: 850px;">
			<div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px;">
				<strong style="font-size: 14px;"><?php esc_html_e( 'Security Status:', 'rgbmind-ephemere' ); ?></strong>
				<span class="rgbmind-badge <?php echo esc_attr( $status_class ); ?>" style="display: inline-block; padding: 2px 8px; border-radius: 3px; font-weight: 600; font-size: 12px;">
					<?php echo esc_html( $status_label ); ?>
				</span>
			</div>
			<p class="description" style="margin: 0;"><?php echo esc_html( $status_desc ); ?></p>
		</div>

		<table class="form-table" role="presentation" style="max-width: 850px;">
			<tbody>
				<!-- 1. Enable temporary restrictions (FREE) -->
				<tr>
					<th scope="row">
						<label for="_rgbmind_is_ephemeral"><?php esc_html_e( 'Temporary Account', 'rgbmind-ephemere' ); ?></label>
					</th>
					<td>
						<fieldset>
							<label for="_rgbmind_is_ephemeral">
								<input type="checkbox" name="_rgbmind_is_ephemeral" id="_rgbmind_is_ephemeral" value="1" <?php checked( 1, $is_ephemeral ); ?> />
								<strong><?php esc_html_e( 'Enable temporary access restrictions for this user', 'rgbmind-ephemere' ); ?></strong>
							</label>
							<p class="description">
								<?php esc_html_e( 'When enabled, the user will be subject to expiration time, login limits, and instant lockout checks.', 'rgbmind-ephemere' ); ?>
							</p>
						</fieldset>
					</td>
				</tr>

				<!-- 2. Expiration date and time (FREE) -->
				<tr class="rgbmind-ephemere-field">
					<th scope="row">
						<label for="_rgbmind_expire_at"><?php esc_html_e( 'Expiration Date & Time', 'rgbmind-ephemere' ); ?></label>
					</th>
					<td>
						<input type="datetime-local" name="_rgbmind_expire_at" id="_rgbmind_expire_at" value="<?php echo esc_attr( $expire_input_value ); ?>" class="regular-text" style="max-width: 280px;" />
						<p class="description">
							<?php
							printf(
								/* translators: %s: site timezone string */
								esc_html__( 'Access will be automatically denied after this date and time. Site timezone: %s. Leave empty for no time limit.', 'rgbmind-ephemere' ),
								'<strong>' . esc_html( $timezone_string ) . '</strong>'
							);
							?>
						</p>
						<?php if ( $expire_at > 0 ) : ?>
							<p class="description" style="color: <?php echo ( $now_ts >= $expire_at ) ? '#d63638' : '#2271b1'; ?>; font-weight: 500;">
								<?php
								$formatted_date = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $expire_at );
								if ( $now_ts >= $expire_at ) {
									printf(
										/* translators: %s: formatted date and time */
										esc_html__( 'Attention: Expired on %s.', 'rgbmind-ephemere' ),
										esc_html( $formatted_date )
									);
								} else {
									printf(
										/* translators: 1: formatted date/time, 2: human readable time diff */
										esc_html__( 'Scheduled expiration: %1$s (in about %2$s).', 'rgbmind-ephemere' ),
										esc_html( $formatted_date ),
										esc_html( human_time_diff( $now_ts, $expire_at ) )
									);
								}
								?>
							</p>
						<?php endif; ?>
					</td>
				</tr>

				<!-- 3. Maximum allowed logins (FREE) -->
				<tr class="rgbmind-ephemere-field">
					<th scope="row">
						<label for="_rgbmind_max_logins"><?php esc_html_e( 'Maximum Number of Logins', 'rgbmind-ephemere' ); ?></label>
					</th>
					<td>
						<input type="number" name="_rgbmind_max_logins" id="_rgbmind_max_logins" min="0" step="1" value="<?php echo esc_attr( $max_logins ); ?>" class="small-text" style="width: 90px;" />
						<span class="description"><?php esc_html_e( 'allowed logins (0 = unlimited).', 'rgbmind-ephemere' ); ?></span>
					</td>
				</tr>

				<!-- 4. Read-only login counter (FREE) -->
				<tr class="rgbmind-ephemere-field">
					<th scope="row">
						<label for="_rgbmind_login_count"><?php esc_html_e( 'Logins Used', 'rgbmind-ephemere' ); ?></label>
					</th>
					<td>
						<input type="number" id="_rgbmind_login_count" value="<?php echo esc_attr( $login_count ); ?>" readonly="readonly" class="small-text" style="width: 90px; background-color: #f0f0f1; cursor: not-allowed; color: #50575e;" />
						<span class="description" style="margin-left: 6px;">
							<?php
							if ( $max_logins > 0 ) {
								$remaining = max( 0, $max_logins - $login_count );
								printf(
									/* translators: 1: current, 2: max, 3: remaining */
									esc_html__( '%1$d of %2$d logins used (%3$d remaining).', 'rgbmind-ephemere' ),
									$login_count,
									$max_logins,
									$remaining
								);
							} else {
								printf(
									/* translators: %d: current login count */
									esc_html__( '%d logins recorded.', 'rgbmind-ephemere' ),
									$login_count
								);
							}
							?>
						</span>
						<div style="margin-top: 6px;">
							<label for="rgbmind_reset_login_count">
								<input type="checkbox" name="rgbmind_reset_login_count" id="rgbmind_reset_login_count" value="1" />
								<span style="color: #646970; font-size: 13px;"><?php esc_html_e( 'Reset login counter on save (restore to 0)', 'rgbmind-ephemere' ); ?></span>
							</label>
						</div>
					</td>
				</tr>

				<!-- 5. Instant lockout checkbox (FREE) -->
				<tr class="rgbmind-ephemere-field">
					<th scope="row">
						<label for="_rgbmind_is_locked"><?php esc_html_e( 'Instant Account Lock', 'rgbmind-ephemere' ); ?></label>
					</th>
					<td>
						<fieldset>
							<label for="_rgbmind_is_locked">
								<input type="checkbox" name="_rgbmind_is_locked" id="_rgbmind_is_locked" value="1" <?php checked( 1, $is_locked ); ?> />
								<strong style="color: #b32d2e;"><?php esc_html_e( 'Instantly block access for this user account', 'rgbmind-ephemere' ); ?></strong>
							</label>
							<p class="description">
								<?php esc_html_e( 'When checked, any login attempt will be immediately rejected with an error regardless of expiration or remaining logins.', 'rgbmind-ephemere' ); ?>
							</p>
						</fieldset>
					</td>
				</tr>

				<?php if ( $is_pro ) : ?>
					<?php do_action( 'rgbmind_eph_render_pro_profile_fields', $user ); ?>
				<?php else : ?>
					<!-- 6. PRO PREVIEW: One-Click Temporary Login Link -->
					<tr class="rgbmind-ephemere-field" style="border-top: 1px solid #dcdcde;">
						<th scope="row">
							<label><?php esc_html_e( 'One-Click Login Link', 'rgbmind-ephemere' ); ?></label>
							<br /><span style="display: inline-block; margin-top: 4px; background: #f0f0f1; color: #50575e; font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 3px; border: 1px solid #c3c4c7;">[PRO]</span>
						</th>
						<td>
							<div style="background: #fcfcfc; border: 1px dashed #c3c4c7; padding: 12px 16px; border-radius: 4px; max-width: 650px;">
								<input type="text" disabled="disabled" value="https://example.com/wp-login.php?rgbmind_magic_auth=••••••••••••••••" class="regular-text" style="width: 100%; opacity: 0.5;" />
								<p class="description" style="margin-top: 8px;">
									<strong><?php esc_html_e( 'Available in PRO:', 'rgbmind-ephemere' ); ?></strong>
									<?php esc_html_e( 'Generate encrypted, single-use login links for direct passwordless access for contractors and clients.', 'rgbmind-ephemere' ); ?>
									<a href="<?php echo esc_url( RGBMIND_EPH_PRO_URL ); ?>" target="_blank" rel="noopener noreferrer" style="font-weight: 600;">
										<?php esc_html_e( 'Learn more at rgbmind.com →', 'rgbmind-ephemere' ); ?>
									</a>
								</p>
							</div>
						</td>
					</tr>

					<!-- 7. PRO PREVIEW: Access Audit Log -->
					<tr class="rgbmind-ephemere-field" style="border-top: 1px solid #dcdcde;">
						<th scope="row">
							<label><?php esc_html_e( 'Access Audit Log', 'rgbmind-ephemere' ); ?></label>
							<br /><span style="display: inline-block; margin-top: 4px; background: #f0f0f1; color: #50575e; font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 3px; border: 1px solid #c3c4c7;">[PRO]</span>
						</th>
						<td>
							<div style="background: #fcfcfc; border: 1px dashed #c3c4c7; padding: 12px 16px; border-radius: 4px; max-width: 650px;">
								<table class="widefat striped" style="opacity: 0.45; pointer-events: none; margin-bottom: 8px;">
									<thead>
										<tr>
											<th>Date & Time</th>
											<th>Method</th>
											<th>IP Address</th>
											<th>Status</th>
										</tr>
									</thead>
									<tbody>
										<tr>
											<td>2026-10-08 10:15:00</td>
											<td>One-Click Link</td>
											<td>192.168.1.xxx</td>
											<td>Success</td>
										</tr>
									</tbody>
								</table>
								<p class="description" style="margin: 0;">
									<strong><?php esc_html_e( 'Available in PRO:', 'rgbmind-ephemere' ); ?></strong>
									<?php esc_html_e( 'Detailed history of the last 10 logins with GDPR-anonymized IP addresses and login methods.', 'rgbmind-ephemere' ); ?>
									<a href="<?php echo esc_url( RGBMIND_EPH_PRO_URL ); ?>" target="_blank" rel="noopener noreferrer" style="font-weight: 600;">
										<?php esc_html_e( 'Learn more at rgbmind.com →', 'rgbmind-ephemere' ); ?>
									</a>
								</p>
							</div>
						</td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>

		<?php if ( ! $is_pro ) : ?>
			<div class="rgbmind-pro-teaser" style="margin-top: 20px; padding: 16px 20px; background: #fff; border-left: 4px solid #2271b1; box-shadow: 0 1px 3px rgba(0,0,0,0.05); max-width: 850px;">
				<div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
					<div>
						<h3 style="margin: 0 0 5px; font-size: 15px; color: #1d2327;">
							🚀 <?php esc_html_e( 'Looking for Agency & Enterprise Access Management?', 'rgbmind-ephemere' ); ?>
						</h3>
						<p style="margin: 0; color: #50575e; font-size: 13px;">
							<?php esc_html_e( 'Unlock passwordless One-Click Magic Links, GDPR-compliant Audit Logs, and instant security email/webhook alerts with RGBmind Ephemere PRO.', 'rgbmind-ephemere' ); ?>
						</p>
					</div>
					<div>
						<a href="<?php echo esc_url( RGBMIND_EPH_PRO_URL ); ?>" target="_blank" rel="noopener noreferrer" class="button button-primary">
							<?php esc_html_e( 'Discover PRO Features', 'rgbmind-ephemere' ); ?> &rarr;
						</a>
					</div>
				</div>
			</div>
		<?php endif; ?>
	</div>
	<?php
}
add_action( 'show_user_profile', 'rgbmind_eph_render_user_profile_fields' );
add_action( 'edit_user_profile', 'rgbmind_eph_render_user_profile_fields' );

/**
 * Saves user profile temporary access configuration.
 *
 * @param int $user_id Saved user ID.
 * @return void
 */
function rgbmind_eph_save_user_profile_fields( $user_id ) {
	$user_id = absint( $user_id );
	if ( ! $user_id ) {
		return;
	}

	// 1. CSRF Nonce check
	if (
		! isset( $_POST['rgbmind_eph_nonce'] ) ||
		! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rgbmind_eph_nonce'] ) ), 'rgbmind_eph_save_profile_fields' )
	) {
		return;
	}

	// 2. Capability check
	if ( ! current_user_can( 'promote_users' ) && ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// 3. _rgbmind_is_ephemeral
	$is_ephemeral = isset( $_POST['_rgbmind_is_ephemeral'] ) ? 1 : 0;
	update_user_meta( $user_id, '_rgbmind_is_ephemeral', $is_ephemeral );

	// 4. _rgbmind_expire_at
	if ( ! empty( $_POST['_rgbmind_expire_at'] ) ) {
		$raw_expire = sanitize_text_field( wp_unslash( $_POST['_rgbmind_expire_at'] ) );
		$timezone   = wp_timezone();

		$datetime = DateTimeImmutable::createFromFormat( 'Y-m-d\TH:i', $raw_expire, $timezone );
		if ( false === $datetime ) {
			$datetime = DateTimeImmutable::createFromFormat( 'Y-m-d\TH:i:s', $raw_expire, $timezone );
		}

		if ( $datetime instanceof DateTimeImmutable ) {
			update_user_meta( $user_id, '_rgbmind_expire_at', $datetime->getTimestamp() );
		} else {
			delete_user_meta( $user_id, '_rgbmind_expire_at' );
		}
	} else {
		delete_user_meta( $user_id, '_rgbmind_expire_at' );
	}

	// 5. _rgbmind_max_logins
	$max_logins = isset( $_POST['_rgbmind_max_logins'] ) ? absint( wp_unslash( $_POST['_rgbmind_max_logins'] ) ) : 0;
	update_user_meta( $user_id, '_rgbmind_max_logins', $max_logins );

	// 6. _rgbmind_is_locked
	$old_locked = (int) get_user_meta( $user_id, '_rgbmind_is_locked', true );
	$is_locked  = isset( $_POST['_rgbmind_is_locked'] ) ? 1 : 0;
	update_user_meta( $user_id, '_rgbmind_is_locked', $is_locked );

	if ( 1 === $is_locked && 0 === $old_locked ) {
		$user_obj = get_user_by( 'id', $user_id );
		if ( $user_obj instanceof WP_User ) {
			do_action( 'rgbmind_eph_account_locked_or_expired', $user_obj, 'manual_lock' );
		}
	}

	// 7. Reset login counter
	if ( ! empty( $_POST['rgbmind_reset_login_count'] ) ) {
		update_user_meta( $user_id, '_rgbmind_login_count', 0 );
	}
}
add_action( 'personal_options_update', 'rgbmind_eph_save_user_profile_fields' );
add_action( 'edit_user_profile_update', 'rgbmind_eph_save_user_profile_fields' );

/* =========================================================================
 * 6. CORE ACCESS & AUTHENTICATION HOOKS
 * ========================================================================= */

/**
 * Checks the access status of a temporary user.
 *
 * @param WP_User|int $user Target WP_User object or user ID.
 * @return true|WP_Error True if access is allowed, WP_Error if blocked.
 */
function rgbmind_eph_check_access( $user ) {
	if ( ! ( $user instanceof WP_User ) ) {
		$user = get_user_by( 'id', absint( $user ) );
	}

	if ( ! ( $user instanceof WP_User ) ) {
		return new WP_Error( 'invalid_user', __( 'User not found.', 'rgbmind-ephemere' ) );
	}

	$is_ephemeral = (int) get_user_meta( $user->ID, '_rgbmind_is_ephemeral', true );
	if ( 1 !== $is_ephemeral ) {
		return true;
	}

	// 1. Condition: Account manually locked
	$is_locked = (int) get_user_meta( $user->ID, '_rgbmind_is_locked', true );
	if ( 1 === $is_locked ) {
		return new WP_Error(
			'rgbmind_account_locked',
			__( '<strong>ACCESS DENIED:</strong> This temporary account is currently locked. Please contact the site administrator.', 'rgbmind-ephemere' )
		);
	}

	// 2. Condition: Expiration time exceeded
	$expire_at = (int) get_user_meta( $user->ID, '_rgbmind_expire_at', true );
	if ( $expire_at > 0 && time() >= $expire_at ) {
		update_user_meta( $user->ID, '_rgbmind_is_locked', 1 );

		do_action( 'rgbmind_eph_account_locked_or_expired', $user, 'expired' );

		return new WP_Error(
			'rgbmind_account_expired',
			__( '<strong>ACCESS DENIED:</strong> The temporary validity period for this account has expired. Access has been revoked.', 'rgbmind-ephemere' )
		);
	}

	// 3. Condition: Maximum login limit reached
	$max_logins  = (int) get_user_meta( $user->ID, '_rgbmind_max_logins', true );
	$login_count = (int) get_user_meta( $user->ID, '_rgbmind_login_count', true );
	if ( $max_logins > 0 && $login_count >= $max_logins ) {
		update_user_meta( $user->ID, '_rgbmind_is_locked', 1 );

		do_action( 'rgbmind_eph_account_locked_or_expired', $user, 'max_logins_reached' );

		return new WP_Error(
			'rgbmind_account_limit_reached',
			__( '<strong>ACCESS DENIED:</strong> This account has reached the maximum number of allowed logins.', 'rgbmind-ephemere' )
		);
	}

	return true;
}

/**
 * Increments the user login counter and records last login timestamp.
 *
 * @param WP_User|int $user WP_User object or user ID.
 * @return int Updated login count.
 */
function rgbmind_eph_increment_login( $user ) {
	$user_id = ( $user instanceof WP_User ) ? $user->ID : absint( $user );
	if ( ! $user_id ) {
		return 0;
	}

	$current_count = (int) get_user_meta( $user_id, '_rgbmind_login_count', true );
	$new_count     = $current_count + 1;

	update_user_meta( $user_id, '_rgbmind_login_count', $new_count );
	update_user_meta( $user_id, '_rgbmind_last_login_time', time() );

	do_action( 'rgbmind_eph_user_login_success', $user_id, 'password' );

	return $new_count;
}

/**
 * Intercepts user authentication to enforce temporary account restrictions.
 *
 * @param WP_User|WP_Error $user User object or error.
 * @param string           $password Provided password.
 * @return WP_User|WP_Error
 */
function rgbmind_eph_authenticate_user( $user, $password ) {
	if ( is_wp_error( $user ) || ! ( $user instanceof WP_User ) ) {
		return $user;
	}

	$check = rgbmind_eph_check_access( $user );
	if ( is_wp_error( $check ) ) {
		return $check;
	}

	return $user;
}
add_filter( 'wp_authenticate_user', 'rgbmind_eph_authenticate_user', 20, 2 );

/**
 * Handles successful user login: increments counter and updates last login time.
 *
 * @param string  $user_login Username of logged in user.
 * @param WP_User $user       Authenticated WP_User object.
 * @return void
 */
function rgbmind_eph_on_successful_login( $user_login, $user ) {
	if ( ! ( $user instanceof WP_User ) ) {
		return;
	}

	$is_ephemeral = (int) get_user_meta( $user->ID, '_rgbmind_is_ephemeral', true );
	if ( 1 !== $is_ephemeral ) {
		return;
	}

	rgbmind_eph_increment_login( $user );
}
add_action( 'wp_login', 'rgbmind_eph_on_successful_login', 10, 2 );

/**
 * Enforces session validity in real time: revokes active session if account expires while browsing.
 *
 * @return void
 */
function rgbmind_eph_enforce_session_validity() {
	if ( ! is_user_logged_in() ) {
		return;
	}

	$user_id      = get_current_user_id();
	$is_ephemeral = (int) get_user_meta( $user_id, '_rgbmind_is_ephemeral', true );

	if ( 1 !== $is_ephemeral ) {
		return;
	}

	$is_locked = (int) get_user_meta( $user_id, '_rgbmind_is_locked', true );
	$expire_at = (int) get_user_meta( $user_id, '_rgbmind_expire_at', true );

	$revoke_session = false;

	if ( 1 === $is_locked ) {
		$revoke_session = true;
	} elseif ( $expire_at > 0 && time() >= $expire_at ) {
		update_user_meta( $user_id, '_rgbmind_is_locked', 1 );
		$user_obj = get_user_by( 'id', $user_id );
		if ( $user_obj instanceof WP_User ) {
			do_action( 'rgbmind_eph_account_locked_or_expired', $user_obj, 'expired' );
		}
		$revoke_session = true;
	}

	if ( $revoke_session ) {
		wp_destroy_current_session();
		wp_clear_auth_cookie();
		wp_set_current_user( 0 );

		$redirect_url = add_query_arg(
			'rgbmind_session_expired',
			'1',
			wp_login_url()
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}
}
add_action( 'init', 'rgbmind_eph_enforce_session_validity' );

/**
 * Displays custom login messages on wp-login.php.
 *
 * @param string $message Original HTML message.
 * @return string
 */
function rgbmind_eph_custom_login_messages( $message ) {
	if ( isset( $_GET['rgbmind_session_expired'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['rgbmind_session_expired'] ) ) ) {
		$message .= '<div class="message notice notice-error"><p>' .
			esc_html__( 'Your temporary access session has expired or was revoked by an administrator.', 'rgbmind-ephemere' ) .
			'</p></div>';
	}

	if ( isset( $_GET['rgbmind_login_error'] ) ) {
		$err     = sanitize_text_field( wp_unslash( $_GET['rgbmind_login_error'] ) );
		$err_msg = '';

		switch ( $err ) {
			case 'token_expired':
				$err_msg = __( 'This magic login link has expired. Please request a new one from your administrator.', 'rgbmind-ephemere' );
				break;
			case 'token_invalid':
			case 'invalid_link':
				$err_msg = __( 'Invalid or already consumed magic login link.', 'rgbmind-ephemere' );
				break;
			case 'account_restricted':
				$err_msg = __( 'The account associated with this link is locked or expired.', 'rgbmind-ephemere' );
				break;
			case 'pro_required':
				$err_msg = __( 'One-Click magic login requires an active PRO license on this site.', 'rgbmind-ephemere' );
				break;
		}

		if ( ! empty( $err_msg ) ) {
			$message .= '<div class="message notice notice-error"><p><strong>' . esc_html__( 'Error:', 'rgbmind-ephemere' ) . '</strong> ' . esc_html( $err_msg ) . '</p></div>';
		}
	}

	return $message;
}
add_filter( 'login_message', 'rgbmind_eph_custom_login_messages' );

/* =========================================================================
 * 7. PRIMARY ADMIN MENU & DASHBOARD (TWO-TAB INTERFACE)
 * ========================================================================= */

/**
 * Registers the primary administrative menu item "RGBmind Ephemere".
 *
 * Placed in the primary WordPress sidebar with manage_options capability.
 *
 * @return void
 */
function rgbmind_eph_register_admin_menu() {
	add_menu_page(
		__( 'RGBmind Ephemere', 'rgbmind-ephemere' ),
		__( 'RGBmind Ephemere', 'rgbmind-ephemere' ),
		'manage_options',
		'rgbmind-ephemere',
		'rgbmind_eph_render_dashboard',
		'dashicons-clock',
		75
	);
}
add_action( 'admin_menu', 'rgbmind_eph_register_admin_menu' );

/**
 * Renders the Administrative Dashboard with Two Tabs:
 *  - Tab 1: Temporary Accounts (Account Temporanei)
 *  - Tab 2: License & PRO Settings (Licenza & Impostazioni PRO)
 *
 * @return void
 */
function rgbmind_eph_render_dashboard() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'rgbmind-ephemere' ) );
	}

	// 1. Handle Quick Lock/Unlock Action via GET
	if ( isset( $_GET['action'] ) && 'toggle_lock' === $_GET['action'] && isset( $_GET['user_id'] ) ) {
		$target_uid = absint( $_GET['user_id'] );
		check_admin_referer( 'rgbmind_eph_toggle_lock_' . $target_uid );

		$current_locked = (int) get_user_meta( $target_uid, '_rgbmind_is_locked', true );
		$new_locked     = ( 1 === $current_locked ) ? 0 : 1;
		update_user_meta( $target_uid, '_rgbmind_is_locked', $new_locked );

		if ( 1 === $new_locked ) {
			$target_user = get_user_by( 'id', $target_uid );
			if ( $target_user instanceof WP_User ) {
				do_action( 'rgbmind_eph_account_locked_or_expired', $target_user, 'manual_lock' );
			}
		}

		$redirect_url = remove_query_arg( array( 'action', 'user_id', '_wpnonce' ) );
		wp_safe_redirect( add_query_arg( 'rgbmind_notice', $new_locked ? 'user_locked' : 'user_unlocked', $redirect_url ) );
		exit;
	}

	// 2. Handle Settings Form Submission via POST
	$notice      = '';
	$notice_type = '';

	if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['rgbmind_eph_settings_nonce'] ) ) {
		if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rgbmind_eph_settings_nonce'] ) ), 'rgbmind_eph_save_settings' ) ) {

			// Action: Save Language Preference
			if ( isset( $_POST['rgbmind_eph_save_language'] ) && isset( $_POST['rgbmind_eph_language'] ) ) {
				$chosen_lang = sanitize_text_field( wp_unslash( $_POST['rgbmind_eph_language'] ) );
				$supported   = rgbmind_eph_get_supported_languages();
				if ( array_key_exists( $chosen_lang, $supported ) ) {
					update_option( 'rgbmind_eph_language', $chosen_lang );
					unload_textdomain( 'rgbmind-ephemere' );
					rgbmind_eph_load_textdomain();
					$notice      = __( 'Language preference updated successfully.', 'rgbmind-ephemere' );
					$notice_type = 'success';
				}
			}
		}
	}

	// 3. Query notices
	if ( isset( $_GET['rgbmind_notice'] ) ) {
		$code = sanitize_text_field( wp_unslash( $_GET['rgbmind_notice'] ) );
		if ( 'user_locked' === $code ) {
			$notice      = __( 'Account locked successfully.', 'rgbmind-ephemere' );
			$notice_type = 'warning';
		} elseif ( 'user_unlocked' === $code ) {
			$notice      = __( 'Account unlocked successfully.', 'rgbmind-ephemere' );
			$notice_type = 'success';
		}
	}

	if ( isset( $_GET['rgbmind_pro_notice'] ) ) {
		$pro_code = sanitize_text_field( wp_unslash( $_GET['rgbmind_pro_notice'] ) );
		switch ( $pro_code ) {
			case 'activated':
				$notice      = __( '🎉 Congratulations! PRO license activated successfully. All premium features are now unlocked.', 'rgbmind-ephemere' );
				$notice_type = 'success';
				break;
			case 'invalid_key':
				$notice      = __( '❌ Invalid license key. Please check your key or contact support.', 'rgbmind-ephemere' );
				$notice_type = 'error';
				break;
			case 'deactivated':
				$notice      = __( 'PRO license deactivated. The plugin has returned to the standard mode.', 'rgbmind-ephemere' );
				$notice_type = 'info';
				break;
			case 'saved':
				$notice      = __( 'PRO settings updated successfully.', 'rgbmind-ephemere' );
				$notice_type = 'success';
				break;
		}
	}

	// Active tab determination
	$active_tab = isset( $_GET['tab'] ) && ( 'settings' === $_GET['tab'] || 'license' === $_GET['tab'] ) ? 'settings' : 'accounts';
	$is_pro     = rgbmind_eph_is_pro_active();
	?>
	<div class="wrap">
		<h1 style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
			<span style="display: flex; align-items: center; gap: 10px;">
				<span>⏱️ <?php esc_html_e( 'RGBmind Ephemere – Management Dashboard', 'rgbmind-ephemere' ); ?></span>
				<span style="font-size: 11px; font-weight: 600; color: #50575e; background: #f0f0f1; border: 1px solid #dcdcde; padding: 2px 8px; border-radius: 10px; letter-spacing: 0.5px;">v<?php echo esc_html( RGBMIND_EPH_VERSION ); ?></span>
			</span>
			<?php if ( $is_pro ) : ?>
				<span style="background: #e6f4ea; color: #137333; font-weight: 700; font-size: 12px; padding: 5px 12px; border-radius: 12px; border: 1px solid #ceead6; letter-spacing: 0.5px;">
					✔ PRO LICENSE ACTIVE
				</span>
			<?php else : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=rgbmind-ephemere&tab=settings' ) ); ?>" style="text-decoration: none;">
					<span style="background: #fef7e0; color: #b06000; font-weight: 700; font-size: 12px; padding: 5px 12px; border-radius: 12px; border: 1px solid #fce8b2; letter-spacing: 0.5px;">
						FREE VERSION (UPGRADE PRO)
					</span>
				</a>
			<?php endif; ?>
		</h1>

		<?php if ( ! empty( $notice ) ) : ?>
			<div class="notice notice-<?php echo esc_attr( $notice_type ); ?> is-dismissible" style="margin-top: 15px;">
				<p><?php echo esc_html( $notice ); ?></p>
			</div>
		<?php endif; ?>

		<!-- Tab Navigation -->
		<nav class="nav-tab-wrapper" style="margin-top: 15px; margin-bottom: 20px;">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=rgbmind-ephemere&tab=accounts' ) ); ?>" class="nav-tab <?php echo 'accounts' === $active_tab ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'Temporary Accounts', 'rgbmind-ephemere' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=rgbmind-ephemere&tab=settings' ) ); ?>" class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'Settings & Extensions', 'rgbmind-ephemere' ); ?>
			</a>
		</nav>

		<!-- TAB 1: TEMPORARY ACCOUNTS -->
		<?php if ( 'accounts' === $active_tab ) : ?>
			<?php
			$ephemeral_users = get_users(
				array(
					'meta_key'   => '_rgbmind_is_ephemeral',
					'meta_value' => 1,
					'number'     => 150,
				)
			);
			?>

			<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px;">
				<p style="margin: 0; color: #50575e; font-size: 13.5px;">
					<?php
					printf(
						/* translators: %d: total temporary accounts count */
						esc_html__( 'Total temporary accounts configured: %d.', 'rgbmind-ephemere' ),
						count( $ephemeral_users )
					);
					?>
				</p>
				<a href="<?php echo esc_url( admin_url( 'users.php' ) ); ?>" class="button button-primary">
					<?php esc_html_e( 'Manage All WordPress Users', 'rgbmind-ephemere' ); ?> &rarr;
				</a>
			</div>

			<?php if ( empty( $ephemeral_users ) ) : ?>
				<div class="card" style="padding: 30px; text-align: center; max-width: none;">
					<h3 style="margin-top: 0; color: #1d2327;">
						<?php esc_html_e( 'No Temporary Accounts Found', 'rgbmind-ephemere' ); ?>
					</h3>
					<p style="color: #646970; max-width: 500px; margin: 10px auto;">
						<?php esc_html_e( 'None of your users are currently configured as temporary accounts. You can edit any existing user profile to enable time limits and access restrictions.', 'rgbmind-ephemere' ); ?>
					</p>
					<a href="<?php echo esc_url( admin_url( 'users.php' ) ); ?>" class="button button-primary" style="margin-top: 10px;">
						<?php esc_html_e( 'Go to Users Screen', 'rgbmind-ephemere' ); ?>
					</a>
				</div>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped table-view-list" style="box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
					<thead>
						<tr>
							<th style="width: 20%;"><?php esc_html_e( 'Username / User', 'rgbmind-ephemere' ); ?></th>
							<th style="width: 20%;"><?php esc_html_e( 'Email', 'rgbmind-ephemere' ); ?></th>
							<th style="width: 18%;"><?php esc_html_e( 'Expiration Date/Time', 'rgbmind-ephemere' ); ?></th>
							<th style="width: 14%;"><?php esc_html_e( 'Login Count', 'rgbmind-ephemere' ); ?></th>
							<th style="width: 13%;"><?php esc_html_e( 'Status', 'rgbmind-ephemere' ); ?></th>
							<th style="width: 15%; text-align: right;"><?php esc_html_e( 'Quick Actions', 'rgbmind-ephemere' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						$now = time();
						foreach ( $ephemeral_users as $u ) :
							$u_id        = $u->ID;
							$u_locked    = (int) get_user_meta( $u_id, '_rgbmind_is_locked', true );
							$u_expire    = (int) get_user_meta( $u_id, '_rgbmind_expire_at', true );
							$u_max       = (int) get_user_meta( $u_id, '_rgbmind_max_logins', true );
							$u_count     = (int) get_user_meta( $u_id, '_rgbmind_login_count', true );
							$u_last      = (int) get_user_meta( $u_id, '_rgbmind_last_login_time', true );

							// Status resolution
							$status_label = __( 'Active', 'rgbmind-ephemere' );
							$status_style = 'color: #007017; font-weight: 600;';
							$status_icon  = '&#x2705;';

							if ( 1 === $u_locked ) {
								$status_label = __( 'Locked', 'rgbmind-ephemere' );
								$status_style = 'color: #b32d2e; font-weight: 600;';
								$status_icon  = '&#x1F512;';
							} elseif ( $u_expire > 0 && $now >= $u_expire ) {
								$status_label = __( 'Expired', 'rgbmind-ephemere' );
								$status_style = 'color: #996800; font-weight: 600;';
								$status_icon  = '&#x23F0;';
							} elseif ( $u_max > 0 && $u_count >= $u_max ) {
								$status_label = __( 'Limit reached', 'rgbmind-ephemere' );
								$status_style = 'color: #996800; font-weight: 600;';
								$status_icon  = '&#x26A0;&#xFE0F;';
							}

							// Toggle lock link with nonce
							$toggle_nonce = wp_create_nonce( 'rgbmind_eph_toggle_lock_' . $u_id );
							$toggle_url   = add_query_arg(
								array(
									'action'   => 'toggle_lock',
									'user_id'  => $u_id,
									'_wpnonce' => $toggle_nonce,
								),
								admin_url( 'admin.php?page=rgbmind-ephemere&tab=accounts' )
							);
							?>
							<tr>
								<td>
									<strong>
										<a href="<?php echo esc_url( admin_url( 'user-edit.php?user_id=' . $u_id ) ); ?>">
											<?php echo esc_html( $u->user_login ); ?>
										</a>
									</strong>
									<br />
									<small style="color: #646970;"><?php echo esc_html( $u->display_name ); ?></small>
								</td>
								<td>
									<a href="mailto:<?php echo esc_attr( $u->user_email ); ?>">
										<?php echo esc_html( $u->user_email ); ?>
									</a>
								</td>
								<td>
									<?php if ( $u_expire > 0 ) : ?>
										<span><?php echo esc_html( wp_date( 'Y-m-d H:i', $u_expire ) ); ?></span>
										<br />
										<small style="color: <?php echo ( $now >= $u_expire ) ? '#d63638' : '#646970'; ?>;">
											<?php
											if ( $now >= $u_expire ) {
												printf( esc_html__( 'Expired %s ago', 'rgbmind-ephemere' ), esc_html( human_time_diff( $u_expire, $now ) ) );
											} else {
												printf( esc_html__( 'in %s', 'rgbmind-ephemere' ), esc_html( human_time_diff( $now, $u_expire ) ) );
											}
											?>
										</small>
									<?php else : ?>
										<span style="color: #646970;"><?php esc_html_e( 'No time limit', 'rgbmind-ephemere' ); ?></span>
									<?php endif; ?>
								</td>
								<td>
									<strong><?php echo esc_html( $u_count ); ?></strong>
									/
									<span><?php echo ( $u_max > 0 ) ? esc_html( $u_max ) : esc_html__( 'Unlimited', 'rgbmind-ephemere' ); ?></span>
									<?php if ( $u_last > 0 ) : ?>
										<br />
										<small style="color: #646970;">
											<?php printf( esc_html__( 'Last: %s ago', 'rgbmind-ephemere' ), esc_html( human_time_diff( $u_last, $now ) ) ); ?>
										</small>
									<?php endif; ?>
								</td>
								<td>
									<span style="<?php echo esc_attr( $status_style ); ?>">
										<?php echo $status_icon . ' ' . esc_html( $status_label ); ?>
									</span>
								</td>
								<td style="text-align: right;">
									<div style="display: flex; gap: 8px; justify-content: flex-end; align-items: center;">
										<?php if ( 1 === $u_locked ) : ?>
											<a href="<?php echo esc_url( $toggle_url ); ?>" class="button button-small" style="color: #007017; border-color: #007017;">
												<?php esc_html_e( 'Unlock', 'rgbmind-ephemere' ); ?>
											</a>
										<?php else : ?>
											<a href="<?php echo esc_url( $toggle_url ); ?>" class="button button-small" style="color: #b32d2e; border-color: #b32d2e;">
												<?php esc_html_e( 'Lock', 'rgbmind-ephemere' ); ?>
											</a>
										<?php endif; ?>

										<a href="<?php echo esc_url( admin_url( 'user-edit.php?user_id=' . $u_id ) ); ?>" class="button button-small">
											<?php esc_html_e( 'Edit', 'rgbmind-ephemere' ); ?>
										</a>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

		<!-- TAB 2: SETTINGS & PRO EXTENSIONS -->
		<?php else : ?>
			<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; max-width: 1200px;">
				<!-- Main Column -->
				<div>
					<!-- Language Settings Box -->
					<div class="card" style="margin: 0 0 20px 0; padding: 20px; max-width: none;">
						<h2 style="margin-top: 0; padding-bottom: 10px; border-bottom: 1px solid #f0f0f1;">
							🌐 <?php esc_html_e( 'Plugin Language Settings', 'rgbmind-ephemere' ); ?>
						</h2>
						<p style="color: #50575e; font-size: 13.5px; margin-top: 10px;">
							<?php esc_html_e( 'Choose whether RGBmind Ephemere should automatically match your WordPress locale or use a specific language.', 'rgbmind-ephemere' ); ?>
						</p>

						<form method="post" action="">
							<?php wp_nonce_field( 'rgbmind_eph_save_settings', 'rgbmind_eph_settings_nonce' ); ?>
							<table class="form-table" role="presentation">
								<tr>
									<th scope="row">
										<label for="rgbmind_eph_language"><?php esc_html_e( 'Select Language', 'rgbmind-ephemere' ); ?></label>
									</th>
									<td>
										<select name="rgbmind_eph_language" id="rgbmind_eph_language" style="min-width: 280px;">
											<?php
											$current_lang = get_option( 'rgbmind_eph_language', 'auto' );
											$supported    = rgbmind_eph_get_supported_languages();
											foreach ( $supported as $lang_code => $lang_label ) :
											?>
												<option value="<?php echo esc_attr( $lang_code ); ?>" <?php selected( $current_lang, $lang_code ); ?>>
													<?php echo esc_html( $lang_label ); ?>
												</option>
											<?php endforeach; ?>
										</select>
										<p class="description">
											<?php esc_html_e( 'Supported: English (US), Italian, Spanish, French, German, and Brazilian Portuguese.', 'rgbmind-ephemere' ); ?>
										</p>
									</td>
								</tr>
							</table>
							<p class="submit" style="margin-bottom: 0; padding-bottom: 0;">
								<input type="submit" name="rgbmind_eph_save_language" class="button button-secondary" value="<?php esc_attr_e( 'Save Language Preference', 'rgbmind-ephemere' ); ?>" />
							</p>
						</form>
					</div>

					<!-- PRO Hook for Companion Add-on Settings -->
					<?php do_action( 'rgbmind_eph_render_dashboard_pro_settings' ); ?>

					<?php if ( ! $is_pro ) : ?>
						<!-- Informational Showcase: PRO Features Preview -->
						<div class="card" style="margin: 0; padding: 20px; max-width: none; background: #fafafa; border-left: 4px solid #2271b1;">
							<h2 style="margin-top: 0; padding-bottom: 10px; border-bottom: 1px solid #e2e4e7; color: #1d2327;">
								✨ <?php esc_html_e( 'Available Commercial Add-on: RGBmind Ephemere PRO', 'rgbmind-ephemere' ); ?>
							</h2>
							<p style="color: #50575e; font-size: 13.5px;">
								<?php esc_html_e( 'Looking to enhance your temporary access workflow? RGBmind Ephemere PRO is available as a separate companion plugin featuring:', 'rgbmind-ephemere' ); ?>
							</p>

							<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin: 15px 0;">
								<div style="background: #fff; padding: 12px 16px; border: 1px solid #dcdcde; border-radius: 4px;">
									<strong style="display: block; font-size: 13px; color: #1d2327; margin-bottom: 4px;">
										⚡ <?php esc_html_e( 'One-Click Magic Links', 'rgbmind-ephemere' ); ?>
									</strong>
									<span style="font-size: 12.5px; color: #646970;">
										<?php esc_html_e( 'Create single-use, cryptographic login links for passwordless contractor access.', 'rgbmind-ephemere' ); ?>
									</span>
								</div>
								<div style="background: #fff; padding: 12px 16px; border: 1px solid #dcdcde; border-radius: 4px;">
									<strong style="display: block; font-size: 13px; color: #1d2327; margin-bottom: 4px;">
										🛡️ <?php esc_html_e( 'GDPR Access Audit Log', 'rgbmind-ephemere' ); ?>
									</strong>
									<span style="font-size: 12.5px; color: #646970;">
										<?php esc_html_e( 'Track the last 10 logins per user with anonymized IPs and authentication methods.', 'rgbmind-ephemere' ); ?>
									</span>
								</div>
								<div style="background: #fff; padding: 12px 16px; border: 1px solid #dcdcde; border-radius: 4px;">
									<strong style="display: block; font-size: 13px; color: #1d2327; margin-bottom: 4px;">
										🔔 <?php esc_html_e( 'Email & Webhook Alerts', 'rgbmind-ephemere' ); ?>
									</strong>
									<span style="font-size: 12.5px; color: #646970;">
										<?php esc_html_e( 'Get instant notifications on account expiration or lockout (Slack, Discord, Zapier).', 'rgbmind-ephemere' ); ?>
									</span>
								</div>
								<div style="background: #fff; padding: 12px 16px; border: 1px solid #dcdcde; border-radius: 4px;">
									<strong style="display: block; font-size: 13px; color: #1d2327; margin-bottom: 4px;">
										🚀 <?php esc_html_e( 'Priority Direct Support', 'rgbmind-ephemere' ); ?>
									</strong>
									<span style="font-size: 12.5px; color: #646970;">
										<?php esc_html_e( 'Dedicated technical assistance from our development and security team.', 'rgbmind-ephemere' ); ?>
									</span>
								</div>
							</div>

							<p style="margin-top: 15px; margin-bottom: 0;">
								<a href="<?php echo esc_url( RGBMIND_EPH_PRO_URL ); ?>" target="_blank" rel="noopener noreferrer" class="button button-primary">
									<?php esc_html_e( 'Learn more about Ephemere PRO at rgbmind.com', 'rgbmind-ephemere' ); ?> &rarr;
								</a>
							</p>
						</div>
					<?php endif; ?>
				</div>

				<!-- Sidebar: Plugin Info -->
				<div>
					<div class="card" style="margin: 0; padding: 20px; background: #fff; border-top: 4px solid #2271b1;">
						<h3 style="margin-top: 0; font-size: 16px; color: #1d2327;">
							<?php esc_html_e( 'About RGBmind Ephemere', 'rgbmind-ephemere' ); ?>
						</h3>
						<p style="color: #50575e; font-size: 13px; line-height: 1.5;">
							<?php esc_html_e( 'RGBmind Ephemere provides secure, native temporary user access management for WordPress sites with zero external tracking and zero bloated database tables.', 'rgbmind-ephemere' ); ?>
						</p>

						<ul style="margin: 15px 0; padding-left: 20px; color: #2c3338; font-size: 13px; line-height: 1.7;">
							<li><strong>✔ <?php esc_html_e( '100% Free Core:', 'rgbmind-ephemere' ); ?></strong> <?php esc_html_e( 'Expiration dates, login counters, instant lockouts.', 'rgbmind-ephemere' ); ?></li>
							<li><strong>✔ <?php esc_html_e( 'Session Revocation:', 'rgbmind-ephemere' ); ?></strong> <?php esc_html_e( 'Active expired sessions terminated in real time.', 'rgbmind-ephemere' ); ?></li>
							<li><strong>✔ <?php esc_html_e( 'Clean Architecture:', 'rgbmind-ephemere' ); ?></strong> <?php esc_html_e( 'Uses standard WordPress user metadata.', 'rgbmind-ephemere' ); ?></li>
							<li><strong>✔ <?php esc_html_e( 'Internationalized:', 'rgbmind-ephemere' ); ?></strong> <?php esc_html_e( '6 languages supported out of the box.', 'rgbmind-ephemere' ); ?></li>
						</ul>

						<div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #f0f0f1; text-align: center;">
							<a href="<?php echo esc_url( RGBMIND_EPH_PRO_URL ); ?>" target="_blank" rel="noopener noreferrer" class="button button-secondary" style="width: 100%; text-align: center; text-decoration: none;">
								<?php esc_html_e( 'Visit RGBmind.com →', 'rgbmind-ephemere' ); ?>
							</a>
							<p style="margin: 12px 0 0; color: #8c8f94; font-size: 11px;">
								RGBmind Ephemere v<?php echo esc_html( RGBMIND_EPH_VERSION ); ?>
							</p>
						</div>
					</div>
				</div>
			</div>
		<?php endif; ?>
	</div>
	<?php
}

/* =========================================================================
 * 8. USERS TABLE, PLUGIN ACTION LINKS & ONBOARDING
 * ========================================================================= */

/**
 * Adds a custom status column to the WordPress users table.
 *
 * @param array $columns Existing columns.
 * @return array
 */
function rgbmind_eph_add_users_column( $columns ) {
	if ( ! current_user_can( 'promote_users' ) && ! current_user_can( 'manage_options' ) ) {
		return $columns;
	}

	$columns['rgbmind_eph_status'] = __( 'Temporary Access', 'rgbmind-ephemere' );
	return $columns;
}
add_filter( 'manage_users_columns', 'rgbmind_eph_add_users_column' );

/**
 * Renders the custom status column value in the users list table.
 *
 * @param string $output      Current column output.
 * @param string $column_name Column name.
 * @param int    $user_id     Row user ID.
 * @return string
 */
function rgbmind_eph_render_users_column( $output, $column_name, $user_id ) {
	if ( 'rgbmind_eph_status' !== $column_name ) {
		return $output;
	}

	if ( ! current_user_can( 'promote_users' ) && ! current_user_can( 'manage_options' ) ) {
		return $output;
	}

	$is_ephemeral = (int) get_user_meta( $user_id, '_rgbmind_is_ephemeral', true );
	if ( 1 !== $is_ephemeral ) {
		return '<span style="color: #a7aaad;">—</span>';
	}

	$is_locked   = (int) get_user_meta( $user_id, '_rgbmind_is_locked', true );
	$expire_at   = (int) get_user_meta( $user_id, '_rgbmind_expire_at', true );
	$max_logins  = (int) get_user_meta( $user_id, '_rgbmind_max_logins', true );
	$login_count = (int) get_user_meta( $user_id, '_rgbmind_login_count', true );
	$now_ts      = time();

	if ( 1 === $is_locked ) {
		return '<span style="color: #b32d2e; font-weight: 600;">&#x1F512; ' . esc_html__( 'Locked', 'rgbmind-ephemere' ) . '</span>';
	}

	if ( $expire_at > 0 && $now_ts >= $expire_at ) {
		return '<span style="color: #996800; font-weight: 600;">&#x23F0; ' . esc_html__( 'Expired', 'rgbmind-ephemere' ) . '</span>';
	}

	if ( $max_logins > 0 && $login_count >= $max_logins ) {
		return '<span style="color: #996800; font-weight: 600;">&#x26A0;&#xFE0F; ' . esc_html__( 'Logins exhausted', 'rgbmind-ephemere' ) . '</span>';
	}

	$details = array();
	if ( $expire_at > 0 ) {
		$details[] = sprintf(
			/* translators: %s: remaining time */
			__( 'expires in %s', 'rgbmind-ephemere' ),
			human_time_diff( $now_ts, $expire_at )
		);
	}
	if ( $max_logins > 0 ) {
		$details[] = sprintf( '%d/%d logins', $login_count, $max_logins );
	}

	$details_text = ! empty( $details ) ? ' (' . implode( ', ', $details ) . ')' : '';

	return '<span style="color: #007017; font-weight: 600;">&#x2705; ' . esc_html__( 'Active', 'rgbmind-ephemere' ) . '</span>' .
		'<br /><small style="color: #646970;">' . esc_html( $details_text ) . '</small>';
}
add_filter( 'manage_users_custom_column', 'rgbmind_eph_render_users_column', 10, 3 );

/**
 * Adds action links in the plugins list table (wp-admin/plugins.php).
 *
 * @param array $links Existing plugin links.
 * @return array
 */
function rgbmind_eph_add_plugin_action_links( $links ) {
	if ( current_user_can( 'manage_options' ) ) {
		$dashboard_link = '<a href="' . esc_url( admin_url( 'admin.php?page=rgbmind-ephemere' ) ) . '">' . esc_html__( 'Dashboard', 'rgbmind-ephemere' ) . '</a>';
		$users_link     = '<a href="' . esc_url( admin_url( 'users.php' ) ) . '">' . esc_html__( 'Manage Users', 'rgbmind-ephemere' ) . '</a>';
		array_unshift( $links, $dashboard_link, $users_link );
	}
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( RGBMIND_EPH_PLUGIN_FILE ), 'rgbmind_eph_add_plugin_action_links' );

/**
 * Activation hook: enables welcome guide notice only on initial installation.
 *
 * @return void
 */
function rgbmind_eph_activate() {
	$already_dismissed = get_option( 'rgbmind_eph_welcome_dismissed', false );
	if ( ! $already_dismissed ) {
		update_option( 'rgbmind_eph_show_welcome_guide', 1 );
	}
}
register_activation_hook( RGBMIND_EPH_PLUGIN_FILE, 'rgbmind_eph_activate' );

/**
 * First-run check for welcome guide notice.
 *
 * @return void
 */
function rgbmind_eph_check_first_install() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$dismissed = get_option( 'rgbmind_eph_welcome_dismissed', false );
	$shown     = get_option( 'rgbmind_eph_show_welcome_guide', false );

	if ( false === $dismissed && false === $shown ) {
		update_option( 'rgbmind_eph_show_welcome_guide', 1 );
	}
}
add_action( 'admin_init', 'rgbmind_eph_check_first_install' );

/**
 * Renders the interactive welcome guide notice on first installation.
 *
 * @return void
 */
function rgbmind_eph_render_welcome_guide_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( ! get_option( 'rgbmind_eph_show_welcome_guide', false ) ) {
		return;
	}

	$dismiss_nonce = wp_create_nonce( 'rgbmind_eph_dismiss_welcome' );
	$dismiss_url   = add_query_arg(
		array(
			'rgbmind_eph_action' => 'dismiss_welcome',
			'_wpnonce'           => $dismiss_nonce,
		),
		admin_url()
	);
	$dashboard_url = admin_url( 'admin.php?page=rgbmind-ephemere' );
	?>
	<div id="rgbmind-ephemere-welcome-guide" data-nonce="<?php echo esc_attr( $dismiss_nonce ); ?>" class="notice notice-info is-dismissible" style="padding: 20px; border-left-color: #2271b1; background: #ffffff; box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-top: 20px; margin-bottom: 20px;">
		<div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #f0f0f1; padding-bottom: 12px; margin-bottom: 16px;">
			<div style="display: flex; align-items: center; gap: 12px;">
				<span style="font-size: 26px; line-height: 1;">⏱️</span>
				<div>
					<h3 style="margin: 0; font-size: 17px; color: #1d2327; font-weight: 600;">
						<?php esc_html_e( 'Welcome to RGBmind Ephemere – Quick Start Guide', 'rgbmind-ephemere' ); ?>
					</h3>
					<p style="margin: 3px 0 0; color: #646970; font-size: 13px;">
						<?php esc_html_e( 'Your lightweight, secure, and local system for managing temporary and time-limited access.', 'rgbmind-ephemere' ); ?>
					</p>
				</div>
			</div>
			<span style="background: #e7f3fb; color: #135e96; font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 12px; text-transform: uppercase; letter-spacing: 0.5px;">
				<?php esc_html_e( 'First Installation', 'rgbmind-ephemere' ); ?>
			</span>
		</div>

		<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 18px;">
			<div style="background: #f8f9fa; border: 1px solid #e2e4e7; border-radius: 6px; padding: 14px;">
				<div style="font-size: 14px; font-weight: 600; color: #1d2327; margin-bottom: 6px;">
					<span style="color: #2271b1;">1.</span> <?php esc_html_e( 'Select User', 'rgbmind-ephemere' ); ?>
				</div>
				<p style="margin: 0; font-size: 12.5px; color: #50575e; line-height: 1.5;">
					<?php esc_html_e( 'Navigate to Users > All Users and click "Edit" on the user account you want to make temporary.', 'rgbmind-ephemere' ); ?>
				</p>
			</div>

			<div style="background: #f8f9fa; border: 1px solid #e2e4e7; border-radius: 6px; padding: 14px;">
				<div style="font-size: 14px; font-weight: 600; color: #1d2327; margin-bottom: 6px;">
					<span style="color: #2271b1;">2.</span> <?php esc_html_e( 'Enable Restrictions', 'rgbmind-ephemere' ); ?>
				</div>
				<p style="margin: 0; font-size: 12.5px; color: #50575e; line-height: 1.5;">
					<?php esc_html_e( 'In the "RGBmind Ephemere" box, check "Enable temporary access restrictions" to activate checks.', 'rgbmind-ephemere' ); ?>
				</p>
			</div>

			<div style="background: #f8f9fa; border: 1px solid #e2e4e7; border-radius: 6px; padding: 14px;">
				<div style="font-size: 14px; font-weight: 600; color: #1d2327; margin-bottom: 6px;">
					<span style="color: #2271b1;">3.</span> <?php esc_html_e( 'Set Expiration & Limits', 'rgbmind-ephemere' ); ?>
				</div>
				<p style="margin: 0; font-size: 12.5px; color: #50575e; line-height: 1.5;">
					<?php esc_html_e( 'Define an expiration date and time, or set the maximum number of logins allowed before automatic lockout.', 'rgbmind-ephemere' ); ?>
				</p>
			</div>

			<div style="background: #f8f9fa; border: 1px solid #e2e4e7; border-radius: 6px; padding: 14px;">
				<div style="font-size: 14px; font-weight: 600; color: #1d2327; margin-bottom: 6px;">
					<span style="color: #2271b1;">4.</span> <?php esc_html_e( 'Monitor & Lock', 'rgbmind-ephemere' ); ?>
				</div>
				<p style="margin: 0; font-size: 12.5px; color: #50575e; line-height: 1.5;">
					<?php esc_html_e( 'Monitor status in the Users table or instantly block access at any time using the Instant Lock switch.', 'rgbmind-ephemere' ); ?>
				</p>
			</div>
		</div>

		<div style="background: #f0f6fc; border-left: 3px solid #2271b1; padding: 8px 12px; margin-bottom: 16px; font-size: 12.5px; color: #1d2327;">
			<strong>🛡️ <?php esc_html_e( 'Cybersecurity Hardening:', 'rgbmind-ephemere' ); ?></strong>
			<?php esc_html_e( 'Active expired sessions are revoked immediately. Zero custom tables created, zero external calls, and full GDPR compliance.', 'rgbmind-ephemere' ); ?>
		</div>

		<div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
			<a href="<?php echo esc_url( $dashboard_url ); ?>" class="button button-primary">
				<?php esc_html_e( 'Open Ephemere Dashboard', 'rgbmind-ephemere' ); ?> &rarr;
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=rgbmind-ephemere&tab=settings' ) ); ?>" class="button button-secondary">
				<?php esc_html_e( 'Settings & Extensions', 'rgbmind-ephemere' ); ?>
			</a>
			<a href="<?php echo esc_url( $dismiss_url ); ?>" id="rgbmind-dismiss-guide-btn" class="button button-link" style="color: #646970;">
				<?php esc_html_e( 'Do not show again', 'rgbmind-ephemere' ); ?>
			</a>
		</div>
	</div>
	<?php
}
add_action( 'admin_notices', 'rgbmind_eph_render_welcome_guide_notice' );

/**
 * Handles welcome guide dismissal via GET request.
 *
 * @return void
 */
function rgbmind_eph_handle_dismiss_welcome_get() {
	if (
		isset( $_GET['rgbmind_eph_action'] ) &&
		'dismiss_welcome' === sanitize_text_field( wp_unslash( $_GET['rgbmind_eph_action'] ) )
	) {
		check_admin_referer( 'rgbmind_eph_dismiss_welcome' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'rgbmind-ephemere' ) );
		}

		delete_option( 'rgbmind_eph_show_welcome_guide' );
		update_option( 'rgbmind_eph_welcome_dismissed', 1 );

		$redirect_url = remove_query_arg( array( 'rgbmind_eph_action', '_wpnonce' ) );
		wp_safe_redirect( $redirect_url );
		exit;
	}
}
add_action( 'admin_init', 'rgbmind_eph_handle_dismiss_welcome_get' );

/**
 * Handles welcome guide dismissal via AJAX.
 *
 * @return void
 */
function rgbmind_eph_ajax_dismiss_welcome() {
	check_ajax_referer( 'rgbmind_eph_dismiss_welcome', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Unauthorized operation.', 'rgbmind-ephemere' ) ), 403 );
	}

	delete_option( 'rgbmind_eph_show_welcome_guide' );
	update_option( 'rgbmind_eph_welcome_dismissed', 1 );

	wp_send_json_success();
}
add_action( 'wp_ajax_rgbmind_eph_dismiss_welcome', 'rgbmind_eph_ajax_dismiss_welcome' );
