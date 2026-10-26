<?php
/**
 * Plugin Name:       RGBmind Ephemere
 * Plugin URI:        https://rgbmind.com/ephemere/
 * Description:       Advanced temporary login and timed access manager for WordPress users. Set expiration dates, max login limits, and instant account locking with zero custom database tables and zero external API calls.
 * Version:           1.3.0
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
define( 'RGBMIND_EPH_VERSION', '1.3.0' );
define( 'RGBMIND_EPH_PLUGIN_FILE', __FILE__ );
define( 'RGBMIND_EPH_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RGBMIND_EPH_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'RGBMIND_EPH_PRO_URL', 'https://rgbmind.com/ephemere/' );
define( 'RGBMIND_EPH_BUG_REPORT_URL', 'https://rgbmind.com/bug-report/?product=rgbmind-ephemere' );

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
				'copyText'          => __( 'Copy Link', 'rgbmind-ephemere' ),
				'regenerateText'    => __( 'Regenerate Link', 'rgbmind-ephemere' ),
				'linkExpiresPrefix' => __( 'Active temporary link generated (expires on ', 'rgbmind-ephemere' ),
				'dismissNonce'      => wp_create_nonce( 'rgbmind_eph_dismiss_welcome' ),
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

	// Primary role classification for RBAC section
	$user_roles        = (array) $user->roles;
	$primary_role_slug = ! empty( $user_roles[0] ) ? $user_roles[0] : 'none';
	$wp_roles_obj      = wp_roles();
	$primary_role_name = isset( $wp_roles_obj->roles[ $primary_role_slug ]['name'] ) ? translate_user_role( $wp_roles_obj->roles[ $primary_role_slug ]['name'] ) : ucfirst( $primary_role_slug );

	// Privilege badge calculation
	$role_badge_bg    = '#f0f0f1';
	$role_badge_color = '#50575e';
	$role_badge_text  = __( 'Standard Privilege', 'rgbmind-ephemere' );

	if ( 'administrator' === $primary_role_slug ) {
		$role_badge_bg    = '#fcf0f1';
		$role_badge_color = '#b32d2e';
		$role_badge_text  = __( 'Critical (Full Site Access)', 'rgbmind-ephemere' );
	} elseif ( in_array( $primary_role_slug, array( 'editor', 'shop_manager' ), true ) ) {
		$role_badge_bg    = '#fef7e0';
		$role_badge_color = '#996800';
		$role_badge_text  = __( 'Elevated (Content & Store)', 'rgbmind-ephemere' );
	} elseif ( in_array( $primary_role_slug, array( 'author', 'contributor' ), true ) ) {
		$role_badge_bg    = '#e7f5ea';
		$role_badge_color = '#137333';
		$role_badge_text  = __( 'Standard (Publishing)', 'rgbmind-ephemere' );
	} elseif ( in_array( $primary_role_slug, array( 'subscriber', 'customer' ), true ) ) {
		$role_badge_bg    = '#f0f6fc';
		$role_badge_color = '#135e96';
		$role_badge_text  = __( 'Restricted (Read Only)', 'rgbmind-ephemere' );
	}
	?>
	<div class="rgbmind-ephemere-wrapper" style="margin-top: 25px;">
		<div style="display: flex; align-items: center; justify-content: space-between; max-width: 850px; flex-wrap: wrap; gap: 10px;">
			<h2 style="margin: 0; display: flex; align-items: center; gap: 10px;">
				<img src="<?php echo esc_url( RGBMIND_EPH_PLUGIN_URL . 'assets/images/logo.png' ); ?>" alt="RGBmind" style="width: 24px; height: 24px; object-fit: contain; vertical-align: middle;" />
				<span><?php esc_html_e( 'RGBmind Ephemere – IAM & Access Control', 'rgbmind-ephemere' ); ?></span>
				<?php if ( $is_pro ) : ?>
					<span style="display: inline-flex; align-items: center; gap: 4px; background: #137333; color: #fff; padding: 2px 7px; border-radius: 12px; font-weight: 700; font-size: 10.5px; letter-spacing: 0.5px; box-shadow: 0 1px 2px rgba(0,0,0,0.1); vertical-align: middle;">
						<span class="dashicons dashicons-shield" style="font-size: 12px; width: 12px; height: 12px; line-height: 12px; color: #fff;"></span>
						<span>PRO</span>
					</span>
				<?php endif; ?>
			</h2>
			<?php if ( ! $is_pro ) : ?>
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
			<?php
			$reg_time_prof = ! empty( $user->user_registered ) ? strtotime( $user->user_registered ) : 0;
			if ( $reg_time_prof > 0 ) :
			?>
				<p class="description" style="margin: 6px 0 0 0; color: #50575e; display: flex; align-items: center; gap: 4px;">
					<span class="dashicons dashicons-calendar-alt" style="font-size: 14px; width: 14px; height: 14px; line-height: 14px; vertical-align: -2px; color: #646970;"></span>
					<strong><?php esc_html_e( 'Account Created:', 'rgbmind-ephemere' ); ?></strong>
					<?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $reg_time_prof ) ); ?>
					(<?php echo esc_html( human_time_diff( $reg_time_prof, time() ) ); ?> <?php esc_html_e( 'ago', 'rgbmind-ephemere' ); ?>)
				</p>
			<?php endif; ?>
		</div>

		<!-- ===================================================================
		     SECTION 1: JIT (JUST-IN-TIME ACCESS)
		     =================================================================== -->
		<div class="rgbmind-section-card" style="background: #fff; border: 1px solid #c3c4c7; border-top: 3px solid #2271b1; border-radius: 4px; padding: 16px 20px; max-width: 850px; margin-bottom: 20px;">
			<h3 style="margin-top: 0; margin-bottom: 4px; font-size: 15px; color: #1d2327; display: flex; align-items: center; gap: 8px;">
				<span>⏱️ <?php esc_html_e( 'JIT (Just-In-Time Access)', 'rgbmind-ephemere' ); ?></span>
			</h3>
			<p style="margin: 0 0 15px 0; color: #646970; font-size: 12.5px;">
				<?php esc_html_e( 'Time-based expiration, login count limits, and single-use entry controls.', 'rgbmind-ephemere' ); ?>
			</p>

			<table class="form-table" role="presentation" style="margin-top: 0;">
				<tbody>
					<!-- 1. Enable temporary restrictions (FREE) -->
					<tr>
						<th scope="row" style="width: 220px;">
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
							<span class="description"><?php esc_html_e( 'Maximum allowed logins (0 = unlimited).', 'rgbmind-ephemere' ); ?></span>
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
									/* translators: 1: current login count, 2: maximum allowed logins, 3: remaining logins */
									echo esc_html(
										sprintf(
											/* translators: 1: current login count, 2: maximum allowed logins, 3: remaining logins */
											__( '%1$d of %2$d logins used (%3$d remaining).', 'rgbmind-ephemere' ),
											absint( $login_count ),
											absint( $max_logins ),
											absint( $remaining )
										)
									);
								} else {
									/* translators: %d: current login count */
									echo esc_html(
										sprintf(
											/* translators: %d: current login count */
											__( '%d logins recorded.', 'rgbmind-ephemere' ),
											absint( $login_count )
										)
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

					<!-- 5. PRO JIT Field: Magic Login Link -->
					<?php if ( $is_pro ) : ?>
						<?php do_action( 'rgbmind_eph_render_pro_jit_fields', $user ); ?>
					<?php else : ?>
						<tr class="rgbmind-ephemere-field" style="border-top: 1px solid #f0f0f1;">
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
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<!-- ===================================================================
		     SECTION 2: RBAC (ROLE-BASED ACCESS CONTROL)
		     =================================================================== -->
		<div class="rgbmind-section-card" style="background: #fff; border: 1px solid #c3c4c7; border-top: 3px solid #b06000; border-radius: 4px; padding: 16px 20px; max-width: 850px; margin-bottom: 20px;">
			<h3 style="margin-top: 0; margin-bottom: 4px; font-size: 15px; color: #1d2327; display: flex; align-items: center; gap: 8px;">
				<span>🛡️ <?php esc_html_e( 'RBAC (Role-Based Access Control)', 'rgbmind-ephemere' ); ?></span>
			</h3>
			<p style="margin: 0 0 15px 0; color: #646970; font-size: 12.5px;">
				<?php esc_html_e( 'Privilege levels, authorization bounds, and Least-Privilege enforcement.', 'rgbmind-ephemere' ); ?>
			</p>

			<table class="form-table" role="presentation" style="margin-top: 0;">
				<tbody>
					<tr>
						<th scope="row" style="width: 220px;">
							<label><?php esc_html_e( 'Current WordPress Role', 'rgbmind-ephemere' ); ?></label>
						</th>
						<td>
							<div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
								<strong style="font-size: 14px; color: #1d2327;"><?php echo esc_html( $primary_role_name ); ?></strong>
								<code><?php echo esc_html( $primary_role_slug ); ?></code>
								<span style="display: inline-block; padding: 2px 8px; border-radius: 4px; font-weight: 600; font-size: 11.5px; background: <?php echo esc_attr( $role_badge_bg ); ?>; color: <?php echo esc_attr( $role_badge_color ); ?>;">
									<?php echo esc_html( $role_badge_text ); ?>
								</span>
							</div>

							<?php if ( 'administrator' === $primary_role_slug ) : ?>
								<p class="description" style="color: #b32d2e; font-weight: 500;">
									⚠️ <strong><?php esc_html_e( 'Zero-Trust Notice:', 'rgbmind-ephemere' ); ?></strong>
									<?php esc_html_e( 'This account holds full Administrator capabilities. When creating temporary access for contractors, always set a defined expiration timer to respect the Principle of Least Privilege.', 'rgbmind-ephemere' ); ?>
								</p>
							<?php else : ?>
								<p class="description">
									<?php esc_html_e( 'User capabilities are governed by standard WordPress role permissions. Temporary access rules will terminate access once time or login limits are reached.', 'rgbmind-ephemere' ); ?>
								</p>
							<?php endif; ?>
						</td>
					</tr>

					<?php do_action( 'rgbmind_eph_render_pro_rbac_fields', $user ); ?>
				</tbody>
			</table>
		</div>

		<!-- ===================================================================
		     SECTION 3: MAC (MANDATORY ACCESS CONTROL)
		     =================================================================== -->
		<div class="rgbmind-section-card" style="background: #fff; border: 1px solid #c3c4c7; border-top: 3px solid #d63638; border-radius: 4px; padding: 16px 20px; max-width: 850px; margin-bottom: 20px;">
			<h3 style="margin-top: 0; margin-bottom: 4px; font-size: 15px; color: #1d2327; display: flex; align-items: center; gap: 8px;">
				<span>🔒 <?php esc_html_e( 'MAC & Compliance (Mandatory Access Control)', 'rgbmind-ephemere' ); ?></span>
			</h3>
			<p style="margin: 0 0 15px 0; color: #646970; font-size: 12.5px;">
				<?php esc_html_e( 'System-enforced lockouts, non-bypassable kill-switches, and audit trail records.', 'rgbmind-ephemere' ); ?>
			</p>

			<table class="form-table" role="presentation" style="margin-top: 0;">
				<tbody>
					<!-- 1. Instant lockout checkbox (FREE) -->
					<tr>
						<th scope="row" style="width: 220px;">
							<label for="_rgbmind_is_locked"><?php esc_html_e( 'Instant Account Lockout', 'rgbmind-ephemere' ); ?></label>
						</th>
						<td>
							<fieldset>
								<label for="_rgbmind_is_locked">
									<input type="checkbox" name="_rgbmind_is_locked" id="_rgbmind_is_locked" value="1" <?php checked( 1, $is_locked ); ?> />
									<strong style="color: #b32d2e;"><?php esc_html_e( 'Instantly lock this account and block access', 'rgbmind-ephemere' ); ?></strong>
								</label>
								<p class="description">
									<?php esc_html_e( 'Mandatory system policy: When locked, any login attempt will be immediately rejected regardless of expiration timer or remaining login count.', 'rgbmind-ephemere' ); ?>
								</p>
							</fieldset>
						</td>
					</tr>

					<!-- 2. PRO MAC Field: Access Audit Log -->
					<?php if ( $is_pro ) : ?>
						<?php do_action( 'rgbmind_eph_render_pro_mac_fields', $user ); ?>
					<?php else : ?>
						<tr class="rgbmind-ephemere-field" style="border-top: 1px solid #f0f0f1;">
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

					<?php do_action( 'rgbmind_eph_render_pro_profile_fields', $user ); ?>
				</tbody>
			</table>
		</div>

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
	$old_ephemeral = (int) get_user_meta( $user_id, '_rgbmind_is_ephemeral', true );
	$is_ephemeral  = isset( $_POST['_rgbmind_is_ephemeral'] ) ? 1 : 0;
	if ( 1 === $is_ephemeral && 0 === $old_ephemeral && ! rgbmind_eph_can_create_temporary_account() ) {
		$is_ephemeral = 0;
		set_transient( 'rgbmind_eph_user_edit_notice_' . get_current_user_id(), 'free_limit_reached', 60 );
	}
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
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading public query argument on wp-login.php to display session expiry notice.
	if ( isset( $_GET['rgbmind_session_expired'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['rgbmind_session_expired'] ) ) ) {
		$message .= '<div class="message notice notice-error"><p>' .
			esc_html__( 'Your temporary access session has expired or was revoked by an administrator.', 'rgbmind-ephemere' ) .
			'</p></div>';
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading public query argument on wp-login.php to display error notice.
	if ( isset( $_GET['rgbmind_login_error'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading public query argument on wp-login.php to display error notice.
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
/**
 * Returns categorized list of standard manageable capabilities in WordPress.
 *
 * @return array<string, array<string, string>> Categorized capabilities array.
 */
function rgbmind_eph_get_manageable_capabilities() {
	return array(
		__( 'Posts & Pages (Content)', 'rgbmind-ephemere' ) => array(
			'edit_posts'          => __( 'Edit Posts', 'rgbmind-ephemere' ),
			'publish_posts'       => __( 'Publish Posts', 'rgbmind-ephemere' ),
			'edit_others_posts'   => __( 'Edit Others Posts', 'rgbmind-ephemere' ),
			'delete_posts'        => __( 'Delete Posts', 'rgbmind-ephemere' ),
			'delete_others_posts' => __( 'Delete Others Posts', 'rgbmind-ephemere' ),
			'edit_pages'          => __( 'Edit Pages', 'rgbmind-ephemere' ),
			'publish_pages'       => __( 'Publish Pages', 'rgbmind-ephemere' ),
			'manage_categories'   => __( 'Manage Categories', 'rgbmind-ephemere' ),
		),
		__( 'Media & Uploads', 'rgbmind-ephemere' ) => array(
			'upload_files' => __( 'Upload Media Files', 'rgbmind-ephemere' ),
		),
		__( 'Appearance & Themes', 'rgbmind-ephemere' ) => array(
			'edit_theme_options' => __( 'Edit Theme Options & Menus', 'rgbmind-ephemere' ),
			'switch_themes'      => __( 'Switch Themes', 'rgbmind-ephemere' ),
		),
		__( 'Plugins & Maintenance', 'rgbmind-ephemere' ) => array(
			'activate_plugins' => __( 'Activate Plugins', 'rgbmind-ephemere' ),
			'install_plugins'  => __( 'Install Plugins', 'rgbmind-ephemere' ),
			'update_plugins'   => __( 'Update Plugins', 'rgbmind-ephemere' ),
		),
		__( 'Users & Privileges', 'rgbmind-ephemere' ) => array(
			'list_users'    => __( 'List Users', 'rgbmind-ephemere' ),
			'create_users'  => __( 'Create Users', 'rgbmind-ephemere' ),
			'promote_users' => __( 'Promote & Edit User Roles', 'rgbmind-ephemere' ),
			'delete_users'  => __( 'Delete Users', 'rgbmind-ephemere' ),
		),
		__( 'Administration & System', 'rgbmind-ephemere' ) => array(
			'manage_options' => __( 'Manage Site Settings (Admin)', 'rgbmind-ephemere' ),
			'import'         => __( 'Import Site Content', 'rgbmind-ephemere' ),
			'export'         => __( 'Export Site Content', 'rgbmind-ephemere' ),
		),
	);
}

/**
 * Handles GET and POST actions for RGBmind Ephemere before headers are sent.
 *
 * @return void
 */
function rgbmind_eph_handle_dashboard_actions() {
	if ( ! is_admin() ) {
		return;
	}

	// 1. Handle Quick Lock/Unlock Action via GET
	if ( isset( $_GET['action'] ) && 'toggle_lock' === $_GET['action'] && isset( $_GET['user_id'] ) ) {
		if ( ! current_user_can( 'promote_users' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'rgbmind-ephemere' ) );
		}
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

	// 1b. Handle Quick Magic Link Generation Action via GET
	if ( isset( $_GET['action'] ) && 'generate_magic' === $_GET['action'] && isset( $_GET['user_id'] ) ) {
		if ( ! current_user_can( 'promote_users' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'rgbmind-ephemere' ) );
		}
		$target_uid = absint( $_GET['user_id'] );
		check_admin_referer( 'rgbmind_eph_generate_magic_' . $target_uid );

		if ( rgbmind_eph_is_pro_active() && function_exists( 'rgbmind_eph_pro_generate_magic_token' ) ) {
			rgbmind_eph_pro_generate_magic_token( $target_uid );
		}

		$redirect_url = remove_query_arg( array( 'action', 'user_id', '_wpnonce' ) );
		wp_safe_redirect( add_query_arg( 'rgbmind_notice', 'magic_generated', $redirect_url ) );
		exit;
	}

	// 1c. Handle Role Deletion Action via GET
	if ( isset( $_GET['action'] ) && 'delete_role' === $_GET['action'] && isset( $_GET['role'] ) ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'rgbmind-ephemere' ) );
		}
		$role_to_del = sanitize_key( wp_unslash( $_GET['role'] ) );
		check_admin_referer( 'rgbmind_eph_delete_role_' . $role_to_del );

		$core_roles = array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' );
		if ( in_array( $role_to_del, $core_roles, true ) ) {
			$redirect_url = remove_query_arg( array( 'action', 'role', '_wpnonce' ) );
			wp_safe_redirect( add_query_arg( array( 'tab' => 'roles', 'rgbmind_notice' => 'role_error_core' ), $redirect_url ) );
			exit;
		}

		$user_counts = count_users();
		$in_role     = isset( $user_counts['avail_roles'][ $role_to_del ] ) ? (int) $user_counts['avail_roles'][ $role_to_del ] : 0;
		if ( $in_role > 0 ) {
			$redirect_url = remove_query_arg( array( 'action', 'role', '_wpnonce' ) );
			wp_safe_redirect( add_query_arg( array( 'tab' => 'roles', 'rgbmind_notice' => 'role_error_users' ), $redirect_url ) );
			exit;
		}

		remove_role( $role_to_del );

		$redirect_url = remove_query_arg( array( 'action', 'role', '_wpnonce' ) );
		wp_safe_redirect( add_query_arg( array( 'tab' => 'roles', 'rgbmind_notice' => 'role_deleted' ), $redirect_url ) );
		exit;
	}

	// 1d. Handle Add Role via POST
	if ( isset( $_POST['rgbmind_eph_add_role_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rgbmind_eph_add_role_nonce'] ) ), 'rgbmind_eph_add_role' ) ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'rgbmind-ephemere' ) );
		}
		$role_name  = isset( $_POST['rgbmind_role_name'] ) ? sanitize_text_field( wp_unslash( $_POST['rgbmind_role_name'] ) ) : '';
		$role_slug  = isset( $_POST['rgbmind_role_slug'] ) ? sanitize_key( wp_unslash( $_POST['rgbmind_role_slug'] ) ) : '';
		$clone_from = isset( $_POST['rgbmind_clone_role'] ) ? sanitize_key( wp_unslash( $_POST['rgbmind_clone_role'] ) ) : '';

		if ( empty( $role_slug ) && ! empty( $role_name ) ) {
			$role_slug = sanitize_key( str_replace( ' ', '_', strtolower( $role_name ) ) );
		}

		if ( empty( $role_slug ) || empty( $role_name ) ) {
			wp_safe_redirect( add_query_arg( array( 'tab' => 'roles', 'rgbmind_notice' => 'role_error_invalid' ), admin_url( 'admin.php?page=rgbmind-ephemere' ) ) );
			exit;
		}

		$existing_role = get_role( $role_slug );
		if ( null !== $existing_role ) {
			wp_safe_redirect( add_query_arg( array( 'tab' => 'roles', 'rgbmind_notice' => 'role_error_exists' ), admin_url( 'admin.php?page=rgbmind-ephemere' ) ) );
			exit;
		}

		$caps = array( 'read' => true );
		if ( 'none' === $clone_from ) {
			$caps = array();
		} elseif ( ! empty( $clone_from ) ) {
			$source_role = get_role( $clone_from );
			if ( $source_role && ! empty( $source_role->capabilities ) && is_array( $source_role->capabilities ) ) {
				$caps = $source_role->capabilities;
			}
		}

		add_role( $role_slug, $role_name, $caps );

		wp_safe_redirect( add_query_arg( array( 'tab' => 'roles', 'rgbmind_notice' => 'role_added' ), admin_url( 'admin.php?page=rgbmind-ephemere' ) ) );
		exit;
	}

	// 1e. Handle Save Role Capabilities via POST
	if ( isset( $_POST['rgbmind_eph_role_caps_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rgbmind_eph_role_caps_nonce'] ) ), 'rgbmind_eph_save_role_caps' ) ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'rgbmind-ephemere' ) );
		}
		$target_role_slug = isset( $_POST['rgbmind_target_role'] ) ? sanitize_key( wp_unslash( $_POST['rgbmind_target_role'] ) ) : '';
		$role_obj         = get_role( $target_role_slug );

		if ( $role_obj ) {
			$submitted_caps  = isset( $_POST['rgbmind_caps'] ) && is_array( $_POST['rgbmind_caps'] ) ? array_map( 'sanitize_key', $_POST['rgbmind_caps'] ) : array();
			$manageable_caps = rgbmind_eph_get_manageable_capabilities();

			foreach ( $manageable_caps as $group_name => $caps_list ) {
				foreach ( array_keys( $caps_list ) as $cap ) {
					if ( in_array( $cap, $submitted_caps, true ) ) {
						$role_obj->add_cap( $cap );
					} else {
						// Prevent removing manage_options from administrator
						if ( 'administrator' === $target_role_slug && in_array( $cap, array( 'manage_options', 'read' ), true ) ) {
							$role_obj->add_cap( $cap );
						} else {
							$role_obj->remove_cap( $cap );
						}
					}
				}
			}

			wp_safe_redirect( add_query_arg( array( 'tab' => 'roles', 'edit_role' => $target_role_slug, 'rgbmind_notice' => 'role_caps_saved' ), admin_url( 'admin.php?page=rgbmind-ephemere#role-permissions-editor' ) ) );
			exit;
		}
	}

	// 2. Handle Settings Form Submission via POST
	if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['rgbmind_eph_settings_nonce'] ) ) {
		if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rgbmind_eph_settings_nonce'] ) ), 'rgbmind_eph_save_settings' ) ) {
			do_action( 'rgbmind_eph_save_dashboard_settings' );
		}
	}
}
add_action( 'admin_init', 'rgbmind_eph_handle_dashboard_actions' );

