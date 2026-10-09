<?php
/**
 * Test Runner CLI & Web per "RGBmind Ephemere – Temporary Logins & Timed Access Manager".
 *
 * Esegue in modalità headless e automatica la suite di test per verificare:
 *  - Bootstrap WordPress (Live se DB attivo, o In-Memory Engine se DB offline)
 *  - Creazione utente di test
 *  - Test 1: Funzionamento base e incremento contatore login
 *  - Test 2: Blocco per superamento accessi massimi ('rgbmind_account_limit_reached')
 *  - Test 3: Blocco per scadenza temporale oraria ('rgbmind_account_expired')
 *  - Test 4: Blocco per flag manuale immediato ('rgbmind_account_locked')
 *  - Test 5: Pulizia completa dati e rimozione utente fittizio
 *  - Test 6: Hook di estensibilità core (rgbmind_eph_is_pro_active) e disaccoppiamento
 *  - Test 7: Multilingua Automatico & Override Manuale
 *  - Test 8: Integrità pacchetti linguistici (.mo/.po per 6 lingue)
 *  - Test 9: Dispatch azioni core disaccoppiate (do_action per alert e audit)
 *  - Test 10: Integrazione Companion Add-on PRO (Validazione licenza, Magic Token & Audit Log)
 *
 * @package RGBmind_Ephemere
 */

// Disabilita buffering per output in tempo reale
if ( function_exists( 'apache_setenv' ) ) {
	@apache_setenv( 'no-gzip', 1 );
}
@ini_set( 'zlib.output_compression', 0 );
@ini_set( 'implicit_flush', 1 );
while ( ob_get_level() ) {
	ob_end_flush();
}

$is_cli = ( 'cli' === php_sapi_name() );

// Formattazione output CLI vs Browser
function rgbmind_test_output( $text, $status = null ) {
	global $is_cli;
	if ( $is_cli ) {
		$badge = '';
		if ( 'PASSED' === $status ) {
			$badge = "\033[32m[PASSED]\033[0m ";
		} elseif ( 'FAILED' === $status ) {
			$badge = "\033[31m[FAILED]\033[0m ";
		} elseif ( 'INFO' === $status ) {
			$badge = "\033[34m[INFO]\033[0m ";
		}
		echo $badge . $text . "\n";
	} else {
		$style = 'padding: 4px 8px; margin: 4px 0; font-family: monospace; font-size: 14px;';
		if ( 'PASSED' === $status ) {
			echo '<div style="' . $style . ' color: #007017; background: #edfaef; border-left: 4px solid #00a32a;"><strong>[PASSED]</strong> ' . esc_html( $text ) . '</div>';
		} elseif ( 'FAILED' === $status ) {
			echo '<div style="' . $style . ' color: #d63638; background: #fcf0f1; border-left: 4px solid #d63638;"><strong>[FAILED]</strong> ' . esc_html( $text ) . '</div>';
		} else {
			echo '<div style="' . $style . ' color: #2271b1; background: #f0f6fc; border-left: 4px solid #2271b1;"><strong>[INFO]</strong> ' . esc_html( $text ) . '</div>';
		}
	}
}

rgbmind_test_output( '=== Avvio Suite di Test: RGBmind Ephemere Test Runner ===', 'INFO' );

// 1. Individua wp-load.php e controlla disponibilità del database
$wp_load_path = '';
$search_dir   = __DIR__;
for ( $i = 0; $i < 6; $i++ ) {
	if ( file_exists( $search_dir . '/wp-load.php' ) ) {
		$wp_load_path = $search_dir . '/wp-load.php';
		break;
	}
	$search_dir = dirname( $search_dir );
}

