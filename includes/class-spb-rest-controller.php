<?php
/**
 * REST API Controller.
 *
 * @package Simple_Page_Builder
 */

namespace SPB;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class REST_Controller
 */
class REST_Controller {

	/**
	 * Namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'pagebuilder/v1';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/create-pages',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'handle_create_pages' ),
					'permission_callback' => '__return_true',
					'args'                => array(),
				),
			)
		);
	}

	/**
	 * Handle create pages request.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_create_pages( WP_REST_Request $request ) {
		$request_id = 'req_' . substr( hash( 'sha256', wp_generate_uuid4() . microtime( true ) ), 0, 12 );
		$ip         = get_client_ip();
		$ua         = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		// Authenticate.
		$auth = Auth::authenticate_request( $request );
		if ( is_wp_error( $auth ) ) {
			Logger::log_request(
				array(
					'request_id'  => $request_id,
					'api_key_id'  => null,
					'endpoint'    => '/create-pages',
					'method'      => 'POST',
					'status_code' => $auth->get_error_data()['status'] ?? 401,
					'result'      => 'auth_failed',
					'ip_address'  => $ip,
					'user_agent'  => $ua,
					'message'     => $auth->get_error_message(),
				)
			);
			return $auth;
		}

		$api_key_row = $auth['key'];
		$key_id      = (int) $api_key_row['id'];

		// Rate limiting.
		$limit    = (int) Settings::get( 'rate_limit_per_hour', 100 );
		$rl_check = Rate_Limiter::check_and_increment( $key_id, $limit );
		if ( ! $rl_check['allowed'] ) {
			$reset_iso = gmdate( 'c', $rl_check['reset_at'] );
			Logger::log_request(
				array(
					'request_id'  => $request_id,
					'api_key_id'  => $key_id,
					'endpoint'    => '/create-pages',
					'method'      => 'POST',
					'status_code' => 429,
					'result'      => 'rate_limited',
					'ip_address'  => $ip,
					'user_agent'  => $ua,
					'message'     => 'Rate limit exceeded',
				)
			);
			return new WP_Error(
				'spb_rate_limited',
				sprintf(
					/* translators: %s: reset time */
					__( 'Rate limit exceeded. Try again after %s.', 'simple-page-builder' ),
					$reset_iso
				),
				array( 'status' => 429 )
			);
		}

		// Parse payload.
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			return new WP_Error( 'spb_invalid_json', __( 'Invalid JSON body.', 'simple-page-builder' ), array( 'status' => 400 ) );
		}

		$pages = isset( $params['pages'] ) ? $params['pages'] : null;
		if ( ! is_array( $pages ) || empty( $pages ) ) {
			return new WP_Error( 'spb_invalid_payload', __( 'Payload must include a non-empty "pages" array.', 'simple-page-builder' ), array( 'status' => 400 ) );
		}

		$max_pages = (int) apply_filters( 'spb_max_pages_per_request', 100, $request, $api_key_row );
		if ( count( $pages ) > $max_pages ) {
			Logger::log_request(
				array(
					'request_id'  => $request_id,
					'api_key_id'  => $key_id,
					'endpoint'    => '/create-pages',
					'method'      => 'POST',
					'status_code' => 400,
					'result'      => 'failed',
					'ip_address'  => $ip,
					'user_agent'  => $ua,
					'message'     => sprintf( 'Too many pages in one request. Max allowed: %d', $max_pages ),
				)
			);
			return new WP_Error(
				'spb_too_many_pages',
				sprintf(
					/* translators: %d: max pages */
					__( 'Too many pages in one request. Maximum allowed is %d.', 'simple-page-builder' ),
					$max_pages
				),
				array( 'status' => 400 )
			);
		}

		/**
		 * Fires before creating pages from the API.
		 *
		 * @param string          $request_id Request identifier.
		 * @param array           $api_key_row Authenticated API key row.
		 * @param array           $pages      Raw pages payload.
		 * @param WP_REST_Request $request    The REST request.
		 */
		do_action( 'spb_before_create_pages', $request_id, $api_key_row, $pages, $request );

		$created      = array();
		$created_ids  = array();
		$errors       = array();

		foreach ( $pages as $idx => $page ) {
			$title   = isset( $page['title'] ) ? wp_strip_all_tags( (string) $page['title'] ) : '';
			$content = isset( $page['content'] ) ? (string) $page['content'] : '';
			$slug    = isset( $page['slug'] ) ? sanitize_slug( (string) $page['slug'] ) : '';

			if ( '' === $title ) {
				$errors[] = array( 'index' => $idx, 'message' => 'Missing title' );
				continue;
			}

			$post_status = apply_filters( 'spb_default_post_status', 'publish', $page, $api_key_row, $request );
			$postarr = array(
				'post_type'    => 'page',
				'post_status'  => $post_status,
				'post_title'   => $title,
				'post_content' => $content,
			);
			if ( '' !== $slug ) {
				$postarr['post_name'] = $slug;
			}

			$postarr = apply_filters( 'spb_create_page_postarr', $postarr, $page, $api_key_row, $request );

			$post_id = wp_insert_post( $postarr, true );
			if ( is_wp_error( $post_id ) ) {
				$errors[] = array( 'index' => $idx, 'message' => $post_id->get_error_message() );
				continue;
			}

			add_post_meta( $post_id, '_spb_created_by_api', 1, true );
			$created_ids[] = $post_id;
			$created[] = array(
				'id'    => (int) $post_id,
				'title' => get_the_title( $post_id ),
				'url'   => get_permalink( $post_id ),
			);
		}

		/**
		 * Fires after attempting to create pages from the API.
		 *
		 * @param string          $request_id Request identifier.
		 * @param array           $api_key_row Authenticated API key row.
		 * @param array           $created    Created pages summary.
		 * @param array           $errors     Error entries for failed pages.
		 * @param WP_REST_Request $request    The REST request.
		 */
		do_action( 'spb_after_create_pages', $request_id, $api_key_row, $created, $errors, $request );

		if ( ! empty( $created_ids ) ) {
			Database::record_created_pages( $key_id, $created_ids );
		}

		// Touch and increment usage.
		Database::touch_api_key( $key_id, $ip );
		Database::increment_request_count( $key_id );

		$status_code = empty( $errors ) ? 201 : ( empty( $created ) ? 400 : 207 ); // 207 Multi-Status when partial success.
		$result_code = empty( $errors ) ? 'success' : ( empty( $created ) ? 'failed' : 'partial_success' );

		Logger::log_request(
			array(
				'request_id'    => $request_id,
				'api_key_id'    => $key_id,
				'endpoint'      => '/create-pages',
				'method'        => 'POST',
				'status_code'   => $status_code,
				'result'        => $result_code,
				'ip_address'    => $ip,
				'user_agent'    => $ua,
				'message'       => empty( $errors ) ? '' : wp_json_encode( $errors ),
				'pages_created' => count( $created_ids ),
			)
		);

		// Webhook notification (fire and log).
		Webhook::send_pages_created( $request_id, $api_key_row, $created );

		$response = array(
			'request_id'    => $request_id,
			'total_created' => count( $created ),
			'pages'         => $created,
		);
		if ( ! empty( $errors ) ) {
			$response['errors'] = $errors;
		}

		$res = new WP_REST_Response( $response, $status_code );
		return $res;
	}
}


