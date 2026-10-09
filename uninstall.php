<?php
/**
 * File di disinstallazione per RGBmind Ephemere – Temporary Logins & Timed Access Manager.
 *
 * Viene eseguito automaticamente da WordPress quando l'amministratore elimina il plugin.
 * Rimuove in blocco tutti i metadati utente (Free e PRO) e le opzioni salvate,
 * garantendo la totale pulizia del database senza lasciare record orfani.
 *
 * @package RGBmind_Ephemere
 */

// Se la disinstallazione non è invocata direttamente da WordPress, interrompi l'esecuzione.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Elenco di tutti i metadati utente creati dal plugin (sia Free che PRO).
 */
$rgbmind_eph_meta_keys = array(
	'_rgbmind_is_ephemeral',
	'_rgbmind_expire_at',
	'_rgbmind_max_logins',
	'_rgbmind_login_count',
	'_rgbmind_is_locked',
	'_rgbmind_last_login_time',
	// Metadati PRO
	'_rgbmind_access_log',
	'_rgbmind_magic_token',
	'_rgbmind_magic_token_exp',
	'_rgbmind_magic_token_expires',
);

/**
 * Eliminazione in blocco tramite la funzione nativa WordPress delete_metadata.
 * Il parametro $delete_all = true assicura la cancellazione del metadato per tutti gli utenti,
 * gestendo correttamente l'invalidazione della cache degli oggetti WordPress.
 */
foreach ( $rgbmind_eph_meta_keys as $rgbmind_eph_meta_key ) {
	delete_metadata( 'user', 0, $rgbmind_eph_meta_key, '', true );
}

/**
 * Pulizia a livello di database per garantire la rimozione assoluta di qualsiasi record orfano
 * o residuo nella tabella usermeta (funziona sia su installazioni singole che su reti Multisite).
 */
global $wpdb;

if ( isset( $wpdb->usermeta ) ) {
	$placeholders = implode( ', ', array_fill( 0, count( $rgbmind_eph_meta_keys ), '%s' ) );
	$sql_query    = $wpdb->prepare(
		"DELETE FROM {$wpdb->usermeta} WHERE meta_key IN ($placeholders)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rgbmind_eph_meta_keys
	);

	$wpdb->query( $sql_query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

/**
 * Eliminazione delle opzioni di configurazione, licenza e onboarding dalla tabella wp_options.
 */
$rgbmind_eph_options = array(
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

foreach ( $rgbmind_eph_options as $option_name ) {
	delete_option( $option_name );
}