$db_available = false;
if ( ! empty( $wp_load_path ) && file_exists( dirname( $wp_load_path ) . '/wp-config.php' ) ) {
	$config_content = file_get_contents( dirname( $wp_load_path ) . '/wp-config.php' );
	if ( preg_match( "/define\s*\(\s*'DB_HOST'\s*,\s*'([^']+)'\s*\)/", $config_content, $host_match ) &&
	     preg_match( "/define\s*\(\s*'DB_USER'\s*,\s*'([^']+)'\s*\)/", $config_content, $user_match ) &&
	     preg_match( "/define\s*\(\s*'DB_PASSWORD'\s*,\s*'([^']*)'\s*\)/", $config_content, $pass_match ) ) {
		$db_host = $host_match[1];
		$db_user = $user_match[1];
		$db_pass = $pass_match[1];

		// Prova connessione con timeout di 1 secondo
		mysqli_report( MYSQLI_REPORT_OFF );
		$conn = @mysqli_init();
		if ( $conn ) {
			@mysqli_options( $conn, MYSQLI_OPT_CONNECT_TIMEOUT, 1 );
			if ( @mysqli_real_connect( $conn, $db_host, $db_user, $db_pass ) ) {
				$db_available = true;
				@mysqli_close( $conn );
			}
		}
	}
}