function rgbmind_eph_render_dashboard() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'rgbmind-ephemere' ) );
	}

	$notice      = '';
	$notice_type = '';

	// Query notices
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading public admin query notice parameter.
	if ( isset( $_GET['rgbmind_notice'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading public admin query notice parameter.
		$code = sanitize_text_field( wp_unslash( $_GET['rgbmind_notice'] ) );
		if ( 'user_locked' === $code ) {
			$notice      = __( 'Account locked successfully.', 'rgbmind-ephemere' );
			$notice_type = 'warning';
		} elseif ( 'user_unlocked' === $code ) {
			$notice      = __( 'Account unlocked successfully.', 'rgbmind-ephemere' );
			$notice_type = 'success';
		} elseif ( 'quick_settings_saved' === $code ) {
			$notice      = __( 'Quick Account Creation settings saved successfully.', 'rgbmind-ephemere' );
			$notice_type = 'success';
		} elseif ( 'magic_generated' === $code ) {
			$notice      = __( 'Magic Link generated successfully.', 'rgbmind-ephemere' );
			$notice_type = 'success';
		} elseif ( 'role_added' === $code ) {
			$notice      = __( 'New role created successfully.', 'rgbmind-ephemere' );
			$notice_type = 'success';
		} elseif ( 'role_deleted' === $code ) {
			$notice      = __( 'Role removed successfully.', 'rgbmind-ephemere' );
			$notice_type = 'success';
		} elseif ( 'role_caps_saved' === $code ) {
			$notice      = __( 'Role permissions updated successfully.', 'rgbmind-ephemere' );
			$notice_type = 'success';
		} elseif ( 'role_error_exists' === $code ) {
			$notice      = __( 'A role with this identifier already exists.', 'rgbmind-ephemere' );
			$notice_type = 'error';
		} elseif ( 'role_error_core' === $code ) {
			$notice      = __( 'WordPress core system roles cannot be deleted.', 'rgbmind-ephemere' );
			$notice_type = 'error';
		} elseif ( 'role_error_users' === $code ) {
			$notice      = __( 'Cannot delete this role because users are currently assigned to it. Reassign users first.', 'rgbmind-ephemere' );
			$notice_type = 'error';
		} elseif ( 'role_error_invalid' === $code ) {
			$notice      = __( 'Please provide a valid role name and identifier.', 'rgbmind-ephemere' );
			$notice_type = 'error';
		} elseif ( 'bulk_done' === $code ) {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only notice parameters.
			$bulk_op = isset( $_GET['rgbmind_bulk'] ) ? sanitize_key( wp_unslash( $_GET['rgbmind_bulk'] ) ) : '';
			$bulk_n  = isset( $_GET['rgbmind_count'] ) ? absint( $_GET['rgbmind_count'] ) : 0;
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			$bulk_messages = array(
				/* translators: %d: number of accounts */
				'delete'      => __( '%d account(s) deleted.', 'rgbmind-ephemere' ),
				/* translators: %d: number of accounts */
				'change_role' => __( 'Role changed for %d account(s).', 'rgbmind-ephemere' ),
				/* translators: %d: number of accounts */
				'lock'        => __( '%d account(s) locked.', 'rgbmind-ephemere' ),
				/* translators: %d: number of accounts */
				'unlock'      => __( '%d account(s) unlocked.', 'rgbmind-ephemere' ),
				/* translators: %d: number of accounts */
				'magic'       => __( 'New Magic Link generated for %d account(s).', 'rgbmind-ephemere' ),
			);
			/* translators: %d: number of accounts */
			$bulk_tpl    = isset( $bulk_messages[ $bulk_op ] ) ? $bulk_messages[ $bulk_op ] : __( 'Bulk action applied to %d account(s).', 'rgbmind-ephemere' );
			$notice      = sprintf( $bulk_tpl, $bulk_n );
			$notice_type = $bulk_n > 0 ? 'success' : 'warning';
		} elseif ( 'free_limit_reached' === $code ) {
			$notice      = __( '⚠️ Free Version Limit: You have reached the maximum of 3 concurrent active temporary accounts. Please upgrade to RGBmind Ephemere PRO to unlock unlimited temporary accounts, or expire/delete an existing account.', 'rgbmind-ephemere' );
			$notice_type = 'warning';
		} elseif ( 'bulk_none' === $code ) {
			$notice      = __( 'Select at least one account and a valid bulk action.', 'rgbmind-ephemere' );
			$notice_type = 'warning';
		}
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading public admin query notice parameter.
	if ( isset( $_GET['rgbmind_pro_notice'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading public admin query notice parameter.
		$pro_code = sanitize_text_field( wp_unslash( $_GET['rgbmind_pro_notice'] ) );
		switch ( $pro_code ) {
			case 'activated':
				$notice      = __( '🎉 Congratulations! PRO license activated successfully. All premium features are now unlocked.', 'rgbmind-ephemere' );
				$notice_type = 'success';
				break;
			case 'invalid_key':
				$notice      = __( '❌ Invalid license key. Please check your key and try again.', 'rgbmind-ephemere' );
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

	// Active tab determination (with backward compatibility)
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading public admin tab navigation query parameter.
	$raw_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'jit';
	if ( 'accounts' === $raw_tab ) {
		$raw_tab = 'jit';
	} elseif ( 'license' === $raw_tab ) {
		$raw_tab = 'settings';
	} elseif ( 'about' === $raw_tab ) {
		$raw_tab = 'version';
	} elseif ( 'mac' === $raw_tab ) {
		$raw_tab = 'audit';
	} elseif ( 'rbac' === $raw_tab ) {
		$raw_tab = 'roles';
	}
	$active_tab = in_array( $raw_tab, array( 'jit', 'roles', 'rbac', 'audit', 'settings', 'version' ), true ) ? $raw_tab : 'jit';
	$is_pro     = rgbmind_eph_is_pro_active();
	?>
	<div class="wrap">
		<div class="rgbmind-eph-dashboard-wrap" style="max-width: 1080px; margin: 20px auto 40px auto;">
		<h1 style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
			<span style="display: flex; align-items: center; gap: 10px;">
				<img src="<?php echo esc_url( RGBMIND_EPH_PLUGIN_URL . 'assets/images/logo.png' ); ?>" alt="RGBmind" style="width: 32px; height: 32px; object-fit: contain; vertical-align: middle; border-radius: 4px;" />
				<span><?php esc_html_e( 'RGBmind Ephemere – IAM & Access Dashboard', 'rgbmind-ephemere' ); ?></span>
				<?php if ( $is_pro ) : ?>
					<span style="display: inline-flex; align-items: center; gap: 4px; background: #137333; color: #fff; padding: 2px 8px; border-radius: 12px; font-weight: 700; font-size: 11px; letter-spacing: 0.5px; box-shadow: 0 1px 2px rgba(0,0,0,0.1); vertical-align: middle;">
						<span class="dashicons dashicons-shield" style="font-size: 13px; width: 13px; height: 13px; line-height: 13px; color: #fff;"></span>
						<span>PRO</span>
					</span>
				<?php endif; ?>
			</span>
			<div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
				<?php if ( ! $is_pro ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=rgbmind-ephemere&tab=settings' ) ); ?>" style="text-decoration: none;">
						<span style="background: #fef7e0; color: #b06000; font-weight: 700; font-size: 12px; padding: 5px 12px; border-radius: 12px; border: 1px solid #fce8b2; letter-spacing: 0.5px;">
							FREE VERSION (UPGRADE PRO)
						</span>
					</a>
								<?php endif; ?>
			</div>
		</h1>

		<?php if ( ! empty( $notice ) ) : ?>
			<div class="notice notice-<?php echo esc_attr( $notice_type ); ?> is-dismissible" style="margin-top: 15px;">
				<p><?php echo esc_html( $notice ); ?></p>
			</div>
		<?php endif; ?>

		<?php
		$quick_created_data = get_transient( 'rgbmind_eph_quick_created_' . get_current_user_id() );
		if ( $quick_created_data ) :
			delete_transient( 'rgbmind_eph_quick_created_' . get_current_user_id() );
		?>
			<div class="notice notice-success is-dismissible" style="padding: 16px 20px; border-left: 4px solid #137333; margin: 15px 0; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
				<h3 style="margin: 0 0 10px 0; color: #137333; font-size: 15px; display: flex; align-items: center; gap: 8px;">
					<span>✅ <?php esc_html_e( 'Temporary Account Created Successfully!', 'rgbmind-ephemere' ); ?></span>
				</h3>
				<div style="font-size: 13.5px; line-height: 1.6; color: #1d2327;">
					<p style="margin: 0 0 10px 0;">
						<strong><?php esc_html_e( 'Username:', 'rgbmind-ephemere' ); ?></strong> <code><?php echo esc_html( $quick_created_data['username'] ); ?></code> &nbsp;|&nbsp;
						<strong><?php esc_html_e( 'Email:', 'rgbmind-ephemere' ); ?></strong> <code><?php echo esc_html( $quick_created_data['email'] ); ?></code> &nbsp;|&nbsp;
						<strong><?php esc_html_e( 'Role:', 'rgbmind-ephemere' ); ?></strong> <?php echo esc_html( $quick_created_data['role'] ); ?> &nbsp;|&nbsp;
						<strong><?php esc_html_e( 'Expires:', 'rgbmind-ephemere' ); ?></strong> <?php echo esc_html( $quick_created_data['expires'] ); ?>
					</p>

					<?php if ( ! empty( $quick_created_data['magic_link'] ) ) : ?>
						<div style="margin-top: 10px; background: #f6f7f7; padding: 12px 14px; border-radius: 4px; border: 1px solid #c3c4c7; max-width: 800px;">
							<label style="display: block; font-weight: 600; margin-bottom: 6px; color: #135e96;">
								⚡ <?php esc_html_e( 'One-Click Magic Login Link (Single-use, ready to share with contractor):', 'rgbmind-ephemere' ); ?>
							</label>
							<div style="display: flex; gap: 8px; align-items: center;">
								<input type="text" readonly="readonly" class="regular-text code" style="width: 100%; background: #fff;" value="<?php echo esc_url( $quick_created_data['magic_link'] ); ?>" id="rgbmind_quick_magic_link_input" onclick="this.select();" />
								<button type="button" class="button button-secondary" onclick="navigator.clipboard.writeText(document.getElementById('rgbmind_quick_magic_link_input').value); this.textContent='<?php echo esc_js( __( 'Copied!', 'rgbmind-ephemere' ) ); ?>'; setTimeout(()=>{this.textContent='<?php echo esc_js( __( 'Copy Link', 'rgbmind-ephemere' ) ); ?>';}, 2000);">
									<?php esc_html_e( 'Copy Link', 'rgbmind-ephemere' ); ?>
								</button>
								<a href="<?php echo esc_url( admin_url( 'user-edit.php?user_id=' . (int) $quick_created_data['user_id'] ) ); ?>" class="button button-link" style="margin-left: 5px;">
									<?php esc_html_e( 'Edit User', 'rgbmind-ephemere' ); ?> &rarr;
								</a>
							</div>
						</div>
					<?php elseif ( ! empty( $quick_created_data['password'] ) ) : ?>
						<div style="margin-top: 10px; background: #f6f7f7; padding: 12px 14px; border-radius: 4px; border: 1px solid #c3c4c7; max-width: 800px;">
							<label style="display: block; font-weight: 600; margin-bottom: 6px;">
								🔑 <?php esc_html_e( 'Generated Password:', 'rgbmind-ephemere' ); ?>
							</label>
							<div style="display: flex; gap: 8px; align-items: center;">
								<input type="text" readonly="readonly" class="regular-text code" style="width: 100%; background: #fff;" value="<?php echo esc_attr( $quick_created_data['password'] ); ?>" id="rgbmind_quick_password_input" onclick="this.select();" />
								<button type="button" class="button button-secondary" onclick="navigator.clipboard.writeText(document.getElementById('rgbmind_quick_password_input').value); this.textContent='<?php echo esc_js( __( 'Copied!', 'rgbmind-ephemere' ) ); ?>'; setTimeout(()=>{this.textContent='<?php echo esc_js( __( 'Copy Password', 'rgbmind-ephemere' ) ); ?>';}, 2000);">
									<?php esc_html_e( 'Copy Password', 'rgbmind-ephemere' ); ?>
								</button>
								<a href="<?php echo esc_url( admin_url( 'user-edit.php?user_id=' . (int) $quick_created_data['user_id'] ) ); ?>" class="button button-link" style="margin-left: 5px;">
									<?php esc_html_e( 'Edit User', 'rgbmind-ephemere' ); ?> &rarr;
								</a>
							</div>
						</div>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>

		<!-- Tab Navigation: JIT, RBAC, MAC, SETTINGS -->
		<nav class="nav-tab-wrapper" style="margin-top: 15px; margin-bottom: 20px;">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=rgbmind-ephemere&tab=jit' ) ); ?>" class="nav-tab <?php echo 'jit' === $active_tab ? 'nav-tab-active' : ''; ?>">
				⏱️ <?php esc_html_e( 'JIT Access', 'rgbmind-ephemere' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=rgbmind-ephemere&tab=roles' ) ); ?>" class="nav-tab <?php echo in_array( $active_tab, array( 'roles', 'rbac' ), true ) ? 'nav-tab-active' : ''; ?>">
				🛡️ <?php esc_html_e( 'Roles & Permissions', 'rgbmind-ephemere' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=rgbmind-ephemere&tab=audit' ) ); ?>" class="nav-tab <?php echo in_array( $active_tab, array( 'audit', 'mac' ), true ) ? 'nav-tab-active' : ''; ?>">
				📋 <?php esc_html_e( 'Access Audit Log', 'rgbmind-ephemere' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=rgbmind-ephemere&tab=settings' ) ); ?>" class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>">
				⚙️ <?php esc_html_e( 'Settings', 'rgbmind-ephemere' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=rgbmind-ephemere&tab=version' ) ); ?>" class="nav-tab <?php echo 'version' === $active_tab ? 'nav-tab-active' : ''; ?>">
				ℹ️ <?php esc_html_e( 'Version & About', 'rgbmind-ephemere' ); ?>
			</a>
		</nav>

		<!-- TAB 1: JIT ACCESS (TEMPORARY ACCOUNTS) -->
		<?php if ( 'jit' === $active_tab ) : ?>
			<?php
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Filter is required to list configured temporary accounts.
			$ephemeral_users = get_users(
				array(
					'meta_key'   => '_rgbmind_is_ephemeral', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value' => 1,                       // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
					'number'     => 500,
				)
			);
			?>

			<?php
			$jit_filters    = rgbmind_eph_get_account_filters_from_request();
			$filtered_users = rgbmind_eph_filter_accounts( $ephemeral_users, $jit_filters );
			$has_filters    = ( '' !== $jit_filters['s'] || '' !== $jit_filters['status'] || '' !== $jit_filters['role'] || '' !== $jit_filters['ml'] );
			$jit_roles      = function_exists( 'get_editable_roles' ) ? get_editable_roles() : wp_roles()->roles;
			$export_args    = array_filter( array_merge( array( 'tab' => 'jit' ), $jit_filters ) );
			$status_counts  = array(
				'active'  => 0,
				'locked'  => 0,
				'expired' => 0,
				'limit'   => 0,
			);
			foreach ( $ephemeral_users as $eu ) {
				$status_counts[ rgbmind_eph_get_account_status_key( $eu->ID ) ]++;
			}
			$status_labels = array(
				'active'  => __( 'Active', 'rgbmind-ephemere' ),
				'locked'  => __( 'Locked', 'rgbmind-ephemere' ),
				'expired' => __( 'Expired', 'rgbmind-ephemere' ),
				'limit'   => __( 'Limit reached', 'rgbmind-ephemere' ),
			);
			?>

			<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; flex-wrap: wrap; gap: 10px;">
				<div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
					<?php rgbmind_eph_render_quick_create_form(); ?>
					<?php
					$active_count_tab = isset( $status_counts['active'] ) ? (int) $status_counts['active'] : 0;
					$free_limit_tab   = rgbmind_eph_get_free_active_accounts_limit();
					if ( ! $is_pro ) :
					?>
						<span style="display: inline-flex; align-items: center; gap: 5px; background: <?php echo ( $active_count_tab >= $free_limit_tab ) ? '#fef7e0' : '#f0f6fc'; ?>; color: <?php echo ( $active_count_tab >= $free_limit_tab ) ? '#b06000' : '#0969da'; ?>; border: 1px solid <?php echo ( $active_count_tab >= $free_limit_tab ) ? '#fce8b2' : '#c8e1ff'; ?>; padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: 600;">
							<span class="dashicons dashicons-admin-users" style="font-size: 14px; width: 14px; height: 14px; line-height: 14px;"></span>
							<?php printf( esc_html__( 'Active: %1$d / %2$d (Free Plan)', 'rgbmind-ephemere' ), $active_count_tab, $free_limit_tab ); ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=rgbmind-ephemere&tab=settings' ) ); ?>" style="color: <?php echo ( $active_count_tab >= $free_limit_tab ) ? '#8a4b00' : '#0550ae'; ?>; text-decoration: underline; margin-left: 4px;">
								<?php esc_html_e( 'Upgrade to PRO &rarr;', 'rgbmind-ephemere' ); ?>
							</a>
						</span>
					<?php endif; ?>
				</div>
				<div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
					<?php
					if ( ! empty( $ephemeral_users ) ) {
						rgbmind_eph_render_export_menu(
							'export_accounts',
							'rgbmind_eph_export_accounts',
							$has_filters ? __( 'Export Filtered', 'rgbmind-ephemere' ) : __( 'Export Accounts', 'rgbmind-ephemere' ),
							$export_args
						);
					}
					?>
					<a href="<?php echo esc_url( admin_url( 'users.php' ) ); ?>" class="button button-secondary">
						<?php esc_html_e( 'Manage All WordPress Users', 'rgbmind-ephemere' ); ?> &rarr;
					</a>
				</div>
			</div>

			<?php if ( ! empty( $ephemeral_users ) ) : ?>
				<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="rgbmind-filter-bar" style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; background: #fff; border: 1px solid #dcdcde; border-radius: 4px; padding: 10px 12px; margin-bottom: 12px;">
					<input type="hidden" name="page" value="rgbmind-ephemere" />
					<input type="hidden" name="tab" value="jit" />
					<div style="position: relative; flex: 1 1 240px; max-width: 340px;">
						<span class="dashicons dashicons-search" style="position: absolute; left: 8px; top: 50%; transform: translateY(-50%); color: #8c8f94; font-size: 16px; width: 16px; height: 16px; line-height: 16px; pointer-events: none;"></span>
						<input type="search" name="s" value="<?php echo esc_attr( $jit_filters['s'] ); ?>" placeholder="<?php esc_attr_e( 'Search username, email or name…', 'rgbmind-ephemere' ); ?>" aria-label="<?php esc_attr_e( 'Search accounts', 'rgbmind-ephemere' ); ?>" style="width: 100%; padding-left: 30px; min-height: 32px; margin: 0;" />
					</div>
					<select name="status" onchange="this.form.submit()" aria-label="<?php esc_attr_e( 'Filter by status', 'rgbmind-ephemere' ); ?>" style="min-height: 32px; margin: 0;">
						<option value=""><?php esc_html_e( 'All statuses', 'rgbmind-ephemere' ); ?></option>
						<?php foreach ( $status_labels as $st_key => $st_label ) : ?>
							<option value="<?php echo esc_attr( $st_key ); ?>" <?php selected( $jit_filters['status'], $st_key ); ?>><?php echo esc_html( $st_label . ' (' . $status_counts[ $st_key ] . ')' ); ?></option>
						<?php endforeach; ?>
					</select>
					<select name="role" onchange="this.form.submit()" aria-label="<?php esc_attr_e( 'Filter by role', 'rgbmind-ephemere' ); ?>" style="min-height: 32px; margin: 0;">
						<option value=""><?php esc_html_e( 'All roles', 'rgbmind-ephemere' ); ?></option>
						<?php foreach ( $jit_roles as $r_slug => $r_info ) : ?>
							<option value="<?php echo esc_attr( $r_slug ); ?>" <?php selected( $jit_filters['role'], $r_slug ); ?>><?php echo esc_html( translate_user_role( $r_info['name'] ) ); ?></option>
						<?php endforeach; ?>
					</select>
					<?php if ( $is_pro ) : ?>
						<select name="ml" onchange="this.form.submit()" aria-label="<?php esc_attr_e( 'Filter by Magic Link', 'rgbmind-ephemere' ); ?>" style="min-height: 32px; margin: 0;">
							<option value=""><?php esc_html_e( 'All Magic Links', 'rgbmind-ephemere' ); ?></option>
							<option value="active" <?php selected( $jit_filters['ml'], 'active' ); ?>><?php esc_html_e( 'Magic Link active', 'rgbmind-ephemere' ); ?></option>
							<option value="expired" <?php selected( $jit_filters['ml'], 'expired' ); ?>><?php esc_html_e( 'Magic Link expired', 'rgbmind-ephemere' ); ?></option>
							<option value="none" <?php selected( $jit_filters['ml'], 'none' ); ?>><?php esc_html_e( 'Magic Link not generated', 'rgbmind-ephemere' ); ?></option>
						</select>
					<?php endif; ?>
					<button type="submit" class="button" style="min-height: 32px;"><?php esc_html_e( 'Filter', 'rgbmind-ephemere' ); ?></button>
					<?php if ( $has_filters ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=rgbmind-ephemere&tab=jit' ) ); ?>" style="color: #b32d2e; text-decoration: none; font-size: 13px;">&times; <?php esc_html_e( 'Reset filters', 'rgbmind-ephemere' ); ?></a>
					<?php endif; ?>
					<span style="margin-left: auto; color: #646970; font-size: 13px;">
						<?php
						printf(
							/* translators: 1: visible accounts, 2: total accounts */
							esc_html__( 'Showing %1$d of %2$d temporary accounts', 'rgbmind-ephemere' ),
							count( $filtered_users ),
							count( $ephemeral_users )
						);
						?>
					</span>
				</form>
			<?php endif; ?>

			<?php if ( empty( $ephemeral_users ) ) : ?>
				<div class="card" style="padding: 30px; text-align: center; max-width: none;">
					<h3 style="margin-top: 0; color: #1d2327;">
						<?php esc_html_e( 'No Temporary Accounts Found', 'rgbmind-ephemere' ); ?>
					</h3>
					<p style="color: #646970; max-width: 500px; margin: 10px auto;">
						<?php esc_html_e( 'None of your users are currently configured as temporary accounts. Create a new temporary account with one click or edit any existing user profile.', 'rgbmind-ephemere' ); ?>
					</p>
					<div style="display: flex; gap: 10px; justify-content: center; align-items: center; margin-top: 15px; flex-wrap: wrap;">
						<?php rgbmind_eph_render_quick_create_form(); ?>
						<a href="<?php echo esc_url( admin_url( 'users.php' ) ); ?>" class="button button-secondary">
							<?php esc_html_e( 'Go to Users Screen', 'rgbmind-ephemere' ); ?>
						</a>
					</div>
				</div>
			<?php else : ?>
				<form method="post" action="" id="rgbmind-bulk-form" style="margin: 0;">
					<?php wp_nonce_field( 'rgbmind_eph_bulk_action', 'rgbmind_eph_bulk_nonce' ); ?>
					<div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap; margin-bottom: 8px;">
						<select name="rgbmind_bulk_action" id="rgbmind-bulk-action" aria-label="<?php esc_attr_e( 'Bulk actions', 'rgbmind-ephemere' ); ?>" style="margin: 0;">
							<option value=""><?php esc_html_e( 'Bulk actions', 'rgbmind-ephemere' ); ?></option>
							<option value="change_role"><?php esc_html_e( 'Change role to…', 'rgbmind-ephemere' ); ?></option>
							<option value="lock"><?php esc_html_e( 'Lock', 'rgbmind-ephemere' ); ?></option>
							<option value="unlock"><?php esc_html_e( 'Unlock', 'rgbmind-ephemere' ); ?></option>
							<?php if ( $is_pro ) : ?>
								<option value="magic"><?php esc_html_e( 'Generate new Magic Link', 'rgbmind-ephemere' ); ?></option>
							<?php endif; ?>
							<optgroup label="<?php esc_attr_e( 'Export selected', 'rgbmind-ephemere' ); ?>">
								<?php foreach ( rgbmind_eph_get_export_formats() as $fmt_key => $fmt ) : ?>
									<option value="export_<?php echo esc_attr( $fmt_key ); ?>"><?php echo esc_html( sprintf( /* translators: %s: file format */ __( 'Export as %s', 'rgbmind-ephemere' ), $fmt['label'] ) ); ?></option>
								<?php endforeach; ?>
							</optgroup>
							<?php if ( current_user_can( 'delete_users' ) ) : ?>
								<option value="delete"><?php esc_html_e( 'Delete permanently', 'rgbmind-ephemere' ); ?></option>
							<?php endif; ?>
						</select>
						<select name="rgbmind_bulk_role" id="rgbmind-bulk-role" aria-label="<?php esc_attr_e( 'New role', 'rgbmind-ephemere' ); ?>" style="display: none; margin: 0;">
							<?php foreach ( $jit_roles as $r_slug => $r_info ) : ?>
								<option value="<?php echo esc_attr( $r_slug ); ?>"><?php echo esc_html( translate_user_role( $r_info['name'] ) ); ?></option>
							<?php endforeach; ?>
						</select>
						<button type="submit" class="button" id="rgbmind-bulk-apply"><?php esc_html_e( 'Apply', 'rgbmind-ephemere' ); ?></button>
						<span id="rgbmind-bulk-count" style="color: #2271b1; font-size: 12.5px; font-weight: 600; margin-left: 4px;"></span>
					</div>
				<table class="wp-list-table widefat fixed striped table-view-list" style="box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
					<thead>
						<tr>
							<td class="manage-column column-cb check-column" style="width: 2.2em;">
								<input type="checkbox" class="rgbmind-cb-all" aria-label="<?php esc_attr_e( 'Select all', 'rgbmind-ephemere' ); ?>" />
							</td>
							<th style="width: 17%;"><?php esc_html_e( 'Username / User', 'rgbmind-ephemere' ); ?></th>
							<th style="width: 18%;"><?php esc_html_e( 'Email', 'rgbmind-ephemere' ); ?></th>
							<th style="width: 16%;"><?php esc_html_e( 'Expiration Date/Time', 'rgbmind-ephemere' ); ?></th>
							<th style="width: 19%;"><?php esc_html_e( 'Magic Link', 'rgbmind-ephemere' ); ?></th>
							<th style="width: 10%;"><?php esc_html_e( 'Login Count', 'rgbmind-ephemere' ); ?></th>
							<th style="width: 9%;"><?php esc_html_e( 'Status', 'rgbmind-ephemere' ); ?></th>
							<th style="width: 11%; text-align: right;"><?php esc_html_e( 'Quick Actions', 'rgbmind-ephemere' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						$now = time();
						foreach ( $filtered_users as $u ) :
							$u_id        = $u->ID;
							$u_locked    = (int) get_user_meta( $u_id, '_rgbmind_is_locked', true );
							$u_expire    = (int) get_user_meta( $u_id, '_rgbmind_expire_at', true );
							$u_max       = (int) get_user_meta( $u_id, '_rgbmind_max_logins', true );
							$u_count     = (int) get_user_meta( $u_id, '_rgbmind_login_count', true );
							$u_last      = (int) get_user_meta( $u_id, '_rgbmind_last_login_time', true );

							// Magic Link state
							$u_token_exp = (int) get_user_meta( $u_id, '_rgbmind_magic_token_exp', true );
							if ( 0 === $u_token_exp ) {
								$u_token_exp = (int) get_user_meta( $u_id, '_rgbmind_magic_token_expires', true );
							}
							$has_active_ml = ( $u_token_exp > $now );
							$magic_url     = '';

							if ( $has_active_ml ) {
								$plain_token = (string) get_user_meta( $u_id, '_rgbmind_magic_plain_token', true );
								if ( empty( $plain_token ) && function_exists( 'rgbmind_eph_pro_generate_magic_token' ) ) {
									$plain_token = rgbmind_eph_pro_generate_magic_token( $u_id, max( 60, $u_token_exp - $now ) );
								}
								if ( ! empty( $plain_token ) ) {
									$magic_url = add_query_arg(
										array(
											'rgbmind_magic_auth' => $plain_token,
											'rgbmind_uid'        => $u_id,
										),
										wp_login_url()
									);
								}
							}

							$gen_magic_nonce = wp_create_nonce( 'rgbmind_eph_generate_magic_' . $u_id );
							$gen_magic_url   = add_query_arg(
								array(
									'action'   => 'generate_magic',
									'user_id'  => $u_id,
									'_wpnonce' => $gen_magic_nonce,
								),
								admin_url( 'admin.php?page=rgbmind-ephemere&tab=jit' )
							);

							// Status resolution
							$status_label = __( 'Active', 'rgbmind-ephemere' );
							$status_style = 'color: #007017; font-weight: 600;';
							$status_icon  = '✅';

							if ( 1 === $u_locked ) {
								$status_label = __( 'Locked', 'rgbmind-ephemere' );
								$status_style = 'color: #b32d2e; font-weight: 600;';
								$status_icon  = '🔒';
							} elseif ( $u_expire > 0 && $now >= $u_expire ) {
								$status_label = __( 'Expired', 'rgbmind-ephemere' );
								$status_style = 'color: #996800; font-weight: 600;';
								$status_icon  = '⏰';
							} elseif ( $u_max > 0 && $u_count >= $u_max ) {
								$status_label = __( 'Limit reached', 'rgbmind-ephemere' );
								$status_style = 'color: #996800; font-weight: 600;';
								$status_icon  = '⚠️';
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
								<th scope="row" class="check-column">
									<input type="checkbox" name="rgbmind_ids[]" value="<?php echo esc_attr( $u_id ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: username */ __( 'Select %s', 'rgbmind-ephemere' ), $u->user_login ) ); ?>" />
								</th>
								<td>
									<strong>
										<a href="<?php echo esc_url( admin_url( 'user-edit.php?user_id=' . $u_id ) ); ?>">
											<?php echo esc_html( $u->user_login ); ?>
										</a>
									</strong>
									<br />
									<small style="color: #646970;"><?php echo esc_html( $u->display_name ); ?></small>
									<?php
									$u_role_slug = ! empty( $u->roles ) ? reset( $u->roles ) : '';
									if ( $u_role_slug ) :
										$u_role_label = isset( $jit_roles[ $u_role_slug ]['name'] ) ? translate_user_role( $jit_roles[ $u_role_slug ]['name'] ) : ucfirst( $u_role_slug );
										?>
										<br /><span style="display: inline-block; margin-top: 3px; background: #f0f0f1; color: #50575e; padding: 1px 6px; border-radius: 3px; font-size: 11px; font-weight: 600;"><?php echo esc_html( $u_role_label ); ?></span>
									<?php endif; ?>
									<?php
									$u_reg_time = ! empty( $u->user_registered ) ? strtotime( $u->user_registered ) : 0;
									if ( $u_reg_time > 0 ) :
										$u_reg_title = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $u_reg_time );
									?>
										<br /><span style="display: inline-flex; align-items: center; gap: 3px; color: #646970; font-size: 11px; margin-top: 4px;" title="<?php printf( esc_attr__( 'Created on: %s', 'rgbmind-ephemere' ), esc_attr( $u_reg_title ) ); ?>">
											<span class="dashicons dashicons-calendar-alt" style="font-size: 13px; width: 13px; height: 13px; line-height: 13px; color: #8c8f94;"></span>
											<span><?php printf( esc_html__( 'Created: %s', 'rgbmind-ephemere' ), esc_html( wp_date( 'd/m/Y H:i', $u_reg_time ) ) ); ?></span>
										</span>
									<?php endif; ?>
								</td>
								<td>
									<a href="mailto:<?php echo esc_attr( $u->user_email ); ?>" style="word-break: break-all;">
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
												/* translators: %s: relative time elapsed since expiration (e.g. 2 hours) */
												echo esc_html( sprintf( __( 'Expired %s ago', 'rgbmind-ephemere' ), human_time_diff( $u_expire, $now ) ) );
											} else {
												/* translators: %s: relative time until expiration (e.g. 3 days) */
												echo esc_html( sprintf( __( 'in %s', 'rgbmind-ephemere' ), human_time_diff( $now, $u_expire ) ) );
											}
											?>
										</small>
									<?php else : ?>
										<span style="color: #646970;"><?php esc_html_e( 'No time limit', 'rgbmind-ephemere' ); ?></span>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( ! $is_pro ) : ?>
										<span style="color: #a7aaad; font-size: 12px;" title="<?php esc_attr_e( 'Available in PRO version', 'rgbmind-ephemere' ); ?>">
											<?php esc_html_e( 'PRO feature', 'rgbmind-ephemere' ); ?>
										</span>
									<?php elseif ( $has_active_ml && ! empty( $magic_url ) ) : ?>
										<span style="color: #007017; font-weight: 600; font-size: 12px; display: inline-flex; align-items: center; gap: 4px;">
											<span class="dashicons dashicons-yes-alt" style="color: #007017; font-size: 15px; width: 15px; height: 15px; line-height: 15px;"></span>
											<?php esc_html_e( 'Active', 'rgbmind-ephemere' ); ?>
										</span>
										<br />
										<small style="color: #646970;">
											<?php
											printf(
												/* translators: 1: formatted date/time, 2: relative time */
												esc_html__( '%1$s (in %2$s)', 'rgbmind-ephemere' ),
												esc_html( wp_date( 'd/m H:i', $u_token_exp ) ),
												esc_html( human_time_diff( $now, $u_token_exp ) )
											);
											?>
										</small>
										<div style="margin-top: 5px; display: flex; align-items: center; gap: 4px;">
											<button type="button" class="button button-small rgbmind-copy-ml-btn" data-url="<?php echo esc_attr( $magic_url ); ?>" style="display: inline-flex; align-items: center; gap: 3px;" title="<?php esc_attr_e( 'Copy Magic Link to clipboard', 'rgbmind-ephemere' ); ?>">
												<span class="dashicons dashicons-admin-links" style="font-size: 14px; width: 14px; height: 14px; line-height: 14px;"></span>
												<span><?php esc_html_e( 'Copy Link', 'rgbmind-ephemere' ); ?></span>
											</button>
											<a href="<?php echo esc_url( $gen_magic_url ); ?>" class="button button-small" style="display: inline-flex; align-items: center; justify-content: center; padding: 0 6px; min-width: 28px;" title="<?php esc_attr_e( 'Regenerate Magic Link', 'rgbmind-ephemere' ); ?>">
												<span class="dashicons dashicons-update" style="font-size: 15px; width: 15px; height: 15px; line-height: 15px;"></span>
											</a>
										</div>
									<?php elseif ( $u_token_exp > 0 && $u_token_exp <= $now ) : ?>
										<span style="color: #b32d2e; font-weight: 500; font-size: 12px; display: inline-flex; align-items: center; gap: 4px;">
											<span class="dashicons dashicons-clock" style="color: #b32d2e; font-size: 15px; width: 15px; height: 15px; line-height: 15px;"></span>
											<?php esc_html_e( 'Expired', 'rgbmind-ephemere' ); ?>
										</span>
										<br />
										<small style="color: #8c8f94;">
											<?php
											printf(
												/* translators: %s: relative time elapsed since expiration */
												esc_html__( '%s ago', 'rgbmind-ephemere' ),
												esc_html( human_time_diff( $u_token_exp, $now ) )
											);
											?>
										</small>
										<div style="margin-top: 4px;">
											<a href="<?php echo esc_url( $gen_magic_url ); ?>" class="button button-small" style="display: inline-flex; align-items: center; gap: 3px;" title="<?php esc_attr_e( 'Generate 1-click Magic Link', 'rgbmind-ephemere' ); ?>">
												<span class="dashicons dashicons-update" style="font-size: 13px; width: 13px; height: 13px; line-height: 13px;"></span>
												<?php esc_html_e( 'Generate', 'rgbmind-ephemere' ); ?>
											</a>
										</div>
									<?php else : ?>
										<span style="color: #8c8f94; font-size: 12px;">
											<?php esc_html_e( 'Not active', 'rgbmind-ephemere' ); ?>
										</span>
										<div style="margin-top: 4px;">
											<a href="<?php echo esc_url( $gen_magic_url ); ?>" class="button button-small" style="display: inline-flex; align-items: center; gap: 3px;" title="<?php esc_attr_e( 'Generate 1-click Magic Link', 'rgbmind-ephemere' ); ?>">
												<span class="dashicons dashicons-update" style="font-size: 13px; width: 13px; height: 13px; line-height: 13px;"></span>
												<?php esc_html_e( 'Generate', 'rgbmind-ephemere' ); ?>
											</a>
										</div>
									<?php endif; ?>
								</td>
								<td>
									<strong><?php echo esc_html( $u_count ); ?></strong>
									/
									<span><?php echo ( $u_max > 0 ) ? esc_html( $u_max ) : esc_html__( 'Unlimited', 'rgbmind-ephemere' ); ?></span>
									<?php if ( $u_last > 0 ) : ?>
										<br />
										<small style="color: #646970;">
											<?php
											/* translators: %s: relative time since last login (e.g. 5 minutes) */
											echo esc_html( sprintf( __( 'Last: %s ago', 'rgbmind-ephemere' ), human_time_diff( $u_last, $now ) ) );
											?>
										</small>
									<?php endif; ?>
								</td>
								<td>
									<span style="<?php echo esc_attr( $status_style ); ?>">
										<?php echo esc_html( $status_icon ) . ' ' . esc_html( $status_label ); ?>
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
						<?php if ( empty( $filtered_users ) ) : ?>
							<tr>
								<td colspan="8" style="text-align: center; padding: 24px; color: #646970;">
									<?php esc_html_e( 'No accounts match the current search or filters.', 'rgbmind-ephemere' ); ?>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=rgbmind-ephemere&tab=jit' ) ); ?>"><?php esc_html_e( 'Reset filters', 'rgbmind-ephemere' ); ?></a>
								</td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>
				</form>
				<script>
				( function () {
					var form = document.getElementById( 'rgbmind-bulk-form' );
					if ( ! form ) { return; }
					var action  = document.getElementById( 'rgbmind-bulk-action' );
					var roleSel = document.getElementById( 'rgbmind-bulk-role' );
					var counter = document.getElementById( 'rgbmind-bulk-count' );
					var masters = form.querySelectorAll( '.rgbmind-cb-all' );
					var i18n    = {
						selected: '<?php echo esc_js( __( 'selected', 'rgbmind-ephemere' ) ); ?>',
						noAction: '<?php echo esc_js( __( 'Choose a bulk action first.', 'rgbmind-ephemere' ) ); ?>',
						noRows:   '<?php echo esc_js( __( 'Select at least one account.', 'rgbmind-ephemere' ) ); ?>',
						confirm:  '<?php echo esc_js( __( 'Permanently delete %d account(s)? This cannot be undone.', 'rgbmind-ephemere' ) ); ?>'
					};
					function boxes() { return form.querySelectorAll( 'input[name="rgbmind_ids[]"]' ); }
					function checkedCount() { return form.querySelectorAll( 'input[name="rgbmind_ids[]"]:checked' ).length; }
					function refresh() {
						var n = checkedCount();
						counter.textContent = n ? n + ' ' + i18n.selected : '';
						boxes().forEach( function ( b ) {
							var tr = b.closest( 'tr' );
							if ( tr ) { tr.style.background = b.checked ? '#f0f6fc' : ''; }
						} );
						masters.forEach( function ( m ) { m.checked = n > 0 && n === boxes().length; } );
					}
					masters.forEach( function ( m ) {
						m.addEventListener( 'change', function () {
							boxes().forEach( function ( b ) { b.checked = m.checked; } );
							refresh();
						} );
					} );
					form.addEventListener( 'change', function ( e ) {
						if ( e.target && 'rgbmind_ids[]' === e.target.name ) { refresh(); }
					} );
					action.addEventListener( 'change', function () {
						roleSel.style.display = ( 'change_role' === this.value ) ? '' : 'none';
					} );
					form.addEventListener( 'submit', function ( e ) {
						var n = checkedCount();
						if ( ! action.value ) { e.preventDefault(); window.alert( i18n.noAction ); return; }
						if ( ! n ) { e.preventDefault(); window.alert( i18n.noRows ); return; }
						if ( 'delete' === action.value && ! window.confirm( i18n.confirm.replace( '%d', n ) ) ) { e.preventDefault(); }
					} );
				} )();
				</script>
				<script>
				document.addEventListener('DOMContentLoaded', function() {
					document.querySelectorAll('.rgbmind-copy-ml-btn').forEach(function(btn) {
						btn.addEventListener('click', function(e) {
							e.preventDefault();
							var url = this.getAttribute('data-url');
							if (!url) return;
							var button = this;
							var span = button.querySelector('span:last-child') || button;
							var originalText = span.textContent;

							function showSuccess() {
								span.textContent = '<?php echo esc_js( __( 'Copied!', 'rgbmind-ephemere' ) ); ?>';
								button.style.borderColor = '#007017';
								button.style.color = '#007017';
								setTimeout(function() {
									span.textContent = originalText;
									button.style.borderColor = '';
									button.style.color = '';
								}, 2000);
							}

							if (navigator.clipboard && window.isSecureContext) {
								navigator.clipboard.writeText(url).then(showSuccess, function() {
									fallbackCopy(url);
								});
							} else {
								fallbackCopy(url);
							}

							function fallbackCopy(text) {
								var ta = document.createElement('textarea');
								ta.value = text;
								ta.style.position = 'fixed';
								ta.style.left = '-9999px';
								document.body.appendChild(ta);
								ta.select();
								try {
									document.execCommand('copy');
									showSuccess();
								} catch(err) {
									window.prompt('<?php echo esc_js( __( 'Copy this URL:', 'rgbmind-ephemere' ) ); ?>', text);
								}
								document.body.removeChild(ta);
							}
						});
					});
				});
				</script>
			<?php endif; ?>

		<!-- TAB 2: ROLES & PERMISSIONS (RBAC) -->
		<?php elseif ( in_array( $active_tab, array( 'roles', 'rbac' ), true ) ) : ?>
			<?php
			$wp_roles_obj    = wp_roles();
			$all_roles       = isset( $wp_roles_obj->roles ) ? $wp_roles_obj->roles : array();
			$user_counts     = count_users();
			$avail_counts    = isset( $user_counts['avail_roles'] ) ? $user_counts['avail_roles'] : array();
			$core_role_slugs = array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' );
			$ephemeral_users = get_users(
				array(
					'meta_key'   => '_rgbmind_is_ephemeral', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value' => 1,                       // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
					'number'     => 500,
				)
			);
			$eph_by_role = array();
			foreach ( $ephemeral_users as $u ) {
				if ( ! empty( $u->roles ) && is_array( $u->roles ) ) {
					foreach ( $u->roles as $r ) {
						$eph_by_role[ $r ] = isset( $eph_by_role[ $r ] ) ? $eph_by_role[ $r ] + 1 : 1;
					}
				}
			}
			$temp_admin_count  = isset( $eph_by_role['administrator'] ) ? (int) $eph_by_role['administrator'] : 0;
			$editing_role_slug = isset( $_GET['edit_role'] ) ? sanitize_key( wp_unslash( $_GET['edit_role'] ) ) : '';
			$editing_role_obj  = $editing_role_slug ? get_role( $editing_role_slug ) : null;
			?>

			<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
				<p style="margin: 0; color: #50575e; font-size: 13.5px;">
					<?php esc_html_e( 'Manage WordPress roles and permissions: create tailored roles under the principle of least privilege, adjust capabilities, or delete unused roles.', 'rgbmind-ephemere' ); ?>
				</p>
				<div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
					<button type="button" class="button button-primary" style="display: inline-flex; align-items: center; gap: 5px; background: #137333; border-color: #137333;" onclick="var c=document.getElementById('rgbmind-new-role-card'); if(c.style.display==='none'){c.style.display='block'; document.getElementById('rgbmind_role_name').focus();}else{c.style.display='none';}">
						<span class="dashicons dashicons-plus-alt2" style="font-size: 16px; width: 16px; height: 16px; line-height: 16px;"></span>
						<?php esc_html_e( 'Add New Role', 'rgbmind-ephemere' ); ?>
					</button>
					<?php rgbmind_eph_render_export_menu( 'export_roles', 'rgbmind_eph_export_roles', __( 'Export Roles', 'rgbmind-ephemere' ), array( 'tab' => 'roles' ) ); ?>
					<a href="<?php echo esc_url( admin_url( 'users.php' ) ); ?>" class="button button-secondary">
						<?php esc_html_e( 'Manage WordPress Users', 'rgbmind-ephemere' ); ?> &rarr;
					</a>
				</div>
			</div>

			<!-- Add Role Form Card -->
			<div id="rgbmind-new-role-card" class="card" style="margin-bottom: 25px; padding: 20px 24px; max-width: none; border-left: 4px solid #137333; <?php echo ( isset( $_GET['add_role'] ) ) ? '' : 'display: none;'; ?>">
				<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
					<h3 style="margin: 0; color: #1d2327; display: flex; align-items: center; gap: 6px;">
						<span class="dashicons dashicons-shield" style="color: #137333; font-size: 20px; width: 20px; height: 20px; line-height: 20px;"></span>
						<span><?php esc_html_e( 'Create New WordPress Role', 'rgbmind-ephemere' ); ?></span>
					</h3>
					<button type="button" class="button button-small" onclick="document.getElementById('rgbmind-new-role-card').style.display='none';">&times; <?php esc_html_e( 'Close', 'rgbmind-ephemere' ); ?></button>
				</div>
				<p style="color: #646970; font-size: 13px; margin: 0 0 15px 0;">
					<?php esc_html_e( 'Create a custom role for external consultants, technicians, or auditors with only the capabilities they need.', 'rgbmind-ephemere' ); ?>
				</p>
				<form method="post" action="">
					<?php wp_nonce_field( 'rgbmind_eph_add_role', 'rgbmind_eph_add_role_nonce' ); ?>
					<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; align-items: end;">
						<div>
							<label for="rgbmind_role_name" style="font-weight: 600; display: block; margin-bottom: 4px; font-size: 13px;">
								<?php esc_html_e( 'Role Display Name', 'rgbmind-ephemere' ); ?> <span style="color: #b32d2e;">*</span>
							</label>
							<input type="text" id="rgbmind_role_name" name="rgbmind_role_name" class="regular-text" style="width: 100%;" placeholder="<?php esc_attr_e( 'e.g. External Auditor', 'rgbmind-ephemere' ); ?>" required oninput="var s=document.getElementById('rgbmind_role_slug'); if(!s.dataset.custom){ s.value=this.value.toLowerCase().replace(/[^a-z0-9]/g,'_').replace(/_+/g,'_').replace(/^_|_$/g,''); }" />
						</div>
						<div>
							<label for="rgbmind_role_slug" style="font-weight: 600; display: block; margin-bottom: 4px; font-size: 13px;">
								<?php esc_html_e( 'Role Identifier / Slug', 'rgbmind-ephemere' ); ?> <span style="color: #b32d2e;">*</span>
							</label>
							<input type="text" id="rgbmind_role_slug" name="rgbmind_role_slug" class="regular-text" style="width: 100%;" placeholder="<?php esc_attr_e( 'e.g. external_auditor', 'rgbmind-ephemere' ); ?>" required onchange="this.dataset.custom='1';" />
						</div>
						<div>
							<label for="rgbmind_clone_role" style="font-weight: 600; display: block; margin-bottom: 4px; font-size: 13px;">
								<?php esc_html_e( 'Clone Permissions From', 'rgbmind-ephemere' ); ?>
							</label>
							<select id="rgbmind_clone_role" name="rgbmind_clone_role" style="width: 100%; height: 34px;">
								<option value="subscriber"><?php esc_html_e( 'Subscriber (Read Only)', 'rgbmind-ephemere' ); ?></option>
								<option value="contributor"><?php esc_html_e( 'Contributor (Edit own posts)', 'rgbmind-ephemere' ); ?></option>
								<option value="author"><?php esc_html_e( 'Author (Publish own posts)', 'rgbmind-ephemere' ); ?></option>
								<option value="editor"><?php esc_html_e( 'Editor (Manage all content)', 'rgbmind-ephemere' ); ?></option>
								<option value="administrator"><?php esc_html_e( 'Administrator (Full Access)', 'rgbmind-ephemere' ); ?></option>
								<option value="none"><?php esc_html_e( 'None (Empty Capabilities)', 'rgbmind-ephemere' ); ?></option>
							</select>
						</div>
						<div>
							<button type="submit" class="button button-primary" style="height: 34px; background: #137333; border-color: #137333; display: inline-flex; align-items: center; gap: 5px;">
								<span class="dashicons dashicons-plus-alt2" style="font-size: 16px; width: 16px; height: 16px; line-height: 16px;"></span>
								<?php esc_html_e( 'Add Role', 'rgbmind-ephemere' ); ?>
							</button>
						</div>
					</div>
				</form>
			</div>

			<?php if ( $temp_admin_count > 0 ) : ?>
				<div class="notice notice-warning inline" style="margin: 0 0 20px 0; padding: 12px 16px; border-left-color: #dba617;">
					<p style="margin: 0; font-size: 13px;">
						<strong>⚠️ <?php esc_html_e( 'Least-Privilege Alert:', 'rgbmind-ephemere' ); ?></strong>
						<?php
						printf(
							/* translators: %d: count of temporary administrators */
							esc_html__( 'You currently have %d temporary account(s) with Administrator privileges. Under Zero-Trust security guidelines, ensure these accounts have strict expiration timers and are revoked immediately after maintenance.', 'rgbmind-ephemere' ),
							$temp_admin_count
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<table class="wp-list-table widefat fixed striped table-view-list" style="box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 25px;">
				<thead>
					<tr>
						<th style="width: 22%;"><?php esc_html_e( 'WordPress Role', 'rgbmind-ephemere' ); ?></th>
						<th style="width: 14%;"><?php esc_html_e( 'Type', 'rgbmind-ephemere' ); ?></th>
						<th style="width: 20%;"><?php esc_html_e( 'Privilege Level', 'rgbmind-ephemere' ); ?></th>
						<th style="width: 11%;"><?php esc_html_e( 'Total Users', 'rgbmind-ephemere' ); ?></th>
						<th style="width: 13%;"><?php esc_html_e( 'Temporary', 'rgbmind-ephemere' ); ?></th>
						<th style="width: 20%; text-align: right;"><?php esc_html_e( 'Actions', 'rgbmind-ephemere' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $all_roles as $role_slug => $role_details ) :
						$role_name     = translate_user_role( $role_details['name'] );
						$total_in_role = isset( $avail_counts[ $role_slug ] ) ? (int) $avail_counts[ $role_slug ] : 0;
						$eph_in_role   = isset( $eph_by_role[ $role_slug ] ) ? (int) $eph_by_role[ $role_slug ] : 0;
						$is_core       = in_array( $role_slug, $core_role_slugs, true );

						// Privilege definition
						$badge_bg    = '#f0f0f1';
						$badge_color = '#50575e';
						$badge_text  = __( 'Standard Privilege', 'rgbmind-ephemere' );

						if ( 'administrator' === $role_slug ) {
							$badge_bg    = '#fcf0f1';
							$badge_color = '#b32d2e';
							$badge_text  = __( 'Critical (Full Site Access)', 'rgbmind-ephemere' );
						} elseif ( in_array( $role_slug, array( 'editor', 'shop_manager' ), true ) ) {
							$badge_bg    = '#fef7e0';
							$badge_color = '#996800';
							$badge_text  = __( 'Elevated (Content & Store)', 'rgbmind-ephemere' );
						} elseif ( in_array( $role_slug, array( 'author', 'contributor' ), true ) ) {
							$badge_bg    = '#e7f5ea';
							$badge_color = '#137333';
							$badge_text  = __( 'Standard (Publishing)', 'rgbmind-ephemere' );
						} elseif ( in_array( $role_slug, array( 'subscriber', 'customer' ), true ) ) {
							$badge_bg    = '#f0f6fc';
							$badge_color = '#135e96';
							$badge_text  = __( 'Restricted (Read Only)', 'rgbmind-ephemere' );
						}

						$del_role_nonce = wp_create_nonce( 'rgbmind_eph_delete_role_' . $role_slug );
						$del_role_url   = add_query_arg(
							array(
								'action'   => 'delete_role',
								'role'     => $role_slug,
								'_wpnonce' => $del_role_nonce,
							),
							admin_url( 'admin.php?page=rgbmind-ephemere&tab=roles' )
						);
						?>
						<tr id="role-row-<?php echo esc_attr( $role_slug ); ?>">
							<td>
								<strong><?php echo esc_html( $role_name ); ?></strong>
								<br /><small style="color: #646970;"><code><?php echo esc_html( $role_slug ); ?></code></small>
							</td>
							<td>
								<?php if ( $is_core ) : ?>
									<span style="background: #f0f0f1; color: #50575e; padding: 2px 7px; border-radius: 4px; font-size: 11px; font-weight: 600;">
										<?php esc_html_e( 'Core', 'rgbmind-ephemere' ); ?>
									</span>
								<?php else : ?>
									<span style="background: #e7f5ea; color: #137333; padding: 2px 7px; border-radius: 4px; font-size: 11px; font-weight: 600;">
										<?php esc_html_e( 'Custom', 'rgbmind-ephemere' ); ?>
									</span>
								<?php endif; ?>
							</td>
							<td>
								<span style="display: inline-block; padding: 3px 8px; border-radius: 4px; font-weight: 600; font-size: 11.5px; background: <?php echo esc_attr( $badge_bg ); ?>; color: <?php echo esc_attr( $badge_color ); ?>;">
									<?php echo esc_html( $badge_text ); ?>
								</span>
							</td>
							<td>
								<strong><?php echo esc_html( $total_in_role ); ?></strong>
							</td>
							<td>
								<?php if ( $eph_in_role > 0 ) : ?>
									<span style="color: #007017; font-weight: 700;">⏱️ <?php echo esc_html( $eph_in_role ); ?></span>
								<?php else : ?>
									<span style="color: #8c8f94;">0</span>
								<?php endif; ?>
							</td>
							<td style="text-align: right;">
								<div style="display: flex; gap: 6px; justify-content: flex-end; align-items: center; flex-wrap: wrap;">
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=rgbmind-ephemere&tab=roles&edit_role=' . urlencode( $role_slug ) . '#role-permissions-editor' ) ); ?>" class="button button-small" style="display: inline-flex; align-items: center; gap: 3px;" title="<?php esc_attr_e( 'View & Edit Capabilities', 'rgbmind-ephemere' ); ?>">
										<span class="dashicons dashicons-admin-settings" style="font-size: 14px; width: 14px; height: 14px; line-height: 14px;"></span>
										<?php esc_html_e( 'Permissions', 'rgbmind-ephemere' ); ?>
									</a>

									<a href="<?php echo esc_url( admin_url( 'users.php?role=' . urlencode( $role_slug ) ) ); ?>" class="button button-small" title="<?php esc_attr_e( 'View Users', 'rgbmind-ephemere' ); ?>">
										<?php esc_html_e( 'Users', 'rgbmind-ephemere' ); ?>
									</a>

									<?php if ( ! $is_core ) : ?>
										<?php if ( $total_in_role > 0 ) : ?>
											<button type="button" class="button button-small" disabled title="<?php esc_attr_e( 'Cannot delete role: reassign active users first', 'rgbmind-ephemere' ); ?>" style="opacity: 0.45; color: #b32d2e; cursor: not-allowed;">
												<span class="dashicons dashicons-trash" style="font-size: 13px; width: 13px; height: 13px; line-height: 13px;"></span>
											</button>
										<?php else : ?>
											<a href="<?php echo esc_url( $del_role_url ); ?>" class="button button-small" style="color: #b32d2e; border-color: #b32d2e; display: inline-flex; align-items: center;" title="<?php esc_attr_e( 'Delete this custom role', 'rgbmind-ephemere' ); ?>" onclick="return confirm('<?php echo esc_js( sprintf( __( 'Are you sure you want to permanently delete the custom role &quot;%s&quot;?', 'rgbmind-ephemere' ), $role_name ) ); ?>');">
												<span class="dashicons dashicons-trash" style="font-size: 13px; width: 13px; height: 13px; line-height: 13px;"></span>
											</a>
										<?php endif; ?>
									<?php else : ?>
										<span style="color: #a7aaad; font-size: 11px; padding: 0 4px;" title="<?php esc_attr_e( 'WordPress Core roles cannot be removed', 'rgbmind-ephemere' ); ?>">
											🔒
										</span>
									<?php endif; ?>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<!-- Permissions Editor for Selected Role -->
			<?php if ( $editing_role_obj ) : ?>
				<?php
				$editing_role_name = isset( $all_roles[ $editing_role_slug ]['name'] ) ? translate_user_role( $all_roles[ $editing_role_slug ]['name'] ) : ucfirst( $editing_role_slug );
				$role_caps         = ! empty( $editing_role_obj->capabilities ) ? $editing_role_obj->capabilities : array();
				$manageable_caps   = rgbmind_eph_get_manageable_capabilities();
				?>
				<div id="role-permissions-editor" class="card" style="margin-top: 25px; padding: 25px; max-width: none; border-left: 4px solid #2271b1; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
					<div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #e2e4e7; padding-bottom: 15px; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
						<div>
							<h3 style="margin: 0; color: #1d2327; display: flex; align-items: center; gap: 8px;">
								<span class="dashicons dashicons-admin-settings" style="color: #2271b1; font-size: 22px; width: 22px; height: 22px; line-height: 22px;"></span>
								<span><?php printf( esc_html__( 'Manage Capabilities: %s', 'rgbmind-ephemere' ), esc_html( $editing_role_name ) ); ?></span>
								<code><?php echo esc_html( $editing_role_slug ); ?></code>
							</h3>
							<p style="margin: 5px 0 0 0; color: #646970; font-size: 13px;">
								<?php esc_html_e( 'Add or remove capabilities for this role. Changes take effect immediately for all users assigned to this role.', 'rgbmind-ephemere' ); ?>
							</p>
						</div>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=rgbmind-ephemere&tab=roles' ) ); ?>" class="button button-secondary">
							&times; <?php esc_html_e( 'Close Editor', 'rgbmind-ephemere' ); ?>
						</a>
					</div>

					<form method="post" action="">
						<?php wp_nonce_field( 'rgbmind_eph_save_role_caps', 'rgbmind_eph_role_caps_nonce' ); ?>
						<input type="hidden" name="rgbmind_target_role" value="<?php echo esc_attr( $editing_role_slug ); ?>" />

						<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 25px;">
							<?php foreach ( $manageable_caps as $category_name => $caps ) : ?>
								<div style="background: #f8f9fa; border: 1px solid #dcdcde; border-radius: 6px; padding: 15px;">
									<h4 style="margin: 0 0 10px 0; font-size: 13.5px; color: #1d2327; border-bottom: 1px solid #e2e4e7; padding-bottom: 6px;">
										<?php echo esc_html( $category_name ); ?>
									</h4>
									<div style="display: flex; flex-direction: column; gap: 7px;">
										<?php foreach ( $caps as $cap_key => $cap_label ) : ?>
											<?php
											$is_active       = ! empty( $role_caps[ $cap_key ] );
											$is_locked_admin = ( 'administrator' === $editing_role_slug && in_array( $cap_key, array( 'manage_options', 'read' ), true ) );
											?>
											<label style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #2c3338; cursor: <?php echo $is_locked_admin ? 'not-allowed' : 'pointer'; ?>;">
												<input type="checkbox" name="rgbmind_caps[]" value="<?php echo esc_attr( $cap_key ); ?>" <?php checked( $is_active ); ?> <?php echo $is_locked_admin ? 'disabled="disabled"' : ''; ?> />
												<?php if ( $is_locked_admin ) : ?>
													<input type="hidden" name="rgbmind_caps[]" value="<?php echo esc_attr( $cap_key ); ?>" />
												<?php endif; ?>
												<span><?php echo esc_html( $cap_label ); ?></span>
												<code style="margin-left: auto; font-size: 11px; color: #8c8f94;"><?php echo esc_html( $cap_key ); ?></code>
											</label>
										<?php endforeach; ?>
									</div>
								</div>
							<?php endforeach; ?>
						</div>

						<div style="display: flex; align-items: center; gap: 10px; border-top: 1px solid #e2e4e7; padding-top: 15px;">
							<button type="submit" class="button button-primary" style="display: inline-flex; align-items: center; gap: 5px;">
								<span class="dashicons dashicons-saved" style="font-size: 16px; width: 16px; height: 16px; line-height: 16px;"></span>
								<?php esc_html_e( 'Save Role Permissions', 'rgbmind-ephemere' ); ?>
							</button>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=rgbmind-ephemere&tab=roles' ) ); ?>" class="button button-secondary">
								<?php esc_html_e( 'Cancel', 'rgbmind-ephemere' ); ?>
							</a>
						</div>
					</form>
				</div>
			<?php endif; ?>

		<!-- TAB 3: ACCESS AUDIT LOG (COMPLIANCE & AUDITING) -->
		<?php elseif ( in_array( $active_tab, array( 'audit', 'mac' ), true ) ) : ?>
			<div style="margin-bottom: 15px;">
				<p style="margin: 0; color: #50575e; font-size: 13.5px;">
					<?php esc_html_e( 'Security and compliance audit trail: monitor and export login events for all temporary accounts.', 'rgbmind-ephemere' ); ?>
				</p>
			</div>

			<!-- PRO Hook for Global Audit Trail -->
			<?php do_action( 'rgbmind_eph_render_dashboard_mac_pro' ); ?>

			<?php if ( ! $is_pro ) : ?>
				<div class="card" style="padding: 25px; max-width: none; background: #fafafa; border-left: 4px solid #2271b1;">
					<h3 style="margin-top: 0; color: #1d2327;">
						📋 <?php esc_html_e( 'Global Access Audit Trail & Compliance Alerts', 'rgbmind-ephemere' ); ?>
					</h3>
					<p style="color: #50575e; font-size: 13.5px; max-width: 800px;">
						<?php esc_html_e( 'Need centralized audit records of contractor logins? RGBmind Ephemere PRO provides automated access logs with GDPR-anonymized IP addresses, authentication methods, and real-time security alerts (Email & Webhook).', 'rgbmind-ephemere' ); ?>
					</p>
					<div style="margin-top: 15px;">
						<a href="<?php echo esc_url( RGBMIND_EPH_PRO_URL ); ?>" target="_blank" rel="noopener noreferrer" class="button button-primary">
							<?php esc_html_e( 'Unlock Global Audit Trail in PRO', 'rgbmind-ephemere' ); ?> &rarr;
						</a>
					</div>
				</div>
			<?php endif; ?>

		<!-- TAB 4: SETTINGS & PRO EXTENSIONS -->
		<?php elseif ( 'settings' === $active_tab ) : ?>
			<div style="width: 100%;">
				<?php
				$quick_base_email = get_option( 'rgbmind_eph_quick_base_email', '' );
				if ( empty( $quick_base_email ) ) {
					$quick_base_email = get_option( 'admin_email' );
				}
				$quick_alias_mode         = get_option( 'rgbmind_eph_quick_alias_mode', 'incremental' );
				$quick_default_role       = get_option( 'rgbmind_eph_quick_default_role', 'administrator' );
				$quick_default_duration   = (int) get_option( 'rgbmind_eph_quick_default_duration', 259200 );
				$quick_default_max_logins = (int) get_option( 'rgbmind_eph_quick_default_max_logins', 0 );
				$quick_counter            = (int) get_option( 'rgbmind_eph_quick_counter', 1 );
				?>
				<div class="card" style="margin: 0 0 25px 0; padding: 20px; max-width: none; border-left: 4px solid #137333;">
					<h2 style="margin-top: 0; padding-bottom: 10px; border-bottom: 1px solid #e2e4e7; color: #1d2327; display: flex; align-items: center; gap: 8px;">
						<span>⚡ <?php esc_html_e( '1-Click Quick Account Creation', 'rgbmind-ephemere' ); ?></span>
					</h2>
					<p style="color: #50575e; font-size: 13.5px; margin-bottom: 20px;">
						<?php esc_html_e( 'Configure the default parameters for 1-click temporary account creation from the JIT Access tab. The email alias allows creating multiple temporary accounts without needing separate email addresses.', 'rgbmind-ephemere' ); ?>
					</p>

					<form method="post" action="">
						<?php wp_nonce_field( 'rgbmind_eph_save_quick_settings', 'rgbmind_eph_quick_settings_nonce' ); ?>
						<table class="form-table" role="presentation">
							<tbody>
								<tr>
									<th scope="row">
										<label for="rgbmind_eph_quick_base_email"><?php esc_html_e( 'Base Email Address', 'rgbmind-ephemere' ); ?></label>
									</th>
									<td>
										<input type="email" name="rgbmind_eph_quick_base_email" id="rgbmind_eph_quick_base_email" value="<?php echo esc_attr( $quick_base_email ); ?>" class="regular-text" placeholder="e.g. dev.codeplanet@gmail.com" required="required" />
										<p class="description">
											<?php esc_html_e( 'All quick temporary accounts will be routed through this base email address using plus-addressing (e.g. name+alias@domain.com).', 'rgbmind-ephemere' ); ?>
										</p>
									</td>
								</tr>

								<tr>
									<th scope="row">
										<label><?php esc_html_e( 'Email Alias Strategy', 'rgbmind-ephemere' ); ?></label>
									</th>
									<td>
										<fieldset>
											<label style="display: block; margin-bottom: 8px;">
												<input type="radio" name="rgbmind_eph_quick_alias_mode" value="incremental" <?php checked( $quick_alias_mode, 'incremental' ); ?> />
												<strong><?php esc_html_e( 'Incremental Counter', 'rgbmind-ephemere' ); ?></strong>
												<span style="color: #646970;"> — <?php esc_html_e( 'Generates email+1@domain.com, email+2@domain.com, email+3@domain.com...', 'rgbmind-ephemere' ); ?></span>
											</label>
											<label style="display: block; margin-bottom: 8px;">
												<input type="radio" name="rgbmind_eph_quick_alias_mode" value="role" <?php checked( $quick_alias_mode, 'role' ); ?> />
												<strong><?php esc_html_e( 'Role-based', 'rgbmind-ephemere' ); ?></strong>
												<span style="color: #646970;"> — <?php esc_html_e( 'Generates email+administrator@domain.com, email+editor@domain.com (with auto-suffix if taken)...', 'rgbmind-ephemere' ); ?></span>
											</label>
											<label style="display: block;">
												<input type="radio" name="rgbmind_eph_quick_alias_mode" value="role_incremental" <?php checked( $quick_alias_mode, 'role_incremental' ); ?> />
												<strong><?php esc_html_e( 'Role + Incremental Counter', 'rgbmind-ephemere' ); ?></strong>
												<span style="color: #646970;"> — <?php esc_html_e( 'Generates email+admin_1@domain.com, email+admin_2@domain.com...', 'rgbmind-ephemere' ); ?></span>
											</label>
										</fieldset>
									</td>
								</tr>

								<tr>
									<th scope="row">
										<label for="rgbmind_eph_quick_default_role"><?php esc_html_e( 'Default Role', 'rgbmind-ephemere' ); ?></label>
									</th>
									<td>
										<select name="rgbmind_eph_quick_default_role" id="rgbmind_eph_quick_default_role">
											<?php
											if ( function_exists( 'wp_dropdown_roles' ) ) {
												wp_dropdown_roles( $quick_default_role );
											} else {
												echo '<option value="administrator">Administrator</option><option value="editor">Editor</option>';
											}
											?>
										</select>
										<p class="description">
											<?php esc_html_e( 'Default role assigned to temporary accounts created via 1-click.', 'rgbmind-ephemere' ); ?>
										</p>
									</td>
								</tr>

								<tr>
									<th scope="row">
										<label for="rgbmind_eph_quick_default_duration"><?php esc_html_e( 'Default Expiration', 'rgbmind-ephemere' ); ?></label>
									</th>
									<td>
										<select name="rgbmind_eph_quick_default_duration" id="rgbmind_eph_quick_default_duration">
											<option value="3600" <?php selected( $quick_default_duration, 3600 ); ?>><?php esc_html_e( '1 hour', 'rgbmind-ephemere' ); ?></option>
											<option value="7200" <?php selected( $quick_default_duration, 7200 ); ?>><?php esc_html_e( '2 hours', 'rgbmind-ephemere' ); ?></option>
											<option value="21600" <?php selected( $quick_default_duration, 21600 ); ?>><?php esc_html_e( '6 hours', 'rgbmind-ephemere' ); ?></option>
											<option value="86400" <?php selected( $quick_default_duration, 86400 ); ?>><?php esc_html_e( '24 hours (1 day)', 'rgbmind-ephemere' ); ?></option>
											<option value="172800" <?php selected( $quick_default_duration, 172800 ); ?>><?php esc_html_e( '48 hours (2 days)', 'rgbmind-ephemere' ); ?></option>
											<option value="259200" <?php selected( $quick_default_duration, 259200 ); ?>><?php esc_html_e( '3 days (recommended)', 'rgbmind-ephemere' ); ?></option>
											<option value="604800" <?php selected( $quick_default_duration, 604800 ); ?>><?php esc_html_e( '7 days', 'rgbmind-ephemere' ); ?></option>
											<option value="0" <?php selected( $quick_default_duration, 0 ); ?>><?php esc_html_e( 'No time expiration', 'rgbmind-ephemere' ); ?></option>
										</select>
									</td>
								</tr>

								<tr>
									<th scope="row">
										<label for="rgbmind_eph_quick_default_max_logins"><?php esc_html_e( 'Default Maximum Logins', 'rgbmind-ephemere' ); ?></label>
									</th>
									<td>
										<input type="number" name="rgbmind_eph_quick_default_max_logins" id="rgbmind_eph_quick_default_max_logins" value="<?php echo esc_attr( $quick_default_max_logins ); ?>" min="0" max="999" style="width: 80px;" />
										<p class="description">
											<?php esc_html_e( '0 for unlimited logins within the valid time frame.', 'rgbmind-ephemere' ); ?>
										</p>
									</td>
								</tr>

								<tr>
									<th scope="row">
										<label for="rgbmind_eph_quick_counter"><?php esc_html_e( 'Current Counter Index', 'rgbmind-ephemere' ); ?></label>
									</th>
									<td>
										<input type="number" name="rgbmind_eph_quick_counter" id="rgbmind_eph_quick_counter" value="<?php echo esc_attr( $quick_counter ); ?>" min="1" max="999999" style="width: 100px;" />
										<p class="description">
											<?php esc_html_e( 'The next incremental number used for aliases. Automatically increments upon each creation.', 'rgbmind-ephemere' ); ?>
										</p>
									</td>
								</tr>
							</tbody>
						</table>

						<p class="submit" style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #f0f0f1;">
							<button type="submit" name="rgbmind_eph_save_quick_settings" class="button button-primary">
								<?php esc_html_e( 'Save Quick Creation Settings', 'rgbmind-ephemere' ); ?>
							</button>
						</p>
					</form>
				</div>

				<!-- PRO Hook for Companion Add-on Settings -->
				<?php do_action( 'rgbmind_eph_render_dashboard_pro_settings' ); ?>

				<?php if ( ! $is_pro ) : ?>
					<!-- Informational Showcase: PRO Features Preview -->
					<div class="card" style="margin: 0 0 20px 0; padding: 20px; max-width: none; background: #fafafa; border-left: 4px solid #2271b1;">
						<h2 style="margin-top: 0; padding-bottom: 10px; border-bottom: 1px solid #e2e4e7; color: #1d2327;">
							✨ <?php esc_html_e( 'Available Commercial Add-on: RGBmind Ephemere PRO', 'rgbmind-ephemere' ); ?>
						</h2>
						<p style="color: #50575e; font-size: 13.5px;">
							<?php esc_html_e( 'Looking to enhance your temporary access workflow? RGBmind Ephemere PRO is available as a separate companion plugin featuring:', 'rgbmind-ephemere' ); ?>
						</p>

						<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 15px; margin: 15px 0;">
							<div style="background: #fff; padding: 14px 16px; border: 1px solid #dcdcde; border-radius: 4px;">
								<strong style="display: block; font-size: 13px; color: #1d2327; margin-bottom: 4px;">
									⚡ <?php esc_html_e( 'One-Click Magic Links', 'rgbmind-ephemere' ); ?>
								</strong>
								<span style="font-size: 12.5px; color: #646970;">
									<?php esc_html_e( 'Create single-use, cryptographic login links for passwordless contractor access.', 'rgbmind-ephemere' ); ?>
								</span>
							</div>
							<div style="background: #fff; padding: 14px 16px; border: 1px solid #dcdcde; border-radius: 4px;">
								<strong style="display: block; font-size: 13px; color: #1d2327; margin-bottom: 4px;">
									🛡️ <?php esc_html_e( 'GDPR Access Audit Log', 'rgbmind-ephemere' ); ?>
								</strong>
								<span style="font-size: 12.5px; color: #646970;">
									<?php esc_html_e( 'Track the last 10 logins per user with anonymized IPs and authentication methods.', 'rgbmind-ephemere' ); ?>
								</span>
							</div>
							<div style="background: #fff; padding: 14px 16px; border: 1px solid #dcdcde; border-radius: 4px;">
								<strong style="display: block; font-size: 13px; color: #1d2327; margin-bottom: 4px;">
									🔔 <?php esc_html_e( 'Email & Webhook Alerts', 'rgbmind-ephemere' ); ?>
								</strong>
								<span style="font-size: 12.5px; color: #646970;">
									<?php esc_html_e( 'Get instant notifications on account expiration or lockout (Slack, Discord, Zapier).', 'rgbmind-ephemere' ); ?>
								</span>
							</div>
							<div style="background: #fff; padding: 14px 16px; border: 1px solid #dcdcde; border-radius: 4px;">
								<strong style="display: block; font-size: 13px; color: #1d2327; margin-bottom: 4px;">
									🚀 <?php esc_html_e( 'Unlimited Temporary Accounts', 'rgbmind-ephemere' ); ?>
								</strong>
								<span style="font-size: 12.5px; color: #646970;">
									<?php esc_html_e( 'Free plan allows up to 3 active temporary accounts. PRO unlocks unlimited concurrent accounts.', 'rgbmind-ephemere' ); ?>
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

		<!-- TAB 5: VERSION & ABOUT -->
		<?php else : ?>
			<div style="display: grid; grid-template-columns: 1.8fr 1.2fr; gap: 20px; width: 100%;">
				<!-- Main Column -->
				<div>
					<!-- About & Version Card -->
					<div class="card" style="margin: 0 0 20px 0; padding: 22px; max-width: none; background: #fff; border-left: 4px solid #2271b1;">
						<div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
							<img src="<?php echo esc_url( RGBMIND_EPH_PLUGIN_URL . 'assets/images/logo.png' ); ?>" alt="RGBmind" style="width: 32px; height: 32px; object-fit: contain;" />
							<div>
								<h2 style="margin: 0; font-size: 18px; color: #1d2327;">
									<?php esc_html_e( 'About RGBmind Ephemere', 'rgbmind-ephemere' ); ?>
								</h2>
								<span style="font-size: 12px; color: #646970; font-weight: 500;">
									<?php
									printf(
										/* translators: %s: plugin version */
										esc_html__( 'Version %s • Native WordPress IAM & Temporary Access Manager', 'rgbmind-ephemere' ),
										esc_html( RGBMIND_EPH_VERSION )
									);
									?>
								</span>
							</div>
						</div>

						<p style="color: #50575e; font-size: 13.5px; line-height: 1.6; margin-top: 12px;">
							<?php esc_html_e( 'RGBmind Ephemere provides secure, native temporary user access management for WordPress sites with zero external tracking and zero bloated database tables.', 'rgbmind-ephemere' ); ?>
						</p>

						<ul style="margin: 15px 0; padding-left: 20px; color: #2c3338; font-size: 13px; line-height: 1.8;">
							<li><strong>✔ <?php esc_html_e( '100% Free Core:', 'rgbmind-ephemere' ); ?></strong> <?php esc_html_e( 'Expiration dates, login counters, instant lockouts.', 'rgbmind-ephemere' ); ?></li>
							<li><strong>✔ <?php esc_html_e( 'Session Revocation:', 'rgbmind-ephemere' ); ?></strong> <?php esc_html_e( 'Active expired sessions terminated in real time.', 'rgbmind-ephemere' ); ?></li>
							<li><strong>✔ <?php esc_html_e( 'Clean Architecture:', 'rgbmind-ephemere' ); ?></strong> <?php esc_html_e( 'Uses standard WordPress user metadata without custom database overhead.', 'rgbmind-ephemere' ); ?></li>
							<li><strong>✔ <?php esc_html_e( 'Translation Ready:', 'rgbmind-ephemere' ); ?></strong> <?php esc_html_e( 'Fully internationalized adhering to native WordPress localization standards.', 'rgbmind-ephemere' ); ?></li>
						</ul>

						<p style="margin: 15px 0 0; padding: 10px 14px; background: #f6f7f7; border-radius: 4px; font-size: 12px; color: #646970; border-left: 3px solid #8c8f94; line-height: 1.5;">
							<?php esc_html_e( 'Notice: Software provided "as is" under standard licensing without 1-on-1 personalized support. Technical issues and bug reports are handled exclusively through the official Bug Report & Feedback Hub.', 'rgbmind-ephemere' ); ?>
						</p>

						<div style="margin-top: 18px;">
							<a href="<?php echo esc_url( RGBMIND_EPH_PRO_URL ); ?>" target="_blank" rel="noopener noreferrer" class="button button-secondary">
								<?php esc_html_e( 'Visit RGBmind.com →', 'rgbmind-ephemere' ); ?>
							</a>
						</div>
					</div>

					<!-- Dedicated Bug Report & Feedback Hub Card -->
					<div class="card" style="margin: 0; padding: 22px; max-width: none; background: #fff; border-left: 4px solid #ef4444;">
						<div style="display: flex; align-items: center; gap: 8px; margin-bottom: 10px;">
							<span class="dashicons dashicons-flag" style="font-size: 20px; width: 20px; height: 20px; line-height: 20px; color: #ef4444;"></span>
							<h3 style="margin: 0; font-size: 16px; color: #1d2327;">
								<?php esc_html_e( 'Bug Report & Feedback Hub', 'rgbmind-ephemere' ); ?>
							</h3>
						</div>

						<p style="color: #50575e; font-size: 13.5px; line-height: 1.6; margin-top: 8px;">
							<?php esc_html_e( 'Found a technical issue, regression, or unexpected behavior? We actively welcome reproducible bug reports to help us maintain and refine RGBmind Ephemere.', 'rgbmind-ephemere' ); ?>
						</p>

						<div style="margin-top: 15px;">
							<a href="<?php echo esc_url( apply_filters( 'rgbmind_eph_bug_report_url', RGBMIND_EPH_BUG_REPORT_URL ) ); ?>" target="_blank" rel="noopener noreferrer" class="button button-primary" style="background: #ef4444; border-color: #dc2626; color: #fff; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; padding: 4px 14px; height: auto; font-size: 13px;">
								<span class="dashicons dashicons-flag" style="font-size: 15px; width: 15px; height: 15px; line-height: 15px;"></span>
								<?php esc_html_e( 'Report a Bug at rgbmind.com', 'rgbmind-ephemere' ); ?> &rarr;
							</a>
						</div>
					</div>
				</div>

				<!-- Sidebar Column -->
				<div>
					<!-- Review & Community Support Card -->
					<div class="card" style="margin: 0; padding: 20px; background: #fff; border-top: 4px solid #f0b849;">
						<h3 style="margin-top: 0; font-size: 15px; color: #1d2327;">
							⭐ <?php esc_html_e( 'Enjoying RGBmind Ephemere?', 'rgbmind-ephemere' ); ?>
						</h3>
						<p style="color: #50575e; font-size: 13px; line-height: 1.5;">
							<?php esc_html_e( 'If this plugin is helping you secure your site, please consider leaving a 5-star review on WordPress.org. Your feedback supports continued development!', 'rgbmind-ephemere' ); ?>
						</p>
						<div style="margin-top: 15px; text-align: center;">
							<a href="https://wordpress.org/support/plugin/rgbmind-ephemere/reviews/#new-post" target="_blank" rel="noopener noreferrer" class="button button-secondary" style="width: 100%; text-align: center; text-decoration: none; border-color: #f0b849; color: #855d10; font-weight: 600;">
								★★★★★ <?php esc_html_e( 'Rate 5-Stars on WordPress.org', 'rgbmind-ephemere' ); ?>
							</a>
						</div>
					</div>
				</div>
			</div>
		<?php endif; ?>
		</div>
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
 * Adds row meta links in the plugins list table (wp-admin/plugins.php).
 *
 * @param array  $plugin_meta Existing plugin meta links.
 * @param string $plugin_file Plugin file path.
 * @return array
 */
function rgbmind_eph_add_plugin_row_meta( $plugin_meta, $plugin_file ) {
	if ( plugin_basename( RGBMIND_EPH_PLUGIN_FILE ) === $plugin_file ) {
		$plugin_meta[] = '<a href="https://wordpress.org/support/plugin/rgbmind-ephemere/reviews/#new-post" target="_blank" rel="noopener noreferrer" style="color: #855d10; font-weight: 600;">' . esc_html__( 'Rate ★★★★★', 'rgbmind-ephemere' ) . '</a>';
	}
	return $plugin_meta;
}
add_filter( 'plugin_row_meta', 'rgbmind_eph_add_plugin_row_meta', 10, 2 );

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
				<?php esc_html_e( 'Settings', 'rgbmind-ephemere' ); ?>
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

/**
 * Handles export requests (accounts and roles) in the chosen format.
 *
 * @return void
 */
function rgbmind_eph_handle_export_requests() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Nonce verified below per action.
	if ( ! is_admin() || ! isset( $_GET['page'], $_GET['action'] ) || 'rgbmind-ephemere' !== $_GET['page'] ) {
		return;
	}
	$action = sanitize_key( wp_unslash( $_GET['action'] ) );
	if ( ! in_array( $action, array( 'export_accounts', 'export_roles' ), true ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Unauthorized access.', 'rgbmind-ephemere' ) );
	}
	$format = isset( $_GET['format'] ) ? sanitize_key( wp_unslash( $_GET['format'] ) ) : 'csv';
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	if ( 'export_accounts' === $action ) {
		check_admin_referer( 'rgbmind_eph_export_accounts' );
		$users = get_users(
			array(
				'meta_key'   => '_rgbmind_is_ephemeral', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => 1,                       // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'number'     => 500,
			)
		);
		$users                  = rgbmind_eph_filter_accounts( $users, rgbmind_eph_get_account_filters_from_request() );
		list( $headers, $rows ) = rgbmind_eph_build_accounts_export_rows( $users );
		rgbmind_eph_send_export( 'rgbmind-ephemere-accounts', $headers, $rows, $format );
	}

	check_admin_referer( 'rgbmind_eph_export_roles' );
	$all_roles   = wp_roles()->roles;
	$user_counts = count_users();
	$avail       = isset( $user_counts['avail_roles'] ) ? $user_counts['avail_roles'] : array();
	$eph_users   = get_users(
		array(
			'meta_key'   => '_rgbmind_is_ephemeral', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value' => 1,                       // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'number'     => 500,
		)
	);
	$eph_by_role = array();
	foreach ( $eph_users as $eu ) {
		foreach ( (array) $eu->roles as $r ) {
			$eph_by_role[ $r ] = isset( $eph_by_role[ $r ] ) ? $eph_by_role[ $r ] + 1 : 1;
		}
	}
	$core_roles = array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' );
	$rows       = array();
	foreach ( $all_roles as $slug => $details ) {
		$caps   = array_keys( array_filter( (array) $details['capabilities'] ) );
		$rows[] = array(
			translate_user_role( $details['name'] ),
			$slug,
			in_array( $slug, $core_roles, true ) ? 'Core' : 'Custom',
			isset( $avail[ $slug ] ) ? (int) $avail[ $slug ] : 0,
			isset( $eph_by_role[ $slug ] ) ? (int) $eph_by_role[ $slug ] : 0,
			count( $caps ),
			implode( ', ', $caps ),
		);
	}
	rgbmind_eph_send_export(
		'rgbmind-ephemere-roles',
		array( 'Role', 'Slug', 'Type', 'Total Users', 'Temporary Accounts', 'Capabilities Count', 'Capabilities' ),
		$rows,
		$format
	);
}
add_action( 'admin_init', 'rgbmind_eph_handle_export_requests' );

/**
 * Neutralizes potential CSV formula injection.
 *
 * @param string $val Raw value.
 * @return string
 */
function rgbmind_eph_sanitize_csv_cell( $val ) {
	$val = (string) $val;
	if ( isset( $val[0] ) && in_array( $val[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
		return "'" . $val;
	}
	return $val;
}

/**
 * Returns the available export formats.
 *
 * @return array<string, array<string, string>>
 */
function rgbmind_eph_get_export_formats() {
	$formats = array(
		'csv' => array(
			'label' => __( 'CSV', 'rgbmind-ephemere' ),
			'desc'  => __( 'Universal, opens everywhere', 'rgbmind-ephemere' ),
			'icon'  => 'dashicons-media-text',
		),
	);
	$formats['xlsx'] = array(
		'label' => __( 'Excel (.xlsx)', 'rgbmind-ephemere' ),
		'desc'  => __( 'Native Microsoft Excel workbook', 'rgbmind-ephemere' ),
		'icon'  => 'dashicons-media-spreadsheet',
	);
	$formats['json'] = array(
		'label' => __( 'JSON', 'rgbmind-ephemere' ),
		'desc'  => __( 'Structured data for developers & APIs', 'rgbmind-ephemere' ),
		'icon'  => 'dashicons-editor-code',
	);
	return $formats;
}

/**
 * Builds a ZIP archive in memory (pure PHP, no ZipArchive extension required).
 *
 * @param array<string, string> $files Map of archive path => file contents.
 * @return string Binary ZIP data.
 */
function rgbmind_eph_build_zip( $files ) {
	$data    = '';
	$central = '';
	$offset  = 0;
	$count   = 0;
	foreach ( $files as $name => $content ) {
		$content = (string) $content;
		$crc     = crc32( $content );
		$deflate = function_exists( 'gzdeflate' ) ? gzdeflate( $content, 6 ) : false;
		$method  = ( false !== $deflate ) ? 8 : 0;
		$stored  = ( 8 === $method ) ? $deflate : $content;
		$c_len   = strlen( $stored );
		$u_len   = strlen( $content );
		$n_len   = strlen( $name );

		$local = pack( 'VvvvvvVVVvv', 0x04034b50, 20, 0, $method, 0, 0x21, $crc, $c_len, $u_len, $n_len, 0 ) . $name;
		$data .= $local . $stored;

		$central .= pack( 'VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, $method, 0, 0x21, $crc, $c_len, $u_len, $n_len, 0, 0, 0, 0, 0, $offset ) . $name;

		$offset += strlen( $local ) + $c_len;
		$count++;
	}
	return $data . $central . pack( 'VvvvvVVv', 0x06054b50, 0, 0, $count, $count, strlen( $central ), $offset, 0 );
}

/**
 * Builds an export payload in the requested format.
 *
 * @param string[] $headers Column headers.
 * @param array[]  $rows    Data rows (indexed arrays aligned with headers).
 * @param string   $format  csv|xlsx|json.
 * @return array{body: string, ext: string, mime: string}
 */
function rgbmind_eph_format_export( $headers, $rows, $format ) {
	$headers = array_values( $headers );

	if ( 'json' === $format ) {
		$data = array();
		foreach ( $rows as $row ) {
			$row  = array_values( $row );
			$item = array();
			foreach ( $headers as $i => $h ) {
				$item[ $h ] = isset( $row[ $i ] ) ? $row[ $i ] : '';
			}
			$data[] = $item;
		}
		$flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
		$body  = function_exists( 'wp_json_encode' ) ? wp_json_encode( $data, $flags ) : json_encode( $data, $flags );
		return array(
			'body' => (string) $body,
			'ext'  => 'json',
			'mime' => 'application/json',
		);
	}

	if ( 'xlsx' === $format ) {
		$xml = function ( $v ) {
			$v = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string) $v );
			return htmlspecialchars( $v, ENT_QUOTES | ENT_XML1, 'UTF-8' );
		};
		$cell = function ( $v, $style = 0 ) use ( $xml ) {
			$s = $style ? ' s="' . (int) $style . '"' : '';
			if ( is_int( $v ) || is_float( $v ) ) {
				return '<c' . $s . '><v>' . $v . '</v></c>';
			}
			return '<c t="inlineStr"' . $s . '><is><t xml:space="preserve">' . $xml( $v ) . '</t></is></c>';
		};

		$sheet  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
		$sheet .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><sheetData><row>';
		foreach ( $headers as $h ) {
			$sheet .= $cell( $h, 1 );
		}
		$sheet .= '</row>';
		foreach ( $rows as $row ) {
			$sheet .= '<row>';
			foreach ( array_values( $row ) as $v ) {
				$sheet .= $cell( $v );
			}
			$sheet .= '</row>';
		}
		$sheet .= '</sheetData></worksheet>';

		$files = array(
			'[Content_Types].xml'        => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
			'_rels/.rels'                => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
			'xl/workbook.xml'            => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Export" sheetId="1" r:id="rId1"/></sheets></workbook>',
			'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
			'xl/styles.xml'              => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs></styleSheet>',
			'xl/worksheets/sheet1.xml'   => $sheet,
		);

		return array(
			'body' => rgbmind_eph_build_zip( $files ),
			'ext'  => 'xlsx',
			'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
		);
	}

	$fh = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
	fwrite( $fh, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
	fputcsv( $fh, $headers, ',', '"', '\\' );
	foreach ( $rows as $row ) {
		fputcsv( $fh, array_map( 'rgbmind_eph_sanitize_csv_cell', array_values( $row ) ), ',', '"', '\\' );
	}
	rewind( $fh );
	$body = (string) stream_get_contents( $fh );
	fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

	return array(
		'body' => $body,
		'ext'  => 'csv',
		'mime' => 'text/csv',
	);
}

/**
 * Streams an export file to the browser and terminates the request.
 *
 * @param string   $basename File name prefix (date and extension are appended).
 * @param string[] $headers  Column headers.
 * @param array[]  $rows     Data rows.
 * @param string   $format   csv|xlsx|json.
 * @return void
 */
function rgbmind_eph_send_export( $basename, $headers, $rows, $format ) {
	$formats = rgbmind_eph_get_export_formats();
	if ( ! isset( $formats[ $format ] ) ) {
		$format = 'csv';
	}
	$payload  = rgbmind_eph_format_export( $headers, $rows, $format );
	$filename = sanitize_file_name( $basename . '-' . gmdate( 'Y-m-d' ) . '.' . $payload['ext'] );

	nocache_headers();
	header( 'Content-Type: ' . $payload['mime'] . '; charset=UTF-8' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
	header( 'Content-Length: ' . strlen( $payload['body'] ) );
	echo $payload['body']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary/plain file download.
	exit;
}

/**
 * Renders a dropdown button letting the admin pick the export format.
 *
 * @param string $action       Value of the "action" query arg.
 * @param string $nonce_action Nonce action name.
 * @param string $label        Button label.
 * @param array  $extra_args   Additional query args (e.g. tab, active filters).
 * @return void
 */
function rgbmind_eph_render_export_menu( $action, $nonce_action, $label, $extra_args = array() ) {
	static $assets_printed = false;
	if ( ! $assets_printed ) {
		$assets_printed = true;
		?>
		<style>
			.rgbmind-export-menu { position: relative; display: inline-block; }
			.rgbmind-export-menu > summary { list-style: none; display: inline-flex !important; align-items: center; gap: 4px; cursor: pointer; }
			.rgbmind-export-menu > summary::-webkit-details-marker { display: none; }
			.rgbmind-export-menu[open] > summary { background: #f0f0f1; }
			.rgbmind-export-menu .rgbmind-export-panel { position: absolute; right: 0; top: calc(100% + 4px); z-index: 1000; background: #fff; border: 1px solid #c3c4c7; border-radius: 6px; box-shadow: 0 6px 18px rgba(0,0,0,0.12); min-width: 250px; padding: 6px 0; }
			.rgbmind-export-menu .rgbmind-export-panel-title { padding: 4px 14px 6px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.4px; color: #8c8f94; }
			.rgbmind-export-menu .rgbmind-export-item { display: flex; align-items: center; gap: 10px; padding: 8px 14px; text-decoration: none; color: #1d2327; }
			.rgbmind-export-menu .rgbmind-export-item:hover, .rgbmind-export-menu .rgbmind-export-item:focus { background: #f0f6fc; color: #135e96; outline: none; }
			.rgbmind-export-menu .rgbmind-export-item small { display: block; color: #646970; font-size: 11.5px; }
		</style>
		<script>
			document.addEventListener( 'click', function ( e ) {
				document.querySelectorAll( 'details.rgbmind-export-menu[open]' ).forEach( function ( d ) {
					if ( ! d.contains( e.target ) ) { d.removeAttribute( 'open' ); }
				} );
			} );
		</script>
		<?php
	}
	?>
	<details class="rgbmind-export-menu">
		<summary class="button button-secondary">
			<span class="dashicons dashicons-download" style="font-size: 16px; width: 16px; height: 16px; line-height: 16px;"></span>
			<?php echo esc_html( $label ); ?>
			<span class="dashicons dashicons-arrow-down-alt2" style="font-size: 13px; width: 13px; height: 13px; line-height: 13px;"></span>
		</summary>
		<div class="rgbmind-export-panel">
			<div class="rgbmind-export-panel-title"><?php esc_html_e( 'Choose file format', 'rgbmind-ephemere' ); ?></div>
			<?php foreach ( rgbmind_eph_get_export_formats() as $fmt_key => $fmt ) : ?>
				<?php
				$fmt_url = wp_nonce_url(
					add_query_arg(
						array_merge(
							array(
								'page'   => 'rgbmind-ephemere',
								'action' => $action,
								'format' => $fmt_key,
							),
							$extra_args
						),
						admin_url( 'admin.php' )
					),
					$nonce_action
				);
				?>
				<a class="rgbmind-export-item" href="<?php echo esc_url( $fmt_url ); ?>" onclick="this.closest('details').removeAttribute('open');">
					<span class="dashicons <?php echo esc_attr( $fmt['icon'] ); ?>" style="color: #2271b1;"></span>
					<span><strong><?php echo esc_html( $fmt['label'] ); ?></strong><small><?php echo esc_html( $fmt['desc'] ); ?></small></span>
				</a>
			<?php endforeach; ?>
		</div>
	</details>
	<?php
}

/**
 * Returns the status key of a temporary account.
 *
 * @param int $user_id User ID.
 * @return string active|locked|expired|limit
 */
function rgbmind_eph_get_account_status_key( $user_id ) {
	$locked = (int) get_user_meta( $user_id, '_rgbmind_is_locked', true );
	$expire = (int) get_user_meta( $user_id, '_rgbmind_expire_at', true );
	$max    = (int) get_user_meta( $user_id, '_rgbmind_max_logins', true );
	$count  = (int) get_user_meta( $user_id, '_rgbmind_login_count', true );

	if ( 1 === $locked ) {
		return 'locked';
	}
	if ( $expire > 0 && time() >= $expire ) {
		return 'expired';
	}
	if ( $max > 0 && $count >= $max ) {
		return 'limit';
	}
	return 'active';
}

/**
 * Returns the Magic Link state of an account.
 *
 * @param int $user_id User ID.
 * @return string active|expired|none
 */
function rgbmind_eph_get_magic_state_key( $user_id ) {
	$exp = (int) get_user_meta( $user_id, '_rgbmind_magic_token_exp', true );
	if ( 0 === $exp ) {
		$exp = (int) get_user_meta( $user_id, '_rgbmind_magic_token_expires', true );
	}
	if ( $exp > time() ) {
		return 'active';
	}
	return ( $exp > 0 ) ? 'expired' : 'none';
}

/**
 * Reads the JIT accounts filters (search, status, role, magic link) from the query string.
 *
 * @return array{s: string, status: string, role: string, ml: string}
 */
function rgbmind_eph_get_account_filters_from_request() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only list filters.
	$filters = array(
		's'      => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
		'status' => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '',
		'role'   => isset( $_GET['role'] ) ? sanitize_key( wp_unslash( $_GET['role'] ) ) : '',
		'ml'     => isset( $_GET['ml'] ) ? sanitize_key( wp_unslash( $_GET['ml'] ) ) : '',
	);
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
	return $filters;
}

/**
 * Filters a list of temporary accounts.
 *
 * @param WP_User[] $users   Users.
 * @param array     $filters Filters (s, status, role, ml).
 * @return WP_User[]
 */
function rgbmind_eph_filter_accounts( $users, $filters ) {
	$search = isset( $filters['s'] ) ? strtolower( trim( (string) $filters['s'] ) ) : '';
	$out    = array();
	foreach ( $users as $u ) {
		if ( '' !== $search ) {
			$haystack = strtolower( $u->user_login . ' ' . $u->user_email . ' ' . $u->display_name );
			if ( false === strpos( $haystack, $search ) ) {
				continue;
			}
		}
		if ( ! empty( $filters['status'] ) && rgbmind_eph_get_account_status_key( $u->ID ) !== $filters['status'] ) {
			continue;
		}
		if ( ! empty( $filters['role'] ) && ( empty( $u->roles ) || ! in_array( $filters['role'], (array) $u->roles, true ) ) ) {
			continue;
		}
		if ( ! empty( $filters['ml'] ) && rgbmind_eph_get_magic_state_key( $u->ID ) !== $filters['ml'] ) {
			continue;
		}
		$out[] = $u;
	}
	return $out;
}

/**
 * Builds export headers and rows for temporary accounts.
 *
 * @param WP_User[] $users Users.
 * @return array{0: string[], 1: array[]}
 */
function rgbmind_eph_build_accounts_export_rows( $users ) {
	$headers       = array( 'User ID', 'Username', 'Display Name', 'Email', 'Roles', 'Created Date (UTC)', 'Expiration', 'Login Count', 'Max Logins', 'Last Login', 'Magic Link', 'Magic Link Expiration', 'Status' );
	$status_labels = array(
		'active'  => 'Active',
		'locked'  => 'Locked',
		'expired' => 'Expired',
		'limit'   => 'Limit reached',
	);
	$ml_labels     = array(
		'active'  => 'Active',
		'expired' => 'Expired',
		'none'    => 'Not generated',
	);

	$rows = array();
	foreach ( $users as $u ) {
		$u_id    = (int) $u->ID;
		$expire  = (int) get_user_meta( $u_id, '_rgbmind_expire_at', true );
		$max     = (int) get_user_meta( $u_id, '_rgbmind_max_logins', true );
		$count   = (int) get_user_meta( $u_id, '_rgbmind_login_count', true );
		$last    = (int) get_user_meta( $u_id, '_rgbmind_last_login_time', true );
		$ml_exp  = (int) get_user_meta( $u_id, '_rgbmind_magic_token_exp', true );
		$ml_key  = rgbmind_eph_get_magic_state_key( $u_id );
		$st_key  = rgbmind_eph_get_account_status_key( $u_id );

		$rows[] = array(
			$u_id,
			(string) $u->user_login,
			(string) $u->display_name,
			(string) $u->user_email,
			! empty( $u->roles ) && is_array( $u->roles ) ? implode( ', ', $u->roles ) : '',
			! empty( $u->user_registered ) ? gmdate( 'Y-m-d H:i:s', strtotime( $u->user_registered ) ) : '',
			$expire > 0 ? wp_date( 'Y-m-d H:i:s', $expire ) : 'No limit',
			$count,
			$max > 0 ? $max : 'Unlimited',
			$last > 0 ? wp_date( 'Y-m-d H:i:s', $last ) : '',
			$ml_labels[ $ml_key ],
			$ml_exp > 0 ? wp_date( 'Y-m-d H:i:s', $ml_exp ) : '',
			$status_labels[ $st_key ],
		);
	}
	return array( $headers, $rows );
}

/**
 * Quick-create duration presets (seconds => label).
 *
 * @return array<int, string>
 */
function rgbmind_eph_get_quick_duration_options() {
	return array(
		3600    => __( '1 hour', 'rgbmind-ephemere' ),
		14400   => __( '4 hours', 'rgbmind-ephemere' ),
		28800   => __( '8 hours', 'rgbmind-ephemere' ),
		86400   => __( '1 day', 'rgbmind-ephemere' ),
		259200  => __( '3 days', 'rgbmind-ephemere' ),
		604800  => __( '7 days', 'rgbmind-ephemere' ),
		1209600 => __( '14 days', 'rgbmind-ephemere' ),
		2592000 => __( '30 days', 'rgbmind-ephemere' ),
		0       => __( 'No expiration', 'rgbmind-ephemere' ),
	);
}

/**
 * Returns the maximum allowed active temporary accounts in the Free version.
 *
 * @return int Maximum active temporary accounts in Free (default: 3).
 */
function rgbmind_eph_get_free_active_accounts_limit() {
	return (int) apply_filters( 'rgbmind_eph_free_active_accounts_limit', 3 );
}

/**
 * Counts the number of currently active temporary accounts.
 *
 * @return int Number of active temporary accounts.
 */
function rgbmind_eph_count_active_temporary_accounts() {
	if ( ! function_exists( 'get_users' ) ) {
		return 0;
	}
	// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Needed to count active accounts.
	$ephemeral_ids = get_users(
		array(
			'meta_key'   => '_rgbmind_is_ephemeral', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value' => 1,                       // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'fields'     => 'ID',
		)
	);
	$active_count = 0;
	foreach ( $ephemeral_ids as $uid ) {
		if ( 'active' === rgbmind_eph_get_account_status_key( $uid ) ) {
			$active_count++;
		}
	}
	return $active_count;
}

/**
 * Checks if a new temporary account can be created under current license/limits.
 *
 * @return bool True if creation is permitted, false if free limit is reached.
 */
function rgbmind_eph_can_create_temporary_account() {
	if ( rgbmind_eph_is_pro_active() ) {
		return true;
	}
	return rgbmind_eph_count_active_temporary_accounts() < rgbmind_eph_get_free_active_accounts_limit();
}

/**
 * Displays admin notice if user edit was blocked by Free limit.
 *
 * @return void
 */
function rgbmind_eph_display_user_edit_notices() {
	$t_key  = 'rgbmind_eph_user_edit_notice_' . get_current_user_id();
	$notice = get_transient( $t_key );
	if ( $notice ) {
		delete_transient( $t_key );
		if ( 'free_limit_reached' === $notice ) {
			echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( '⚠️ Free Version Limit: You have reached the maximum of 3 concurrent active temporary accounts. Please upgrade to RGBmind Ephemere PRO to unlock unlimited temporary accounts.', 'rgbmind-ephemere' ) . '</p></div>';
		}
	}
}
add_action( 'admin_notices', 'rgbmind_eph_display_user_edit_notices' );

/**
 * Renders the quick-create form with on-the-fly role and duration selectors.
 *
 * @return void
 */
function rgbmind_eph_render_quick_create_form() {
	$roles = function_exists( 'get_editable_roles' ) ? get_editable_roles() : wp_roles()->roles;
	$uid   = get_current_user_id();

	$sel_role = (string) get_user_meta( $uid, '_rgbmind_last_quick_role', true );
	if ( '' === $sel_role || ! isset( $roles[ $sel_role ] ) ) {
		$sel_role = (string) get_option( 'rgbmind_eph_quick_default_role', 'administrator' );
	}

	$last_dur = get_user_meta( $uid, '_rgbmind_last_quick_duration', true );
	$sel_dur  = ( '' !== $last_dur ) ? (int) $last_dur : (int) get_option( 'rgbmind_eph_quick_default_duration', 259200 );

	$durations = rgbmind_eph_get_quick_duration_options();
	if ( ! isset( $durations[ $sel_dur ] ) ) {
		$durations[ $sel_dur ] = human_time_diff( 0, $sel_dur );
	}
	$select_style = 'border: 0; border-right: 1px solid #c3c4c7; border-radius: 0; min-height: 30px; padding: 0 26px 0 10px; font-size: 13px; box-shadow: none; margin: 0; background-color: #fff;';
	$is_pro        = rgbmind_eph_is_pro_active();
	$active_count  = rgbmind_eph_count_active_temporary_accounts();
	$free_limit    = rgbmind_eph_get_free_active_accounts_limit();
	$limit_reached = ( ! $is_pro && $active_count >= $free_limit );
	?>
	<form method="post" action="" class="rgbmind-quick-create" style="display: inline-flex; align-items: stretch; margin: 0; border: 1px solid <?php echo $limit_reached ? '#e3a824' : '#137333'; ?>; border-radius: 4px; overflow: hidden; background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,0.04);">
		<?php wp_nonce_field( 'rgbmind_eph_quick_create', 'rgbmind_eph_quick_create_nonce' ); ?>
		<span style="display: inline-flex; align-items: center; padding: 0 0 0 10px; color: <?php echo $limit_reached ? '#b06000' : '#137333'; ?>;" aria-hidden="true">
			<span class="dashicons dashicons-admin-users" style="font-size: 16px; width: 16px; height: 16px; line-height: 16px;"></span>
		</span>
		<select name="rgbmind_quick_role" aria-label="<?php esc_attr_e( 'Role', 'rgbmind-ephemere' ); ?>" title="<?php esc_attr_e( 'Role', 'rgbmind-ephemere' ); ?>" style="<?php echo esc_attr( $select_style ); ?> max-width: 170px;" <?php disabled( $limit_reached, true ); ?>>
			<?php foreach ( $roles as $role_slug => $role_info ) : ?>
				<option value="<?php echo esc_attr( $role_slug ); ?>" <?php selected( $sel_role, $role_slug ); ?>><?php echo esc_html( translate_user_role( $role_info['name'] ) ); ?></option>
			<?php endforeach; ?>
		</select>
		<span style="display: inline-flex; align-items: center; padding: 0 0 0 10px; color: <?php echo $limit_reached ? '#b06000' : '#137333'; ?>;" aria-hidden="true">
			<span class="dashicons dashicons-clock" style="font-size: 16px; width: 16px; height: 16px; line-height: 16px;"></span>
		</span>
		<select name="rgbmind_quick_duration" aria-label="<?php esc_attr_e( 'Duration', 'rgbmind-ephemere' ); ?>" title="<?php esc_attr_e( 'Account duration', 'rgbmind-ephemere' ); ?>" style="<?php echo esc_attr( $select_style ); ?>" <?php disabled( $limit_reached, true ); ?>>
			<?php foreach ( $durations as $secs => $dur_label ) : ?>
				<option value="<?php echo esc_attr( $secs ); ?>" <?php selected( $sel_dur, (int) $secs ); ?>><?php echo esc_html( $dur_label ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php if ( $limit_reached ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=rgbmind-ephemere&tab=settings' ) ); ?>" class="button" style="display: inline-flex; align-items: center; gap: 6px; background: #fef7e0; color: #b06000; border: 0; border-radius: 0; margin: 0; min-height: 30px; font-weight: 600; text-decoration: none;" title="<?php esc_attr_e( 'Free limit reached. Click to upgrade to PRO.', 'rgbmind-ephemere' ); ?>">
				<span class="dashicons dashicons-lock" style="font-size: 15px; width: 15px; height: 15px; line-height: 15px; color: #b06000;"></span>
				<?php printf( esc_html__( 'Free Limit Reached (%1$d/%2$d) – Upgrade to PRO', 'rgbmind-ephemere' ), (int) $active_count, (int) $free_limit ); ?>
			</a>
		<?php else : ?>
			<button type="submit" name="rgbmind_eph_do_quick_create" value="1" class="button button-primary" style="display: inline-flex; align-items: center; gap: 5px; background: #137333; border: 0; border-radius: 0; margin: 0; min-height: 30px;">
				<span class="dashicons dashicons-plus-alt2" style="font-size: 16px; width: 16px; height: 16px; line-height: 16px;"></span>
				<?php esc_html_e( 'Create Temporary User', 'rgbmind-ephemere' ); ?>
			</button>
		<?php endif; ?>
	</form>
	<?php
}

/**
 * Handles bulk actions on temporary accounts (delete, change role, lock, unlock, magic link, export).
 *
 * @return void
 */
function rgbmind_eph_handle_bulk_actions() {
	if ( ! is_admin() || ! isset( $_POST['rgbmind_eph_bulk_nonce'] ) ) {
		return;
	}
	if ( ! current_user_can( 'promote_users' ) && ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Unauthorized access.', 'rgbmind-ephemere' ) );
	}
	check_admin_referer( 'rgbmind_eph_bulk_action', 'rgbmind_eph_bulk_nonce' );

	$action   = isset( $_POST['rgbmind_bulk_action'] ) ? sanitize_key( wp_unslash( $_POST['rgbmind_bulk_action'] ) ) : '';
	$raw_ids  = isset( $_POST['rgbmind_ids'] ) ? (array) wp_unslash( $_POST['rgbmind_ids'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Cast to int below.
	$ids      = array_filter( array_map( 'absint', $raw_ids ) );
	$referer  = wp_get_referer();
	$redirect = $referer ? $referer : admin_url( 'admin.php?page=rgbmind-ephemere&tab=jit' );
	$redirect = remove_query_arg( array( 'rgbmind_notice', 'rgbmind_bulk', 'rgbmind_count', 'quick_created' ), $redirect );

	// Only temporary accounts, never the current user.
	$current = get_current_user_id();
	$targets = array();
	foreach ( $ids as $id ) {
		if ( $id !== $current && 1 === (int) get_user_meta( $id, '_rgbmind_is_ephemeral', true ) ) {
			$targets[] = $id;
		}
	}

	if ( '' === $action || empty( $targets ) ) {
		wp_safe_redirect( add_query_arg( 'rgbmind_notice', 'bulk_none', $redirect ) );
		exit;
	}

	if ( 0 === strpos( $action, 'export_' ) ) {
		$users = array();
		foreach ( $targets as $id ) {
			$u = get_user_by( 'id', $id );
			if ( $u ) {
				$users[] = $u;
			}
		}
		list( $headers, $rows ) = rgbmind_eph_build_accounts_export_rows( $users );
		rgbmind_eph_send_export( 'rgbmind-ephemere-accounts-selected', $headers, $rows, substr( $action, 7 ) );
	}

	$done = 0;
	switch ( $action ) {
		case 'delete':
			if ( ! current_user_can( 'delete_users' ) ) {
				wp_die( esc_html__( 'You are not allowed to delete users.', 'rgbmind-ephemere' ) );
			}
			if ( ! function_exists( 'wp_delete_user' ) ) {
				require_once ABSPATH . 'wp-admin/includes/user.php';
			}
			foreach ( $targets as $id ) {
				if ( wp_delete_user( $id ) ) {
					$done++;
				}
			}
			break;

		case 'change_role':
			$new_role = isset( $_POST['rgbmind_bulk_role'] ) ? sanitize_key( wp_unslash( $_POST['rgbmind_bulk_role'] ) ) : '';
			$editable = function_exists( 'get_editable_roles' ) ? get_editable_roles() : array();
			if ( '' === $new_role || ( ! empty( $editable ) && ! isset( $editable[ $new_role ] ) ) ) {
				wp_safe_redirect( add_query_arg( 'rgbmind_notice', 'bulk_none', $redirect ) );
				exit;
			}
			foreach ( $targets as $id ) {
				$u = get_user_by( 'id', $id );
				if ( $u ) {
					$u->set_role( $new_role );
					$done++;
				}
			}
			break;

		case 'lock':
			foreach ( $targets as $id ) {
				update_user_meta( $id, '_rgbmind_is_locked', 1 );
				$u = get_user_by( 'id', $id );
				if ( $u instanceof WP_User ) {
					do_action( 'rgbmind_eph_account_locked_or_expired', $u, 'manual_lock' );
				}
				$done++;
			}
			break;

		case 'unlock':
			foreach ( $targets as $id ) {
				update_user_meta( $id, '_rgbmind_is_locked', 0 );
				$done++;
			}
			break;

		case 'magic':
			if ( rgbmind_eph_is_pro_active() && function_exists( 'rgbmind_eph_pro_generate_magic_token' ) ) {
				foreach ( $targets as $id ) {
					rgbmind_eph_pro_generate_magic_token( $id );
					$done++;
				}
			}
			break;
	}

	wp_safe_redirect(
		add_query_arg(
			array(
				'rgbmind_notice' => 'bulk_done',
				'rgbmind_bulk'   => $action,
				'rgbmind_count'  => $done,
			),
			$redirect
		)
	);
	exit;
}
add_action( 'admin_init', 'rgbmind_eph_handle_bulk_actions' );

/**
 * Creates a temporary user on the fly based on Quick Creation settings.
 *
 * @return array|WP_Error User details array on success, WP_Error on failure.
 */
function rgbmind_eph_create_quick_temporary_user( $override_role = '', $override_duration = null ) {
	if ( ! current_user_can( 'promote_users' ) && ! current_user_can( 'manage_options' ) ) {
		return new WP_Error( 'unauthorized', __( 'Unauthorized access.', 'rgbmind-ephemere' ) );
	}

	if ( ! rgbmind_eph_can_create_temporary_account() ) {
		return new WP_Error(
			'free_limit_reached',
			__( 'You have reached the maximum limit of 3 active temporary accounts in the Free version. Upgrade to RGBmind Ephemere PRO to unlock unlimited temporary accounts.', 'rgbmind-ephemere' )
		);
	}

	$base_email = trim( (string) get_option( 'rgbmind_eph_quick_base_email', '' ) );
	if ( empty( $base_email ) || ! is_email( $base_email ) ) {
		$base_email = get_option( 'admin_email' );
	}

	$alias_mode     = get_option( 'rgbmind_eph_quick_alias_mode', 'incremental' );
	$default_role   = get_option( 'rgbmind_eph_quick_default_role', 'administrator' );
	$editable_roles = function_exists( 'get_editable_roles' ) ? get_editable_roles() : array( 'administrator' => true, 'editor' => true );
	if ( ! isset( $editable_roles[ $default_role ] ) ) {
		$default_role = 'administrator';
	}
	if ( '' !== $override_role && isset( $editable_roles[ $override_role ] ) ) {
		$default_role = $override_role;
	}

	$duration   = (int) get_option( 'rgbmind_eph_quick_default_duration', 259200 );
	if ( null !== $override_duration ) {
		$duration = min( max( 0, (int) $override_duration ), 31536000 );
	}
	$max_logins = (int) get_option( 'rgbmind_eph_quick_default_max_logins', 0 );
	$counter    = (int) get_option( 'rgbmind_eph_quick_counter', 1 );
	if ( $counter < 1 ) {
		$counter = 1;
	}

	$parts      = explode( '@', $base_email, 2 );
	$local_base = explode( '+', $parts[0] )[0];
	$domain     = isset( $parts[1] ) ? $parts[1] : 'example.com';

	$unique_email    = '';
	$unique_username = '';
	$attempt         = 0;
	$max_attempts    = 500;

	while ( $attempt < $max_attempts ) {
		$attempt++;

		if ( 'role' === $alias_mode ) {
			$suffix          = ( 1 === $attempt ) ? '' : '_' . $attempt;
			$unique_email    = $local_base . '+' . sanitize_key( $default_role ) . $suffix . '@' . $domain;
			$unique_username = 'temp_' . sanitize_key( $default_role ) . $suffix;
		} elseif ( 'role_incremental' === $alias_mode ) {
			$unique_email    = $local_base . '+' . sanitize_key( $default_role ) . '_' . $counter . '@' . $domain;
			$unique_username = 'temp_' . sanitize_key( $default_role ) . '_' . $counter;
		} else {
			// incremental
			$unique_email    = $local_base . '+' . $counter . '@' . $domain;
			$unique_username = 'temp_' . $counter;
		}

		$user_exists = function_exists( 'username_exists' ) ? username_exists( $unique_username ) : false;
		$mail_exists = function_exists( 'email_exists' ) ? email_exists( $unique_email ) : false;

		if ( ! $user_exists && ! $mail_exists ) {
			if ( 'role' !== $alias_mode ) {
				update_option( 'rgbmind_eph_quick_counter', $counter + 1 );
			}
			break;
		}

		$counter++;
	}

	if ( empty( $unique_email ) || empty( $unique_username ) ) {
		return new WP_Error( 'collision_error', __( 'Could not find a unique username and email. Please check your settings.', 'rgbmind-ephemere' ) );
	}

	$random_password = wp_generate_password( 24, true, true );
	$user_id         = wp_create_user( $unique_username, $random_password, $unique_email );
	if ( is_wp_error( $user_id ) ) {
		return $user_id;
	}

	$user = get_user_by( 'id', $user_id );
	if ( $user ) {
		if ( method_exists( $user, 'set_role' ) ) {
			$user->set_role( $default_role );
		} else {
			$user->roles = array( $default_role );
		}
		$user->display_name = ucfirst( str_replace( '_', ' ', $unique_username ) );
		if ( function_exists( 'wp_update_user' ) ) {
			wp_update_user( $user );
		}
	}

	update_user_meta( $user_id, '_rgbmind_is_ephemeral', 1 );
	if ( $duration > 0 ) {
		update_user_meta( $user_id, '_rgbmind_expire_at', time() + $duration );
	} else {
		update_user_meta( $user_id, '_rgbmind_expire_at', 0 );
	}
	update_user_meta( $user_id, '_rgbmind_max_logins', $max_logins );
	update_user_meta( $user_id, '_rgbmind_login_count', 0 );
	update_user_meta( $user_id, '_rgbmind_is_locked', 0 );

	$magic_link = '';
	if ( rgbmind_eph_is_pro_active() && function_exists( 'rgbmind_eph_pro_generate_magic_token' ) ) {
		$token      = rgbmind_eph_pro_generate_magic_token( $user_id );
		$magic_link = add_query_arg(
			array(
				'rgbmind_magic_auth' => $token,
				'rgbmind_uid'        => $user_id,
			),
			wp_login_url()
		);
	}

	$formatted_expire = ( $duration > 0 )
		? sprintf(
			/* translators: 1: human readable diff, 2: formatted date */
			__( 'in %1$s (%2$s)', 'rgbmind-ephemere' ),
			human_time_diff( time(), time() + $duration ),
			wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), time() + $duration )
		)
		: __( 'No expiration date', 'rgbmind-ephemere' );

	return array(
		'user_id'    => $user_id,
		'username'   => $unique_username,
		'email'      => $unique_email,
		'role'       => ucfirst( $default_role ),
		'password'   => $random_password,
		'expires'    => $formatted_expire,
		'magic_link' => $magic_link,
	);
}

/**
 * Handles 1-click quick account creation POST request.
 *
 * @return void
 */
function rgbmind_eph_handle_quick_account_creation() {
	if ( ! is_admin() || ! isset( $_POST['rgbmind_eph_do_quick_create'] ) ) {
		return;
	}
	if ( ! current_user_can( 'promote_users' ) && ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Unauthorized access.', 'rgbmind-ephemere' ) );
	}
	check_admin_referer( 'rgbmind_eph_quick_create', 'rgbmind_eph_quick_create_nonce' );

	$q_role     = isset( $_POST['rgbmind_quick_role'] ) ? sanitize_key( wp_unslash( $_POST['rgbmind_quick_role'] ) ) : '';
	$q_duration = isset( $_POST['rgbmind_quick_duration'] ) ? absint( wp_unslash( $_POST['rgbmind_quick_duration'] ) ) : null;

	$user_data = rgbmind_eph_create_quick_temporary_user( $q_role, $q_duration );
	if ( '' !== $q_role ) {
		update_user_meta( get_current_user_id(), '_rgbmind_last_quick_role', $q_role );
	}
	if ( null !== $q_duration ) {
		update_user_meta( get_current_user_id(), '_rgbmind_last_quick_duration', $q_duration );
	}
	if ( is_wp_error( $user_data ) ) {
		if ( 'free_limit_reached' === $user_data->get_error_code() ) {
			wp_safe_redirect( admin_url( 'admin.php?page=rgbmind-ephemere&tab=jit&rgbmind_notice=free_limit_reached' ) );
			exit;
		}
		wp_die( esc_html( $user_data->get_error_message() ) );
	}

	set_transient( 'rgbmind_eph_quick_created_' . get_current_user_id(), $user_data, 60 );
	wp_safe_redirect( admin_url( 'admin.php?page=rgbmind-ephemere&tab=jit&quick_created=1' ) );
	exit;
}
add_action( 'admin_init', 'rgbmind_eph_handle_quick_account_creation' );

/**
 * Handles saving Quick Creation settings form.
 *
 * @return void
 */
function rgbmind_eph_handle_quick_settings_save() {
	if ( ! is_admin() || ! isset( $_POST['rgbmind_eph_save_quick_settings'] ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Unauthorized access.', 'rgbmind-ephemere' ) );
	}
	check_admin_referer( 'rgbmind_eph_save_quick_settings', 'rgbmind_eph_quick_settings_nonce' );

	if ( isset( $_POST['rgbmind_eph_quick_base_email'] ) ) {
		$base_email = sanitize_email( wp_unslash( $_POST['rgbmind_eph_quick_base_email'] ) );
		if ( is_email( $base_email ) ) {
			update_option( 'rgbmind_eph_quick_base_email', $base_email );
		}
	}

	if ( isset( $_POST['rgbmind_eph_quick_alias_mode'] ) ) {
		$alias_mode = sanitize_key( wp_unslash( $_POST['rgbmind_eph_quick_alias_mode'] ) );
		if ( in_array( $alias_mode, array( 'incremental', 'role', 'role_incremental' ), true ) ) {
			update_option( 'rgbmind_eph_quick_alias_mode', $alias_mode );
		}
	}

	if ( isset( $_POST['rgbmind_eph_quick_default_role'] ) ) {
		$default_role   = sanitize_key( wp_unslash( $_POST['rgbmind_eph_quick_default_role'] ) );
		$editable_roles = function_exists( 'get_editable_roles' ) ? get_editable_roles() : array();
		if ( empty( $editable_roles ) || isset( $editable_roles[ $default_role ] ) ) {
			update_option( 'rgbmind_eph_quick_default_role', $default_role );
		}
	}

	if ( isset( $_POST['rgbmind_eph_quick_default_duration'] ) ) {
		$default_duration = absint( wp_unslash( $_POST['rgbmind_eph_quick_default_duration'] ) );
		update_option( 'rgbmind_eph_quick_default_duration', $default_duration );
	}

	if ( isset( $_POST['rgbmind_eph_quick_default_max_logins'] ) ) {
		$default_max_logins = absint( wp_unslash( $_POST['rgbmind_eph_quick_default_max_logins'] ) );
		update_option( 'rgbmind_eph_quick_default_max_logins', $default_max_logins );
	}

	if ( isset( $_POST['rgbmind_eph_quick_counter'] ) ) {
		$counter = max( 1, absint( wp_unslash( $_POST['rgbmind_eph_quick_counter'] ) ) );
		update_option( 'rgbmind_eph_quick_counter', $counter );
	}

	wp_safe_redirect( admin_url( 'admin.php?page=rgbmind-ephemere&tab=settings&rgbmind_eph_notice=quick_settings_saved' ) );
	exit;
}
add_action( 'admin_init', 'rgbmind_eph_handle_quick_settings_save' );
