<?php
/**
 * Webhook sender.
 *
 * @package Simple_Page_Builder
 */

namespace SPB;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Webhook
 */
class Webhook {

	/**
	 * Send "pages_created" webhook.
	 *
	 * @param string $request_id Request ID.
	 * @param array  $api_key    API key row.
	 * @param array  $pages      Pages array [{id,title,url}].
	 * @param string $override_url Optional override webhook URL.
	 * @return void
	 */
	public static function send_pages_created( $request_id, $api_key, $pages, $override_url = '' ) {
		$url = $override_url;
		if ( empty( $url ) ) {
			$url = Settings::get( 'default_webhook_url', '' );
		}
		$url = trim( (string) $url );
		$url = apply_filters( 'spb_webhook_url', $url, $request_id, $api_key, $pages );
		if ( '' === $url ) {
			return;
		}

		$payload = apply_filters(
			'spb_webhook_payload',
			array(
			'event'        => 'pages_created',
			'timestamp'    => iso8601_now(),
			'request_id'   => $request_id,
			'api_key_name' => isset( $api_key['name'] ) ? $api_key['name'] : '',
			'total_pages'  => count( $pages ),
			'pages'        => array_values( $pages ),
			),
			$request_id,
			$api_key,
			$pages
		);

		$secret     = (string) Settings::get( 'webhook_secret', '' );
		$body       = wp_json_encode( $payload );
		$signature  = '';
		if ( '' !== $secret ) {
			$signature = hash_hmac( 'sha256', $body, $secret );
		}

		$headers = array(
			'Content-Type'        => 'application/json',
			'X-Webhook-Signature' => $signature,
		);
		$headers = apply_filters( 'spb_webhook_headers', $headers, $request_id, $url, $payload );

		$max_retries = (int) apply_filters( 'spb_webhook_max_retries', 2, $request_id, $url, $payload );
		$attempt     = 0;
		$delays      = (array) apply_filters( 'spb_webhook_retry_delays', array( 1, 2 ), $request_id, $url, $payload ); // seconds.
		$success     = false;
		$response    = null;
		$http_code   = 0;

		do {
			$attempt++;
			do_action( 'spb_webhook_attempt', $attempt, $request_id, $url, $payload );
			$args = array(
				'headers' => $headers,
				'body'    => $body,
				'timeout' => 20,
			);
			$args     = apply_filters( 'spb_webhook_request_args', $args, $request_id, $url, $payload );
			$response = wp_remote_post( $url, $args );

			if ( ! is_wp_error( $response ) ) {
				$http_code = (int) wp_remote_retrieve_response_code( $response );
				if ( $http_code >= 200 && $http_code < 300 ) {
					$success = true;
					break;
				}
			}

			if ( $attempt <= $max_retries ) {
				$delay = isset( $delays[ $attempt - 1 ] ) ? (int) $delays[ $attempt - 1 ] : ( 2 ** $attempt );
				if ( function_exists( 'wp_sleep' ) ) {
					wp_sleep( $delay );
				} else {
					sleep( $delay );
				}
			}
		} while ( $attempt <= $max_retries );

		do_action( 'spb_webhook_result', $success, $http_code, $request_id, $url, $response, $payload );

		// Log webhook result.
		$response_body = is_wp_error( $response ) ? $response->get_error_message() : wp_remote_retrieve_body( $response );
		Logger::log_webhook(
			array(
				'request_id'    => $request_id,
				'url'           => $url,
				'status'        => $success ? 'success' : 'failed',
				'http_code'     => $http_code,
				'attempts'      => $attempt,
				'response_body' => $response_body,
			)
		);
	}
}