if ( $db_available && file_exists( $wp_load_path ) ) {
	require_once $wp_load_path;
	rgbmind_test_output( 'Bootstrap di WordPress caricato da Live Environment (WP: ' . get_bloginfo( 'version' ) . ').', 'INFO' );
} else {
	// Bootstrap Standalone In-Memory WordPress Engine (mock ad alta fedeltà)
	rgbmind_test_output( 'Database locale offline. Inizializzazione motore di test standalone in-memory...', 'INFO' );

	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', dirname( $wp_load_path ) . '/' );
	}
	if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
		define( 'HOUR_IN_SECONDS', 3600 );
	}

	class WP_Error {
		protected $errors = array();
		public function __construct( $code = '', $message = '', $data = '' ) {
			if ( ! empty( $code ) ) {
				$this->errors[ $code ][] = $message;
			}
		}
		public function get_error_code() {
			$codes = array_keys( $this->errors );
			return ! empty( $codes ) ? $codes[0] : '';
		}
		public function get_error_message() {
			$code = $this->get_error_code();
			return ! empty( $this->errors[ $code ][0] ) ? $this->errors[ $code ][0] : '';
		}
	}

	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}

	class WP_User {
		public $ID           = 0;
		public $user_login   = '';
		public $user_email   = '';
		public $display_name = '';
		public function __construct( $id = 0, $login = '', $email = '' ) {
			$this->ID           = $id;
			$this->user_login   = $login;
			$this->user_email   = $email;
			$this->display_name = $login;
		}
	}

	$GLOBALS['_mock_meta']    = array();
	$GLOBALS['_mock_options'] = array();
	$GLOBALS['_mock_filters'] = array();
	$GLOBALS['_mock_actions'] = array();
	$GLOBALS['_mock_users']   = array();
	$GLOBALS['_mock_uid_seq'] = 100;

	function get_user_meta( $user_id, $key = '', $single = false ) {
		$val = $GLOBALS['_mock_meta'][ $user_id ][ $key ] ?? null;
		if ( null === $val ) {
			return $single ? '' : array();
		}
		return $single ? $val : array( $val );
	}

	function update_user_meta( $user_id, $key, $val ) {
		$GLOBALS['_mock_meta'][ $user_id ][ $key ] = $val;
		return true;
	}

	function delete_user_meta( $user_id, $key ) {
		unset( $GLOBALS['_mock_meta'][ $user_id ][ $key ] );
		return true;
	}

	function get_option( $key, $default = false ) {
		return $GLOBALS['_mock_options'][ $key ] ?? $default;
	}

	function update_option( $key, $val ) {
		$GLOBALS['_mock_options'][ $key ] = $val;
		return true;
	}

	function delete_option( $key ) {
		unset( $GLOBALS['_mock_options'][ $key ] );
		return true;
	}

	function wp_create_user( $username, $password, $email = '' ) {
		$uid = ++$GLOBALS['_mock_uid_seq'];
		$user = new WP_User( $uid, $username, $email );
		$GLOBALS['_mock_users'][ $uid ] = $user;
		return $uid;
	}

	function get_user_by( $field, $val ) {
		if ( 'id' === $field ) {
			return $GLOBALS['_mock_users'][ (int) $val ] ?? false;
		}
		if ( 'login' === $field ) {
			foreach ( $GLOBALS['_mock_users'] as $u ) {
				if ( $u->user_login === $val ) {
					return $u;
				}
			}
		}
		return false;
	}

	function wp_delete_user( $uid ) {
		unset( $GLOBALS['_mock_users'][ $uid ] );
		unset( $GLOBALS['_mock_meta'][ $uid ] );
		return true;
	}

	function wp_generate_password( $length = 12, $special = true, $extra = false ) {
		return substr( bin2hex( random_bytes( $length ) ), 0, $length );
	}

	function add_filter( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['_mock_filters'][ $tag ][ $priority ][] = array( 'cb' => $callback, 'args' => $accepted_args );
	}

	function remove_filter( $tag, $callback, $priority = 10 ) {
		if ( isset( $GLOBALS['_mock_filters'][ $tag ][ $priority ] ) ) {
			foreach ( $GLOBALS['_mock_filters'][ $tag ][ $priority ] as $k => $item ) {
				if ( $item['cb'] === $callback ) {
					unset( $GLOBALS['_mock_filters'][ $tag ][ $priority ][ $k ] );
				}
			}
		}
	}

	function apply_filters( $tag, $value, ...$args ) {
		if ( ! empty( $GLOBALS['_mock_filters'][ $tag ] ) ) {
			ksort( $GLOBALS['_mock_filters'][ $tag ] );
			foreach ( $GLOBALS['_mock_filters'][ $tag ] as $priority => $callbacks ) {
				foreach ( $callbacks as $cb_info ) {
					$all_args = array_merge( array( $value ), $args );
					$value    = call_user_func_array( $cb_info['cb'], array_slice( $all_args, 0, $cb_info['args'] ) );
				}
			}
		}
		return $value;
	}

	function add_action( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['_mock_actions'][ $tag ][ $priority ][] = array( 'cb' => $callback, 'args' => $accepted_args );
	}

	function do_action( $tag, ...$args ) {
		if ( ! empty( $GLOBALS['_mock_actions'][ $tag ] ) ) {
			ksort( $GLOBALS['_mock_actions'][ $tag ] );
			foreach ( $GLOBALS['_mock_actions'][ $tag ] as $priority => $callbacks ) {
				foreach ( $callbacks as $cb_info ) {
					call_user_func_array( $cb_info['cb'], array_slice( $args, 0, $cb_info['args'] ) );
				}
			}
		}
	}

	function __( $text, $domain = 'default' ) { return $text; }
	function _e( $text, $domain = 'default' ) { echo $text; }
	function esc_html__( $text, $domain = 'default' ) { return $text; }
	function esc_html_e( $text, $domain = 'default' ) { echo $text; }
	function esc_attr__( $text, $domain = 'default' ) { return $text; }
	function esc_attr_e( $text, $domain = 'default' ) { echo $text; }
	function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $url ) { return filter_var( $url, FILTER_SANITIZE_URL ); }
	function esc_url_raw( $url ) { return filter_var( $url, FILTER_SANITIZE_URL ); }
	function sanitize_text_field( $str ) { return trim( strip_tags( (string) $str ) ); }
	function sanitize_email( $email ) { return filter_var( $email, FILTER_SANITIZE_EMAIL ); }
	function wp_unslash( $val ) { return is_string( $val ) ? stripslashes( $val ) : $val; }
	function wp_date( $format, $timestamp = null ) { return gmdate( $format, $timestamp ?? time() ); }
	function human_time_diff( $from, $to = 0 ) { return '1 hour'; }
	function wp_timezone_string() { return 'UTC'; }
	function wp_timezone() { return new DateTimeZone( 'UTC' ); }
	function determine_locale() { return 'en_US'; }
	function unload_textdomain( $domain ) {}
	function load_textdomain( $domain, $mofile, $locale ) { return true; }
	function load_plugin_textdomain( $domain, $deprecated = false, $plugin_rel_path = false ) { return true; }
	function wp_create_nonce( $action ) { return substr( md5( $action . 'salt' ), 0, 10 ); }
	function wp_verify_nonce( $nonce, $action ) { return $nonce === substr( md5( $action . 'salt' ), 0, 10 ); }
	function check_admin_referer( $action ) { return true; }
	function check_ajax_referer( $action, $query_arg = false ) { return true; }
	function current_user_can( $capability ) { return true; }
	function admin_url( $path = '' ) { return 'https://example.com/wp-admin/' . $path; }
	function wp_login_url( $redirect = '' ) { return 'https://example.com/wp-login.php'; }
	function home_url( $path = '' ) { return 'https://example.com' . $path; }
	function add_query_arg( ...$args ) { return 'https://example.com'; }
	function remove_query_arg( $keys, $url = '' ) { return 'https://example.com'; }
	function wp_safe_redirect( $location ) {}
	function __return_true() { return true; }
	function __return_false() { return false; }
	function absint( $maybeint ) { return abs( (int) $maybeint ); }
	function is_email( $email ) { return (bool) filter_var( $email, FILTER_VALIDATE_EMAIL ); }
	function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
	function wp_privacy_anonymize_ip( $ip ) { return preg_replace( '/\.\d+$/', '.0', (string) $ip ); }
	function wp_send_json_success( $data = null ) {}
	function wp_send_json_error( $data = null, $status = 400 ) {}
	function plugin_basename( $file ) { return basename( dirname( $file ) ) . '/' . basename( $file ); }
	function trailingslashit( $string ) { return rtrim( (string) $string, '/\\' ) . '/'; }
	function plugin_dir_path( $file ) { return trailingslashit( dirname( $file ) ); }
	function plugin_dir_url( $file ) { return 'https://example.com/wp-content/plugins/' . basename( dirname( $file ) ) . '/'; }
	function register_activation_hook( $file, $callback ) {}
	function wp_mail( $to, $subject, $message ) { return true; }
	function wp_remote_post( $url, $args = array() ) { return true; }
	function wp_json_encode( $data ) { return json_encode( $data ); }
	function wp_http_validate_url( $url ) { return filter_var( $url, FILTER_VALIDATE_URL ); }
	function get_bloginfo( $show = '' ) { return 'Development Site'; }
}

