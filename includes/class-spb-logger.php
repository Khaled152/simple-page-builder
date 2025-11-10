<?php
/**
 * Logger for API requests and webhooks.
 *
 * @package Simple_Page_Builder
 */

namespace SPB;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Logger
 */
class Logger {

	/**
	 * Log an API request.
	 *
	 * @param array $args Args.
	 * @return int
	 */
	public static function log_request( $args ) {
		return Database::log_request( $args );
	}

	/**
	 * Log a webhook delivery attempt.
	 *
	 * @param array $args Args.
	 * @return int
	 */
	public static function log_webhook( $args ) {
		return Database::log_webhook( $args );
	}
}


