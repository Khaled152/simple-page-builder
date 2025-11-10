<?php
/**
 * Database layer for Simple Page Builder.
 *
 * @package Simple_Page_Builder
 */

namespace SPB;

use wpdb;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Database
 *
 * Handles DB schema and common queries.
 */
class Database {

	/**
	 * Return table names.
	 *
	 * @return array
	 */
	public static function tables() {
		global $wpdb;
		return array(
			'keys'          => $wpdb->prefix . 'spb_api_keys',
			'logs'          => $wpdb->prefix . 'spb_api_logs',
			'created_pages' => $wpdb->prefix . 'spb_created_pages',
			'webhook_logs'  => $wpdb->prefix . 'spb_webhook_logs',
		);
	}

	/**
	 * Get a specific table name by key.
	 *
	 * @param string $key Table key.
	 * @return string
	 */
	public static function table( $key ) {
		$tables = self::tables();
		return isset( $tables[ $key ] ) ? $tables[ $key ] : '';
	}

	/**
	 * Activation: create tables.
	 *
	 * @return void
	 */
	public static function activate() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$tables          = self::tables();

		$sql_keys = "CREATE TABLE {$tables['keys']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL,
			api_key_hash VARCHAR(255) NOT NULL,
			secret_key_hash VARCHAR(255) NOT NULL,
			api_key_fp CHAR(64) NOT NULL,
			secret_key_fp CHAR(64) NOT NULL,
			key_hint VARCHAR(24) NULL,
			status ENUM('active','revoked') NOT NULL DEFAULT 'active',
			created_at DATETIME NOT NULL,
			expires_at DATETIME NULL,
			last_used DATETIME NULL,
			request_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
			last_ip VARCHAR(45) NULL,
			PRIMARY KEY  (id),
			KEY api_key_fp (api_key_fp),
			KEY status (status),
			KEY created_at (created_at)
		) $charset_collate;";

		$sql_logs = "CREATE TABLE {$tables['logs']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			request_id VARCHAR(64) NOT NULL,
			api_key_id BIGINT UNSIGNED NULL,
			timestamp DATETIME NOT NULL,
			endpoint VARCHAR(191) NOT NULL,
			method VARCHAR(10) NOT NULL,
			status_code INT NOT NULL,
			result VARCHAR(32) NOT NULL,
			ip_address VARCHAR(45) NULL,
			user_agent VARCHAR(255) NULL,
			message TEXT NULL,
			pages_created INT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			KEY request_id (request_id),
			KEY api_key_id (api_key_id),
			KEY timestamp (timestamp)
		) $charset_collate;";

		$sql_pages = "CREATE TABLE {$tables['created_pages']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			post_id BIGINT UNSIGNED NOT NULL,
			api_key_id BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY post_id (post_id),
			KEY api_key_id (api_key_id),
			KEY created_at (created_at)
		) $charset_collate;";

		$sql_webhook = "CREATE TABLE {$tables['webhook_logs']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			request_id VARCHAR(64) NOT NULL,
			url VARCHAR(255) NOT NULL,
			status VARCHAR(20) NOT NULL,
			http_code INT NULL,
			attempts INT NOT NULL DEFAULT 1,
			response_body LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY request_id (request_id),
			KEY created_at (created_at)
		) $charset_collate;";

		dbDelta( $sql_keys );
		dbDelta( $sql_logs );
		dbDelta( $sql_pages );
		dbDelta( $sql_webhook );

		update_option( 'spb_db_version', SPB_DB_VERSION );
	}

	/**
	 * Deactivation.
	 *
	 * @return void
	 */
	public static function deactivate() {
		// No action on deactivate (data retention).
	}

	/**
	 * Insert a new API key, storing only secure hashes and fingerprints.
	 *
	 * @param string      $name            Key name/label.
	 * @param string      $api_key_plain   Plain api key.
	 * @param string      $secret_plain    Plain secret key.
	 * @param null|string $expires_at      Optional expiration (Y-m-d H:i:s) UTC.
	 * @return array{insert_id:int}|false
	 */
	public static function insert_api_key( $name, $api_key_plain, $secret_plain, $expires_at = null ) {
		global $wpdb;
		$table = self::table( 'keys' );
		if ( empty( $table ) ) {
			return false;
		}

		$now      = current_time( 'mysql', true );
		$api_hash = password_hash( $api_key_plain, PASSWORD_DEFAULT );
		$sec_hash = password_hash( $secret_plain, PASSWORD_DEFAULT );
		$api_fp   = hash( 'sha256', $api_key_plain );
		$sec_fp   = hash( 'sha256', $secret_plain );
		$hint     = substr( $api_key_plain, 0, 4 ) . '...' . substr( $api_key_plain, -4 );

		$inserted = $wpdb->insert(
			$table,
			array(
				'name'             => $name,
				'api_key_hash'     => $api_hash,
				'secret_key_hash'  => $sec_hash,
				'api_key_fp'       => $api_fp,
				'secret_key_fp'    => $sec_fp,
				'key_hint'         => $hint,
				'status'           => 'active',
				'created_at'       => $now,
				'expires_at'       => $expires_at,
				'last_used'        => null,
				'request_count'    => 0,
				'last_ip'          => null,
			),
			array( '%s','%s','%s','%s','%s','%s','%s','%s','%s', null, '%d', null )
		);

		if ( false === $inserted ) {
			return false;
		}

		return array(
			'insert_id' => (int) $wpdb->insert_id,
		);
	}

	/**
	 * Get API key row by fingerprint.
	 *
	 * @param string $api_key_fp SHA256 fingerprint of api key.
	 * @return array|null
	 */
	public static function get_api_key_by_fingerprint( $api_key_fp ) {
		global $wpdb;
		$table = self::table( 'keys' );
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE api_key_fp = %s LIMIT 1",
				$api_key_fp
			),
			ARRAY_A
		);
	}

	/**
	 * Get API key by ID.
	 *
	 * @param int $id ID.
	 * @return array|null
	 */
	public static function get_api_key_by_id( $id ) {
		global $wpdb;
		$table = self::table( 'keys' );
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d LIMIT 1",
				$id
			),
			ARRAY_A
		);
	}

	/**
	 * Update API key usage stats.
	 *
	 * @param int    $id Key ID.
	 * @param string $ip IP address.
	 * @return void
	 */
	public static function touch_api_key( $id, $ip ) {
		global $wpdb;
		$table = self::table( 'keys' );
		$wpdb->update(
			$table,
			array(
				'last_used' => current_time( 'mysql', true ),
				'last_ip'   => $ip,
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Increment request_count.
	 *
	 * @param int $id Key ID.
	 * @return void
	 */
	public static function increment_request_count( $id ) {
		global $wpdb;
		$table = self::table( 'keys' );
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET request_count = request_count + 1 WHERE id = %d",
				$id
			)
		);
	}

	/**
	 * Set key status.
	 *
	 * @param int    $id Key ID.
	 * @param string $status Status.
	 * @return bool
	 */
	public static function set_api_key_status( $id, $status ) {
		global $wpdb;
		$table = self::table( 'keys' );
		return (bool) $wpdb->update(
			$table,
			array( 'status' => $status ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Get API keys for admin listing.
	 *
	 * @param array $args Args.
	 * @return array
	 */
	public static function get_api_keys( $args = array() ) {
		global $wpdb;
		$table   = self::table( 'keys' );
		$where   = 'WHERE 1=1';
		$params  = array();
		$status  = isset( $args['status'] ) ? $args['status'] : '';
		$limit   = isset( $args['limit'] ) ? absint( $args['limit'] ) : 50;
		$offset  = isset( $args['offset'] ) ? absint( $args['offset'] ) : 0;
		$orderby = 'created_at';
		$order   = 'DESC';

		if ( in_array( $status, array( 'active', 'revoked' ), true ) ) {
			$where   .= ' AND status = %s';
			$params[] = $status;
		}

		$sql = "SELECT * FROM {$table} {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$params[] = $limit;
		$params[] = $offset;

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
	}

	/**
	 * Log API request.
	 *
	 * @param array $data Data [request_id, api_key_id, endpoint, method, status_code, result, ip_address, user_agent, message, pages_created].
	 * @return int Insert ID.
	 */
	public static function log_request( $data ) {
		global $wpdb;
		$table = self::table( 'logs' );
		$wpdb->insert(
			$table,
			array(
				'request_id'    => isset( $data['request_id'] ) ? $data['request_id'] : '',
				'api_key_id'    => isset( $data['api_key_id'] ) ? $data['api_key_id'] : null,
				'timestamp'     => current_time( 'mysql', true ),
				'endpoint'      => isset( $data['endpoint'] ) ? $data['endpoint'] : '',
				'method'        => isset( $data['method'] ) ? $data['method'] : 'POST',
				'status_code'   => isset( $data['status_code'] ) ? (int) $data['status_code'] : 0,
				'result'        => isset( $data['result'] ) ? $data['result'] : '',
				'ip_address'    => isset( $data['ip_address'] ) ? $data['ip_address'] : '',
				'user_agent'    => isset( $data['user_agent'] ) ? $data['user_agent'] : '',
				'message'       => isset( $data['message'] ) ? $data['message'] : '',
				'pages_created' => isset( $data['pages_created'] ) ? (int) $data['pages_created'] : 0,
			),
			array( '%s','%d','%s','%s','%d','%s','%s','%s','%s','%d' )
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * Log webhook delivery attempt.
	 *
	 * @param array $data Data [request_id, url, status, http_code, attempts, response_body].
	 * @return int Insert ID.
	 */
	public static function log_webhook( $data ) {
		global $wpdb;
		$table = self::table( 'webhook_logs' );
		$wpdb->insert(
			$table,
			array(
				'request_id'   => isset( $data['request_id'] ) ? $data['request_id'] : '',
				'url'          => isset( $data['url'] ) ? $data['url'] : '',
				'status'       => isset( $data['status'] ) ? $data['status'] : '',
				'http_code'    => isset( $data['http_code'] ) ? (int) $data['http_code'] : null,
				'attempts'     => isset( $data['attempts'] ) ? (int) $data['attempts'] : 1,
				'response_body'=> isset( $data['response_body'] ) ? $data['response_body'] : null,
				'created_at'   => current_time( 'mysql', true ),
			),
			array( '%s','%s','%s','%d','%d', null, '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * Record created pages mapping.
	 *
	 * @param int   $api_key_id API Key ID (nullable).
	 * @param array $post_ids Post IDs.
	 * @return void
	 */
	public static function record_created_pages( $api_key_id, $post_ids ) {
		global $wpdb;
		$table = self::table( 'created_pages' );
		$now   = current_time( 'mysql', true );
		foreach ( $post_ids as $pid ) {
			$wpdb->insert(
				$table,
				array(
					'post_id'    => (int) $pid,
					'api_key_id' => $api_key_id ? (int) $api_key_id : null,
					'created_at' => $now,
				),
				array( '%d','%d','%s' )
			);
		}
	}

	/**
	 * Query logs for admin.
	 *
	 * @param array $args Args.
	 * @return array
	 */
	public static function query_logs( $args = array() ) {
		global $wpdb;
		$table  = self::table( 'logs' );
		$where  = 'WHERE 1=1';
		$params = array();

		if ( ! empty( $args['api_key_id'] ) ) {
			$where   .= ' AND api_key_id = %d';
			$params[] = (int) $args['api_key_id'];
		}
		if ( ! empty( $args['result'] ) ) {
			$where   .= ' AND result = %s';
			$params[] = $args['result'];
		}
		if ( ! empty( $args['date_from'] ) ) {
			$where   .= ' AND timestamp >= %s';
			$params[] = $args['date_from'];
		}
		if ( ! empty( $args['date_to'] ) ) {
			$where   .= ' AND timestamp <= %s';
			$params[] = $args['date_to'];
		}

		$limit   = isset( $args['limit'] ) ? absint( $args['limit'] ) : 50;
		$offset  = isset( $args['offset'] ) ? absint( $args['offset'] ) : 0;
		$order   = 'DESC';
		$sql     = "SELECT * FROM {$table} {$where} ORDER BY timestamp {$order} LIMIT %d OFFSET %d";
		$params[] = $limit;
		$params[] = $offset;

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
	}

	/**
	 * Query created pages for admin.
	 *
	 * @param array $args Args.
	 * @return array
	 */
	public static function query_created_pages( $args = array() ) {
		global $wpdb;
		$table  = self::table( 'created_pages' );
		$where  = 'WHERE 1=1';
		$params = array();

		if ( ! empty( $args['api_key_id'] ) ) {
			$where   .= ' AND api_key_id = %d';
			$params[] = (int) $args['api_key_id'];
		}

		$limit   = isset( $args['limit'] ) ? absint( $args['limit'] ) : 50;
		$offset  = isset( $args['offset'] ) ? absint( $args['offset'] ) : 0;
		$sql     = "SELECT * FROM {$table} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d";
		$params[] = $limit;
		$params[] = $offset;

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
	}
}