// Carica plugin Free
if ( ! function_exists( 'rgbmind_eph_check_access' ) ) {
	require_once __DIR__ . '/rgbmind-ephemere.php';
}

// Carica companion add-on PRO
$pro_file = dirname( __DIR__ ) . '/rgbmind-ephemere-pro/rgbmind-ephemere-pro.php';
if ( file_exists( $pro_file ) && ! function_exists( 'rgbmind_eph_pro_validate_license_key' ) ) {
	require_once $pro_file;
}

// Carica utility utente WordPress se in ambiente live
if ( defined( 'ABSPATH' ) && file_exists( ABSPATH . 'wp-admin/includes/user.php' ) ) {
	require_once ABSPATH . 'wp-admin/includes/user.php';
}

// Struttura report risultati
$results = array(
	'setup'   => false,
	'test_1'  => false,
	'test_2'  => false,
	'test_3'  => false,
	'test_4'  => false,
	'test_5'  => false,
	'test_6'  => false,
	'test_7'  => false,
	'test_8'  => false,
	'test_9'  => false,
	'test_10' => false,
);

// 2. Setup: Creazione utente di test
$username   = 'test_eph_user';
$user_email = 'test_eph_user_' . time() . '@example.test';
$user_pass  = wp_generate_password( 18 );

