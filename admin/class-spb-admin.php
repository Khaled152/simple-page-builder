<?php
/**
 * Admin UI for Simple Page Builder.
 *
 * @package Simple_Page_Builder
 */

namespace SPB;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Admin
 */
class Admin {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_spb_generate_key', array( $this, 'handle_generate_key' ) );
		add_action( 'admin_post_spb_revoke_key', array( $this, 'handle_revoke_key' ) );
		add_action( 'admin_post_spb_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_spb_export_logs', array( $this, 'handle_export_logs' ) );
		add_action( 'admin_post_spb_generate_jwt', array( $this, 'handle_generate_jwt' ) );
	}

	/**
	 * Register admin menu.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_submenu_page(
			'tools.php',
			__( 'Page Builder', 'simple-page-builder' ),
			__( 'Page Builder', 'simple-page-builder' ),
			'manage_options',
			'spb-page-builder',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render main admin page with tabs.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'simple-page-builder' ) );
		}

		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'keys';
		$tabs = array(
			'keys'     => __( 'API Keys', 'simple-page-builder' ),
			'logs'     => __( 'API Activity Log', 'simple-page-builder' ),
			'pages'    => __( 'Created Pages', 'simple-page-builder' ),
			'settings' => __( 'Settings', 'simple-page-builder' ),
			'docs'     => __( 'API Documentation', 'simple-page-builder' ),
		);
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'keys';
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Simple Page Builder', 'simple-page-builder' ) . '</h1>';

		echo '<h2 class="nav-tab-wrapper">';
		foreach ( $tabs as $key => $label ) {
			$active = ( $key === $tab ) ? ' nav-tab-active' : '';
			$url    = admin_url( 'tools.php?page=spb-page-builder&tab=' . $key );
			echo '<a href="' . esc_url( $url ) . '" class="nav-tab' . esc_attr( $active ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</h2>';

		$view_file = SPB_PLUGIN_DIR . 'admin/views/' . $tab . '.php';
		if ( file_exists( $view_file ) ) {
			include $view_file;
		} else {
			echo '<p>' . esc_html__( 'View not found.', 'simple-page-builder' ) . '</p>';
		}

		echo '</div>';
	}

	/**
	 * Handle generate key action.
	 *
	 * @return void
	 */
	public function handle_generate_key() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'simple-page-builder' ) );
		}
		check_admin_referer( 'spb_generate_key' );

		$name   = isset( $_POST['spb_key_name'] ) ? sanitize_text_field( wp_unslash( $_POST['spb_key_name'] ) ) : '';
		$expiry = isset( $_POST['spb_key_expiry'] ) ? sanitize_text_field( wp_unslash( $_POST['spb_key_expiry'] ) ) : '';
		$expire_dt = null;
		if ( ! empty( $expiry ) ) {
			// Expecting Y-m-d format, convert to UTC midnight end of day.
			$ts = strtotime( $expiry . ' 23:59:59 UTC' );
			if ( $ts ) {
				$expire_dt = gmdate( 'Y-m-d H:i:s', $ts );
			}
		}

		$api_key    = random_string( 48 );
		$secret_key = random_string( 48 );

		$insert = Database::insert_api_key( $name, $api_key, $secret_key, $expire_dt );
		if ( false === $insert ) {
			wp_safe_redirect( admin_url( 'tools.php?page=spb-page-builder&tab=keys&error=insert_failed' ) );
			exit;
		}

		// Store in transient to show once.
		$tid = 'spb_new_key_' . (int) $insert['insert_id'];
		set_transient(
			$tid,
			array(
				'name'       => $name,
				'api_key'    => $api_key,
				'secret_key' => $secret_key,
			),
			10 * MINUTE_IN_SECONDS
		);

		wp_safe_redirect( admin_url( 'tools.php?page=spb-page-builder&tab=keys&show_key=' . (int) $insert['insert_id'] ) );
		exit;
	}

	/**
	 * Handle revoke or restore key.
	 *
	 * @return void
	 */
	public function handle_revoke_key() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'simple-page-builder' ) );
		}
		check_admin_referer( 'spb_revoke_key' );

		$id     = isset( $_POST['spb_key_id'] ) ? absint( $_POST['spb_key_id'] ) : 0;
		$action = isset( $_POST['spb_action'] ) ? sanitize_key( wp_unslash( $_POST['spb_action'] ) ) : 'revoke';
		$status = ( 'restore' === $action ) ? 'active' : 'revoked';
		if ( $id > 0 ) {
			Database::set_api_key_status( $id, $status );
		}
		wp_safe_redirect( admin_url( 'tools.php?page=spb-page-builder&tab=keys' ) );
		exit;
	}

	/**
	 * Handle settings save.
	 *
	 * @return void
	 */
	public function handle_save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'simple-page-builder' ) );
		}
		check_admin_referer( 'spb_save_settings' );

		$enable_api  = isset( $_POST['spb_enable_api'] ) ? (bool) $_POST['spb_enable_api'] : false;
		$webhook_url = isset( $_POST['spb_webhook_url'] ) ? esc_url_raw( wp_unslash( $_POST['spb_webhook_url'] ) ) : '';
		$rate_limit  = isset( $_POST['spb_rate_limit'] ) ? absint( $_POST['spb_rate_limit'] ) : 100;
		$expiration  = isset( $_POST['spb_default_expiration_days'] ) ? absint( $_POST['spb_default_expiration_days'] ) : 0;
		$auth_mode   = isset( $_POST['spb_auth_mode'] ) ? sanitize_key( wp_unslash( $_POST['spb_auth_mode'] ) ) : 'api_key';
		$delete_data = isset( $_POST['spb_delete_on_uninstall'] ) ? (bool) $_POST['spb_delete_on_uninstall'] : false;
		$regen_secret = isset( $_POST['spb_regen_webhook_secret'] ) ? (bool) $_POST['spb_regen_webhook_secret'] : false;

		Settings::set( 'enable_api', (bool) $enable_api );
		Settings::set( 'default_webhook_url', (string) $webhook_url );
		Settings::set( 'rate_limit_per_hour', max( 1, $rate_limit ) );
		Settings::set( 'default_key_expiration_days', $expiration );
		Settings::set( 'auth_mode', in_array( $auth_mode, array( 'api_key', 'jwt' ), true ) ? $auth_mode : 'api_key' );
		Settings::set( 'delete_data_on_uninstall', (bool) $delete_data );
		if ( $regen_secret ) {
			Settings::set( 'webhook_secret', random_string( 48 ) );
		}

		wp_safe_redirect( admin_url( 'tools.php?page=spb-page-builder&tab=settings&updated=1' ) );
		exit;
	}

	/**
	 * Handle CSV export for logs.
	 *
	 * @return void
	 */
	public function handle_export_logs() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'simple-page-builder' ) );
		}
		check_admin_referer( 'spb_export_logs' );

		$args = array(
			'api_key_id' => isset( $_GET['api_key_id'] ) ? absint( $_GET['api_key_id'] ) : 0,
			'result'     => isset( $_GET['result'] ) ? sanitize_key( wp_unslash( $_GET['result'] ) ) : '',
			'date_from'  => isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '',
			'date_to'    => isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '',
			'limit'      => 10000,
			'offset'     => 0,
		);
		$rows = Database::query_logs( $args );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=spb_api_logs_' . gmdate( 'Ymd_His' ) . '.csv' );

		$fh = fopen( 'php://output', 'w' );
		fputcsv( $fh, array( 'timestamp', 'request_id', 'api_key_id', 'endpoint', 'method', 'status_code', 'result', 'ip_address', 'user_agent', 'message', 'pages_created' ) );
		foreach ( $rows as $r ) {
			fputcsv(
				$fh,
				array(
					$r['timestamp'],
					$r['request_id'],
					$r['api_key_id'],
					$r['endpoint'],
					$r['method'],
					$r['status_code'],
					$r['result'],
					$r['ip_address'],
					$r['user_agent'],
					$r['message'],
					$r['pages_created'],
				)
			);
		}
		fclose( $fh );
		exit;
	}

	/**
	 * Handle JWT generation (admin utility).
	 *
	 * @return void
	 */
	public function handle_generate_jwt() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'simple-page-builder' ) );
		}
		check_admin_referer( 'spb_generate_jwt' );

		$api_key  = isset( $_POST['spb_jwt_api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['spb_jwt_api_key'] ) ) : '';
		$exp_secs = isset( $_POST['spb_jwt_exp_secs'] ) ? absint( $_POST['spb_jwt_exp_secs'] ) : 3600;
		$exp_secs = max( 60, $exp_secs );

		if ( '' === $api_key ) {
			wp_safe_redirect( admin_url( 'tools.php?page=spb-page-builder&tab=settings&jwt_error=missing_api_key' ) );
			exit;
		}

		$secret = (string) \SPB\Settings::get( 'webhook_secret', '' );
		if ( '' === $secret ) {
			wp_safe_redirect( admin_url( 'tools.php?page=spb-page-builder&tab=settings&jwt_error=missing_secret' ) );
			exit;
		}

		$header  = \SPB\base64url_encode( wp_json_encode( array( 'alg' => 'HS256', 'typ' => 'JWT' ) ) );
		$payload = \SPB\base64url_encode(
			wp_json_encode(
				array(
					'ak_fp' => hash( 'sha256', $api_key ),
					'exp'   => time() + $exp_secs,
				)
			)
		);
		$to_sign  = $header . '.' . $payload;
		$sign     = \SPB\base64url_encode( hash_hmac( 'sha256', $to_sign, $secret, true ) );
		$jwt      = $to_sign . '.' . $sign;

		$tid = 'spb_generated_jwt_' . get_current_user_id();
		set_transient( $tid, $jwt, 5 * MINUTE_IN_SECONDS );

		wp_safe_redirect( admin_url( 'tools.php?page=spb-page-builder&tab=settings&show_jwt=1' ) );
		exit;
	}
}


