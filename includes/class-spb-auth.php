<?php
/**
 * Authentication for REST requests.
 *
 * @package Simple_Page_Builder
 */

namespace SPB;

use WP_Error;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Auth
 */
class Auth {

	const HEADER_API_KEY    = 'x-spb-api-key';
	const HEADER_API_SECRET = 'x-spb-api-secret';

	/**
	 * Authenticate an incoming REST request using custom API key + secret.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error Returns array with 'key' on success.
	 */
	public static function authenticate_request( WP_REST_Request $request ) {
		// Global enable/disable.
		if ( ! Settings::get( 'enable_api', true ) ) {
			return new WP_Error( 'spb_api_disabled', __( 'API is currently disabled by the site administrator.', 'simple-page-builder' ), array( 'status' => 503 ) );
		}

		// Optional auth mode could be implemented here (e.g., JWT).
		$mode = Settings::get( 'auth_mode', 'api_key' );
		if ( 'jwt' === $mode ) {
			$auth_header = (string) $request->get_header( 'authorization' );
			$jwt         = '';
			if ( stripos( $auth_header, 'bearer ' ) === 0 ) {
				$jwt = trim( substr( $auth_header, 7 ) );
			}
			if ( '' === $jwt ) {
				$jwt = (string) $request->get_header( 'x-spb-jwt' );
			}
			if ( '' === $jwt ) {
				return new WP_Error( 'spb_auth_missing', __( 'Missing JWT token.', 'simple-page-builder' ), array( 'status' => 401 ) );
			}
			$secret = (string) Settings::get( 'webhook_secret', '' );
			$payload = self::jwt_decode( $jwt, $secret );
			if ( is_wp_error( $payload ) ) {
				return $payload;
			}
			$ak_fp = isset( $payload['ak_fp'] ) ? (string) $payload['ak_fp'] : '';
			if ( '' === $ak_fp ) {
				return new WP_Error( 'spb_auth_invalid', __( 'Invalid token payload.', 'simple-page-builder' ), array( 'status' => 401 ) );
			}
			$row = \SPB\Database::get_api_key_by_fingerprint( $ak_fp );
			if ( ! $row ) {
				return new WP_Error( 'spb_auth_invalid', __( 'Invalid API credentials.', 'simple-page-builder' ), array( 'status' => 401 ) );
			}
			// Check status/expiry.
			if ( 'revoked' === $row['status'] ) {
				return new WP_Error( 'spb_auth_revoked', __( 'API key has been revoked.', 'simple-page-builder' ), array( 'status' => 403 ) );
			}
			if ( ! empty( $row['expires_at'] ) ) {
				$expires_ts = strtotime( $row['expires_at'] . ' UTC' );
				if ( $expires_ts && time() > $expires_ts ) {
					return new WP_Error( 'spb_auth_expired', __( 'API key has expired.', 'simple-page-builder' ), array( 'status' => 403 ) );
				}
			}
			return array( 'key' => $row );
		}

		$api_key    = trim( (string) $request->get_header( self::HEADER_API_KEY ) );
		$api_secret = trim( (string) $request->get_header( self::HEADER_API_SECRET ) );

		if ( '' === $api_key || '' === $api_secret ) {
			return new WP_Error( 'spb_auth_missing', __( 'Missing API key or secret in headers.', 'simple-page-builder' ), array( 'status' => 401 ) );
		}

		$api_fp = hash( 'sha256', $api_key );
		$row    = Database::get_api_key_by_fingerprint( $api_fp );
		if ( ! $row ) {
			return new WP_Error( 'spb_auth_invalid', __( 'Invalid API credentials.', 'simple-page-builder' ), array( 'status' => 401 ) );
		}

		// Check status.
		if ( 'revoked' === $row['status'] ) {
			return new WP_Error( 'spb_auth_revoked', __( 'API key has been revoked.', 'simple-page-builder' ), array( 'status' => 403 ) );
		}

		// Check expiration if any.
		if ( ! empty( $row['expires_at'] ) ) {
			$expires_ts = strtotime( $row['expires_at'] . ' UTC' );
			if ( $expires_ts && time() > $expires_ts ) {
				return new WP_Error( 'spb_auth_expired', __( 'API key has expired.', 'simple-page-builder' ), array( 'status' => 403 ) );
			}
		}

		// Verify hashes.
		if ( ! password_verify( $api_key, $row['api_key_hash'] ) ) {
			return new WP_Error( 'spb_auth_invalid', __( 'Invalid API credentials.', 'simple-page-builder' ), array( 'status' => 401 ) );
		}
		if ( ! password_verify( $api_secret, $row['secret_key_hash'] ) ) {
			return new WP_Error( 'spb_auth_invalid', __( 'Invalid API credentials.', 'simple-page-builder' ), array( 'status' => 401 ) );
		}

		return array(
			'key' => $row,
		);
	}

	/**
	 * Minimal JWT decode for HS256.
	 *
	 * @param string $jwt Token.
	 * @param string $secret Secret.
	 * @return array|\WP_Error
	 */
	protected static function jwt_decode( $jwt, $secret ) {
		$parts = explode( '.', $jwt );
		if ( count( $parts ) !== 3 ) {
			return new \WP_Error( 'spb_auth_invalid', __( 'Invalid token format.', 'simple-page-builder' ), array( 'status' => 401 ) );
		}
		list( $h, $p, $s ) = $parts;
		$header  = json_decode( \SPB\base64url_decode( $h ), true );
		$payload = json_decode( \SPB\base64url_decode( $p ), true );
		$sig     = \SPB\base64url_decode( $s );
		if ( ! is_array( $header ) || ! is_array( $payload ) || false === $sig ) {
			return new \WP_Error( 'spb_auth_invalid', __( 'Invalid token segments.', 'simple-page-builder' ), array( 'status' => 401 ) );
		}
		if ( ( $header['alg'] ?? '' ) !== 'HS256' ) {
			return new \WP_Error( 'spb_auth_invalid', __( 'Unsupported JWT algorithm.', 'simple-page-builder' ), array( 'status' => 401 ) );
		}
		$expected = hash_hmac( 'sha256', $h . '.' . $p, $secret, true );
		if ( ! hash_equals( $expected, $sig ) ) {
			return new \WP_Error( 'spb_auth_invalid', __( 'Invalid token signature.', 'simple-page-builder' ), array( 'status' => 401 ) );
		}
		// Validate exp if present.
		if ( isset( $payload['exp'] ) && is_numeric( $payload['exp'] ) && time() >= (int) $payload['exp'] ) {
			return new \WP_Error( 'spb_auth_invalid', __( 'Token has expired.', 'simple-page-builder' ), array( 'status' => 401 ) );
		}
		return $payload;
	}
}