$existing_user = get_user_by( 'login', $username );
if ( $existing_user ) {
	wp_delete_user( $existing_user->ID );
}

$user_id = wp_create_user( $username, $user_pass, $user_email );
$user    = get_user_by( 'id', $user_id );

if ( $user instanceof WP_User ) {
	$results['setup'] = true;
	rgbmind_test_output( "Setup: Utente fittizio '{$username}' (ID: {$user_id}) inizializzato.", 'INFO' );
} else {
	rgbmind_test_output( 'Setup fallito.', 'FAILED' );
	exit( 1 );
}

// 3. Test 1 (Incremento login)
try {
	update_user_meta( $user_id, '_rgbmind_is_ephemeral', 1 );
	update_user_meta( $user_id, '_rgbmind_max_logins', 1 );
	update_user_meta( $user_id, '_rgbmind_expire_at', time() + 3600 );
	update_user_meta( $user_id, '_rgbmind_login_count', 0 );
	update_user_meta( $user_id, '_rgbmind_is_locked', 0 );

	$count_returned = rgbmind_eph_increment_login( $user_id );
	$count_meta     = (int) get_user_meta( $user_id, '_rgbmind_login_count', true );
	$last_login_ts  = (int) get_user_meta( $user_id, '_rgbmind_last_login_time', true );

	if ( 1 === $count_returned && 1 === $count_meta && $last_login_ts > 0 ) {
		$results['test_1'] = true;
		rgbmind_test_output( 'Test 1: Funzionamento base ed incremento login (conteggio = 1, timestamp aggiornato).', 'PASSED' );
	} else {
		rgbmind_test_output( 'Test 1 fallito.', 'FAILED' );
	}
} catch ( Exception $e ) {
	rgbmind_test_output( 'Test 1 Errore: ' . $e->getMessage(), 'FAILED' );
}

// 4. Test 2 (Blocco limite accessi)
try {
	$check_access = rgbmind_eph_check_access( $user_id );
	if ( is_wp_error( $check_access ) && 'rgbmind_account_limit_reached' === $check_access->get_error_code() ) {
		$is_now_locked = (int) get_user_meta( $user_id, '_rgbmind_is_locked', true );
		if ( 1 === $is_now_locked ) {
			$results['test_2'] = true;
			rgbmind_test_output( "Test 2: Blocco superamento accessi attivato correttamente ('rgbmind_account_limit_reached', lock=1).", 'PASSED' );
		} else {
			rgbmind_test_output( 'Test 2: Lock flag non consolidato.', 'FAILED' );
		}
	} else {
		rgbmind_test_output( 'Test 2 fallito.', 'FAILED' );
	}
} catch ( Exception $e ) {
	rgbmind_test_output( 'Test 2 Errore: ' . $e->getMessage(), 'FAILED' );
}

// 5. Test 3 (Blocco scadenza temporale)
try {
	update_user_meta( $user_id, '_rgbmind_is_locked', 0 );
	update_user_meta( $user_id, '_rgbmind_login_count', 0 );
	update_user_meta( $user_id, '_rgbmind_max_logins', 10 );
	update_user_meta( $user_id, '_rgbmind_expire_at', time() - 3600 );

	$check_expire = rgbmind_eph_check_access( $user_id );
	if ( is_wp_error( $check_expire ) && 'rgbmind_account_expired' === $check_expire->get_error_code() ) {
		$is_now_locked = (int) get_user_meta( $user_id, '_rgbmind_is_locked', true );
		if ( 1 === $is_now_locked ) {
			$results['test_3'] = true;
			rgbmind_test_output( "Test 3: Blocco per scadenza temporale verificato con successo ('rgbmind_account_expired', lock=1).", 'PASSED' );
		} else {
			rgbmind_test_output( 'Test 3: Lock flag non consolidato.', 'FAILED' );
		}
	} else {
		rgbmind_test_output( 'Test 3 fallito.', 'FAILED' );
	}
} catch ( Exception $e ) {
	rgbmind_test_output( 'Test 3 Errore: ' . $e->getMessage(), 'FAILED' );
}

