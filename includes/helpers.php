<?php
/**
 * Helpers
 *
 * @package Simple_Page_Builder
 */

namespace SPB;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generate a secure random string of given length.
 *
 * @param int $length Length.
 * @return string
 */
function random_string( $length = 48 ) {
	$length = max( 16, min( 128, absint( $length ) ) );
	// Use WordPress function for secure random passwords with no special chars.
	return wp_generate_password( $length, false, false );
}

/**
 * Get ISO8601 UTC timestamp.
 *
 * @return string
 */
function iso8601_now() {
	$dt = new \DateTime( 'now', new \DateTimeZone( 'UTC' ) );
	return $dt->format( 'c' );
}

/**
 * Base64 URL-safe encode without padding.
 *
 * @param string $data Data.
 * @return string
 */
function base64url_encode( $data ) {
	return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
}

/**
 * Base64 URL-safe decode.
 *
 * @param string $data Data.
 * @return string|false
 */
function base64url_decode( $data ) {
	$remainder = strlen( $data ) % 4;
	if ( $remainder ) {
		$data .= str_repeat( '=', 4 - $remainder );
	}
	return base64_decode( strtr( $data, '-_', '+/' ) );
}

/**
 * Get client IP address.
 *
 * @return string
 */
function get_client_ip() {
	foreach ( array( 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' ) as $key ) {
		if ( ! empty( $_SERVER[ $key ] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
			// In case of multiple IPs (X_FORWARDED_FOR).
			if ( strpos( $ip, ',' ) !== false ) {
				$parts = explode( ',', $ip );
				return trim( $parts[0] );
			}
			return $ip;
		}
	}
	return '';
}

/**
 * Sanitize and normalize a slug.
 *
 * @param string $slug Slug.
 * @return string
 */
function sanitize_slug( $slug ) {
	$slug = sanitize_title( $slug );
	return $slug;
}


