<?php
/**
 * Uninstallation cleanup for RGBmind Ephemere.
 *
 * Runs automatically when an administrator deletes the plugin via WordPress admin.
 * Cleans up all user metadata and configuration options safely using official WordPress core APIs.
 *
 * @package RGBmind_Ephemere
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Performs complete data cleanup upon plugin deletion.
 *
 * @return void
 */
function rgbmind_eph_uninstall() {
	$meta_keys = array(
		'_rgbmind_is_ephemeral',
		'_rgbmind_expire_at',
		'_rgbmind_max_logins',
		'_rgbmind_login_count',
		'_rgbmind_is_locked',
		'_rgbmind_last_login_time',
		// PRO add-on metadata
		'_rgbmind_access_log',
		'_rgbmind_magic_token',
		'_rgbmind_magic_token_exp',
		'_rgbmind_magic_token_expires',
	);

	foreach ( $meta_keys as $meta_key ) {
		delete_metadata( 'user', 0, $meta_key, '', true );
	}

	$options = array(
		'rgbmind_eph_show_welcome_guide',
		'rgbmind_eph_welcome_dismissed',
		'rgbmind_eph_license_key',
		'rgbmind_eph_is_pro',
		'rgbmind_eph_email_alerts_enabled',
		'rgbmind_eph_alert_email',
		'rgbmind_eph_magic_link_ttl',
		'rgbmind_eph_webhook_url',
		'rgbmind_eph_language',
	);

	foreach ( $options as $option_name ) {
		delete_option( $option_name );
	}
}

rgbmind_eph_uninstall();