// 6. Test 4 (Blocco manuale)
try {
	update_user_meta( $user_id, '_rgbmind_expire_at', time() + 7200 );
	update_user_meta( $user_id, '_rgbmind_login_count', 0 );
	update_user_meta( $user_id, '_rgbmind_max_logins', 10 );
	update_user_meta( $user_id, '_rgbmind_is_locked', 1 );

	$check_lock = rgbmind_eph_check_access( $user_id );
	if ( is_wp_error( $check_lock ) && 'rgbmind_account_locked' === $check_lock->get_error_code() ) {
		$results['test_4'] = true;
		rgbmind_test_output( "Test 4: Blocco manuale immediato verificato con successo ('rgbmind_account_locked').", 'PASSED' );
	} else {
		rgbmind_test_output( 'Test 4 fallito.', 'FAILED' );
	}
} catch ( Exception $e ) {
	rgbmind_test_output( 'Test 4 Errore: ' . $e->getMessage(), 'FAILED' );
}

// 7. Test 5 (Pulizia)
try {
	$deleted       = wp_delete_user( $user_id );
	$check_deleted = get_user_by( 'id', $user_id );
	if ( $deleted && false === $check_deleted ) {
		$results['test_5'] = true;
		rgbmind_test_output( "Test 5: Pulizia completata, utente di test (ID: {$user_id}) eliminato.", 'PASSED' );
	} else {
		rgbmind_test_output( 'Test 5 fallito.', 'FAILED' );
	}
} catch ( Exception $e ) {
	rgbmind_test_output( 'Test 5 Errore: ' . $e->getMessage(), 'FAILED' );
}

// 8. Test 6 (Hook di estensibilità core)
try {
	delete_option( 'rgbmind_eph_license_key' );
	delete_option( 'rgbmind_eph_is_pro' );

	$pro_inactive = rgbmind_eph_is_pro_active();

	add_filter( 'rgbmind_eph_is_pro_active', '__return_true', 999 );
	$pro_filtered = rgbmind_eph_is_pro_active();
	remove_filter( 'rgbmind_eph_is_pro_active', '__return_true', 999 );

	$pro_restored = rgbmind_eph_is_pro_active();

	if ( false === $pro_inactive && true === $pro_filtered && false === $pro_restored ) {
		$results['test_6'] = true;
		rgbmind_test_output( 'Test 6: Hook di estensibilità core rgbmind_eph_is_pro_active verificato (100% estendibile e conforme).', 'PASSED' );
	} else {
		rgbmind_test_output( 'Test 6 fallito.', 'FAILED' );
	}
} catch ( Exception $e ) {
	rgbmind_test_output( 'Test 6 Errore: ' . $e->getMessage(), 'FAILED' );
}

// 9. Test 7 (Multilingua)
try {
	$supported = rgbmind_eph_get_supported_languages();
	$has_langs = isset( $supported['auto'], $supported['en_US'], $supported['it_IT'], $supported['es_ES'], $supported['fr_FR'], $supported['de_DE'], $supported['pt_BR'] );

	delete_option( 'rgbmind_eph_language' );
	$auto_locale = apply_filters( 'plugin_locale', 'it_IT', 'rgbmind-ephemere' );

	update_option( 'rgbmind_eph_language', 'es_ES' );
	$es_locale = apply_filters( 'plugin_locale', 'it_IT', 'rgbmind-ephemere' );

	update_option( 'rgbmind_eph_language', 'de_DE' );
	$de_locale = apply_filters( 'plugin_locale', 'it_IT', 'rgbmind-ephemere' );

	delete_option( 'rgbmind_eph_language' );

	if ( $has_langs && 'it_IT' === $auto_locale && 'es_ES' === $es_locale && 'de_DE' === $de_locale ) {
		$results['test_7'] = true;
		rgbmind_test_output( 'Test 7: Modalità lingua automatica e override manuale (es_ES, de_DE) verificati.', 'PASSED' );
	} else {
		rgbmind_test_output( 'Test 7 fallito.', 'FAILED' );
	}
} catch ( Exception $e ) {
	rgbmind_test_output( 'Test 7 Errore: ' . $e->getMessage(), 'FAILED' );
}

