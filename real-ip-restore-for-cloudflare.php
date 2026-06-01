<?php
/**
 * Plugin Name:       Real IP Restore for Cloudflare
 * Plugin URI:        https://apps.culture-dev.eu/plugins/wp/real-ip-restore-for-cloudflare
 * Description:       Restores the real visitor IP behind Cloudflare's proxy on any shared hosting that lacks native mod_remoteip support.
 * Version:           1.3.1
 * Requires at least: 5.0
 * Requires PHP:      7.4
 * Author:            Culture Dev
 * Author URI:        https://culture-dev.eu
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       real-ip-restore-for-cloudflare
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CFRIP_VERSION', '1.3.1' );
define( 'CFRIP_CACHE_KEY', 'cfrip_ip_ranges' );
define( 'CFRIP_CACHE_TTL', 86400 );

/**
 * Return the list of Cloudflare IP ranges (cached as a transient).
 *
 * @return string[]
 */
function cfrip_get_ranges() {
	$cached = get_transient( CFRIP_CACHE_KEY );
	if ( false !== $cached ) {
		return $cached;
	}

	$ranges = array();
	foreach ( array( 'https://www.cloudflare.com/ips-v4', 'https://www.cloudflare.com/ips-v6' ) as $url ) {
		$r = wp_remote_get( $url, array( 'timeout' => 5 ) );
		if ( ! is_wp_error( $r ) && 200 === wp_remote_retrieve_response_code( $r ) ) {
			$lines  = array_filter( array_map( 'trim', explode( "\n", wp_remote_retrieve_body( $r ) ) ) );
			$ranges = array_merge( $ranges, array_values( $lines ) );
		}
	}

	if ( empty( $ranges ) ) {
		$ranges = cfrip_hardcoded_ranges();
	}

	set_transient( CFRIP_CACHE_KEY, $ranges, CFRIP_CACHE_TTL );
	return $ranges;
}

/**
 * Fallback Cloudflare ranges, used when the official endpoints are unreachable.
 *
 * @return string[]
 */
function cfrip_hardcoded_ranges() {
	return array(
		'173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
		'141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
		'197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
		'104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
		'2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32',
		'2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
	);
}

/**
 * Check whether an IP address belongs to a CIDR range.
 *
 * @param string $ip    IP address.
 * @param string $range CIDR or plain IP.
 * @return bool
 */
function cfrip_ip_in_range( $ip, $range ) {
	if ( false === strpos( $range, '/' ) ) {
		return $ip === $range;
	}

	list( $subnet, $bits ) = explode( '/', $range, 2 );
	$bits                  = (int) $bits;

	if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) &&
		filter_var( $subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
		$ip_bin  = inet_pton( $ip );
		$sub_bin = inet_pton( $subnet );
		$mask    = str_repeat( "\xff", intdiv( $bits, 8 ) );
		$rem     = $bits % 8;
		if ( $rem ) {
			$mask .= chr( 0xff ^ ( 0xff >> $rem ) );
		}
		$mask .= str_repeat( "\x00", 16 - strlen( $mask ) );
		return ( $ip_bin & $mask ) === ( $sub_bin & $mask );
	}

	if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) &&
		filter_var( $subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
		$mask = ~( ( 1 << ( 32 - $bits ) ) - 1 );
		return ( ip2long( $ip ) & $mask ) === ( ip2long( $subnet ) & $mask );
	}

	return false;
}

/**
 * Replace REMOTE_ADDR with the real visitor IP when the request comes through Cloudflare.
 */
function cfrip_restore_ip() {
	$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	if ( '' === $remote ) {
		return;
	}

	$is_cf = false;
	foreach ( cfrip_get_ranges() as $range ) {
		if ( cfrip_ip_in_range( $remote, $range ) ) {
			$is_cf = true;
			break;
		}
	}
	if ( ! $is_cf ) {
		return;
	}

	foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP' ) as $header ) {
		if ( empty( $_SERVER[ $header ] ) ) {
			continue;
		}
		$raw = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
		$val = trim( explode( ',', $raw )[0] );
		if ( filter_var( $val, FILTER_VALIDATE_IP ) ) {
			$_SERVER['REMOTE_ADDR']    = $val;
			$_SERVER['HTTP_X_REAL_IP'] = $val;
			return;
		}
	}
}
add_action( 'init', 'cfrip_restore_ip', 1 );

/**
 * Force-refresh the cached ranges.
 */
function cfrip_refresh_cache() {
	delete_transient( CFRIP_CACHE_KEY );
	cfrip_get_ranges();
}
add_action( 'cfrip_refresh_cache', 'cfrip_refresh_cache' );

/**
 * Schedule the daily refresh on activation.
 */
function cfrip_schedule() {
	if ( ! wp_next_scheduled( 'cfrip_refresh_cache' ) ) {
		wp_schedule_event( time(), 'daily', 'cfrip_refresh_cache' );
	}
}
register_activation_hook( __FILE__, 'cfrip_schedule' );

/**
 * Clean up on deactivation.
 */
function cfrip_unschedule() {
	$ts = wp_next_scheduled( 'cfrip_refresh_cache' );
	if ( $ts ) {
		wp_unschedule_event( $ts, 'cfrip_refresh_cache' );
	}
	delete_transient( CFRIP_CACHE_KEY );
}
register_deactivation_hook( __FILE__, 'cfrip_unschedule' );

/**
 * Settings page under the Settings menu.
 */
function cfrip_admin_menu() {
	add_options_page(
		__( 'Cloudflare Real IP', 'real-ip-restore-for-cloudflare' ),
		__( 'CF Real IP', 'real-ip-restore-for-cloudflare' ),
		'manage_options',
		'real-ip-restore-for-cloudflare',
		'cfrip_settings_page'
	);
}
add_action( 'admin_menu', 'cfrip_admin_menu' );

/**
 * Render the settings page.
 */
function cfrip_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( isset( $_POST['cfrip_refresh'] ) && check_admin_referer( 'cfrip_refresh_nonce' ) ) {
		cfrip_refresh_cache();
		echo '<div class="notice notice-success"><p>' . esc_html__( 'IP ranges refreshed.', 'real-ip-restore-for-cloudflare' ) . '</p></div>';
	}

	$ranges  = cfrip_get_ranges();
	$current = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : __( 'unknown', 'real-ip-restore-for-cloudflare' );

	echo '<div class="wrap"><h1>' . esc_html__( 'Cloudflare Real IP', 'real-ip-restore-for-cloudflare' ) . '</h1>';
	echo '<p>' . esc_html__( 'Current REMOTE_ADDR:', 'real-ip-restore-for-cloudflare' ) . ' <strong>' . esc_html( $current ) . '</strong></p>';
	echo '<form method="post">';
	wp_nonce_field( 'cfrip_refresh_nonce' );
	echo '<input type="hidden" name="cfrip_refresh" value="1">';
	submit_button( __( 'Refresh IP Ranges Cache', 'real-ip-restore-for-cloudflare' ) );
	echo '</form>';
	/* translators: %d: number of cached Cloudflare IP ranges. */
	echo '<h2>' . esc_html( sprintf( __( 'Cached Cloudflare IP Ranges (%d)', 'real-ip-restore-for-cloudflare' ), count( $ranges ) ) ) . '</h2><ul>';
	foreach ( $ranges as $r ) {
		echo '<li><code>' . esc_html( $r ) . '</code></li>';
	}
	echo '</ul></div>';
}
