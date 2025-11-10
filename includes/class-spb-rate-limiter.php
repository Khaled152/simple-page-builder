<?php
/**
 * Rate limiter per API key.
 *
 * @package Simple_Page_Builder
 */

namespace SPB;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Rate_Limiter
 */
class Rate_Limiter {

	/**
	 * Check and increment rate usage for a given key ID.
	 *
	 * @param int $api_key_id API key ID.
	 * @param int $limit_per_hour Requests per hour.
	 * @return array { allowed: bool, remaining: int, reset_at: int }
	 */
	public static function check_and_increment( $api_key_id, $limit_per_hour ) {
		$limit_per_hour = max( 1, absint( $limit_per_hour ) );
		$key            = 'spb_rl_' . (int) $api_key_id;
		$data           = get_transient( $key );
		$now            = time();
		$window         = 3600; // 1 hour.

		if ( ! is_array( $data ) || empty( $data['reset_at'] ) || $now >= (int) $data['reset_at'] ) {
			$data = array(
				'count'    => 0,
				'reset_at' => $now + $window,
			);
		}

		if ( $data['count'] >= $limit_per_hour ) {
			return array(
				'allowed'   => false,
				'remaining' => 0,
				'reset_at'  => (int) $data['reset_at'],
			);
		}

		$data['count']++;
		$ttl = max( 1, (int) $data['reset_at'] - $now );
		set_transient( $key, $data, $ttl );

		return array(
			'allowed'   => true,
			'remaining' => max( 0, $limit_per_hour - $data['count'] ),
			'reset_at'  => (int) $data['reset_at'],
		);
	}
}