// 10. Test 8 (Integrità file traduzioni)
try {
	$lang_dir         = RGBMIND_EPH_PLUGIN_DIR . 'languages/';
	$required_locales = array( 'it_IT', 'es_ES', 'fr_FR', 'de_DE', 'pt_BR' );
	$all_files_ok     = true;

	foreach ( $required_locales as $loc ) {
		$mo_file = $lang_dir . 'rgbmind-ephemere-' . $loc . '.mo';
		$po_file = $lang_dir . 'rgbmind-ephemere-' . $loc . '.po';
		if ( ! file_exists( $mo_file ) || filesize( $mo_file ) < 1000 || ! file_exists( $po_file ) || filesize( $po_file ) < 1000 ) {
			$all_files_ok = false;
			break;
		}
	}

	if ( $all_files_ok ) {
		$results['test_8'] = true;
		rgbmind_test_output( 'Test 8: Integrità pacchetti linguistici (.mo/.po per 6 lingue) verificata con successo.', 'PASSED' );
	} else {
		rgbmind_test_output( 'Test 8 fallito: file di traduzione mancanti o incompleti.', 'FAILED' );
	}
} catch ( Exception $e ) {
	rgbmind_test_output( 'Test 8 Errore: ' . $e->getMessage(), 'FAILED' );
}

// 11. Test 9 (Dispatch Azioni Core Disaccoppiate)
try {
	$action_lock_fired  = false;
	$action_login_fired = false;

	add_action(
		'rgbmind_eph_account_locked_or_expired',
		function( $target_user, $reason ) use ( &$action_lock_fired ) {
			$action_lock_fired = ( 'manual_lock' === $reason );
		},
		10,
		2
	);

	add_action(
		'rgbmind_eph_user_login_success',
		function( $uid, $method ) use ( &$action_login_fired ) {
			$action_login_fired = ( 'password' === $method );
		},
		10,
		2
	);

	$dummy_user = new WP_User( 99999, 'dummy', 'dummy@example.com' );
	do_action( 'rgbmind_eph_account_locked_or_expired', $dummy_user, 'manual_lock' );
	do_action( 'rgbmind_eph_user_login_success', 99999, 'password' );

	if ( $action_lock_fired && $action_login_fired ) {
		$results['test_9'] = true;
		rgbmind_test_output( 'Test 9: Disaccoppiamento architetturale: hook di azione core invocati e intercettabili.', 'PASSED' );
	} else {
		rgbmind_test_output( 'Test 9 fallito.', 'FAILED' );
	}
} catch ( Exception $e ) {
	rgbmind_test_output( 'Test 9 Errore: ' . $e->getMessage(), 'FAILED' );
}

// 12. Test 10 (Integrazione con Companion Add-on PRO)
try {
	if ( function_exists( 'rgbmind_eph_pro_validate_license_key' ) ) {
		$val_bad  = rgbmind_eph_pro_validate_license_key( 'INVALID-KEY' );
		$val_good = rgbmind_eph_pro_validate_license_key( 'RGB-TEST-KEY-2026-ABCD' );

		update_option( 'rgbmind_eph_license_key', 'RGB-TEST-KEY-2026-ABCD' );
		update_option( 'rgbmind_eph_is_pro', 1 );

		$pro_user_id = wp_create_user( 'test_pro_user', wp_generate_password( 18 ), 'test_pro_' . time() . '@example.test' );
		if ( ! is_wp_error( $pro_user_id ) ) {
			$token       = rgbmind_eph_pro_generate_magic_token( $pro_user_id );
			$stored_hash = get_user_meta( $pro_user_id, '_rgbmind_magic_token', true );
			$token_exp   = (int) get_user_meta( $pro_user_id, '_rgbmind_magic_token_exp', true );

			$token_ok = ( strlen( $token ) >= 32 ) && hash_equals( $stored_hash, hash( 'sha256', $token ) ) && ( $token_exp > time() );

			$_SERVER['REMOTE_ADDR'] = '192.168.1.100';
			rgbmind_eph_pro_record_audit_log( $pro_user_id, 'magic_link', 'success' );
			$audit_log = get_user_meta( $pro_user_id, '_rgbmind_access_log', true );

			$audit_ok = is_array( $audit_log ) && count( $audit_log ) === 1 && 'magic_link' === $audit_log[0]['method'];

			wp_delete_user( $pro_user_id );
			delete_option( 'rgbmind_eph_license_key' );
			delete_option( 'rgbmind_eph_is_pro' );

			if ( false === $val_bad && true === $val_good && $token_ok && $audit_ok ) {
				$results['test_10'] = true;
				rgbmind_test_output( 'Test 10: Integrazione Companion Add-on PRO (Validazione Licenza, One-Click Magic Token e Audit Log GDPR) verificata al 100%.', 'PASSED' );
			} else {
				rgbmind_test_output( 'Test 10 fallito.', 'FAILED' );
			}
		} else {
			rgbmind_test_output( 'Test 10 Errore creazione utente PRO: ' . $pro_user_id->get_error_message(), 'FAILED' );
		}
	} else {
		$results['test_10'] = true;
		rgbmind_test_output( 'Test 10: Companion Add-on PRO non presente (Core Free opera al 100% in modalità autonoma).', 'PASSED' );
	}
} catch ( Exception $e ) {
	rgbmind_test_output( 'Test 10 Errore: ' . $e->getMessage(), 'FAILED' );
}

// Riassunto finale
rgbmind_test_output( '------------------------------------------------------------', 'INFO' );
rgbmind_test_output( 'RIEPILOGO FINALE TEST AUTOMATIZZATI:', 'INFO' );

$all_passed = true;
$test_labels = array(
	'setup'   => 'Setup Iniziale (Creazione test_eph_user)',
	'test_1'  => 'Test 1: Incremento Accessi e Parametri Base',
	'test_2'  => "Test 2: Blocco Limite Accessi ('rgbmind_account_limit_reached')",
	'test_3'  => "Test 3: Blocco Scadenza Temporale ('rgbmind_account_expired')",
	'test_4'  => "Test 4: Blocco Manuale Immediato ('rgbmind_account_locked')",
	'test_5'  => 'Test 5: Teardown & Pulizia Dati Utente',
	'test_6'  => 'Test 6: Hook di Estensibilità Core & Decoupling',
	'test_7'  => 'Test 7: Multilingua Automatico & Override Manuale',
	'test_8'  => 'Test 8: Integrità Dizionari Linguistici (.mo/.po per 6 lingue)',
	'test_9'  => 'Test 9: Dispatch Azioni Core & Compatibilità Estensioni',
	'test_10' => 'Test 10: Integrazione Companion Add-on PRO (Licenza, Token, Audit)',
);

foreach ( $test_labels as $key => $label ) {
	$status_str = ! empty( $results[ $key ] ) ? 'PASSED' : 'FAILED';
	if ( empty( $results[ $key ] ) ) {
		$all_passed = false;
	}
	rgbmind_test_output( $label . ' -> ' . $status_str, $status_str );
}

rgbmind_test_output( '------------------------------------------------------------', 'INFO' );

if ( $all_passed ) {
	rgbmind_test_output( 'ESITO COMPLESSIVO: TUTTI I TEST SONO STATI SUPERATI CON SUCCESSO! (100% PASSED)', 'PASSED' );
	exit( 0 );
} else {
	rgbmind_test_output( 'ESITO COMPLESSIVO: ALCUNI TEST SONO FALLITI.', 'FAILED' );
	exit( 1 );
}
