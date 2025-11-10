<?php
/**
 * Logs view.
 *
 * @package Simple_Page_Builder
 */

use SPB\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$api_key_id = isset( $_GET['api_key_id'] ) ? absint( $_GET['api_key_id'] ) : 0;
$result     = isset( $_GET['result'] ) ? sanitize_key( wp_unslash( $_GET['result'] ) ) : '';
$date_from  = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
$date_to    = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';

$args = array(
	'api_key_id' => $api_key_id,
	'result'     => $result,
	'date_from'  => $date_from,
	'date_to'    => $date_to,
	'limit'      => 100,
);
$rows = Database::query_logs( $args );
?>

<h2><?php esc_html_e( 'API Activity Log', 'simple-page-builder' ); ?></h2>
<form method="get" action="">
	<input type="hidden" name="page" value="spb-page-builder">
	<input type="hidden" name="tab" value="logs">
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="api_key_id"><?php esc_html_e( 'API Key ID', 'simple-page-builder' ); ?></label></th>
			<td><input name="api_key_id" id="api_key_id" type="number" class="regular-text" value="<?php echo (int) $api_key_id; ?>"></td>
		</tr>
		<tr>
			<th scope="row"><label for="result"><?php esc_html_e( 'Result', 'simple-page-builder' ); ?></label></th>
			<td>
				<select name="result" id="result">
					<option value=""><?php esc_html_e( 'Any', 'simple-page-builder' ); ?></option>
					<?php foreach ( array( 'success', 'partial_success', 'failed', 'auth_failed', 'rate_limited' ) as $opt ) : ?>
						<option value="<?php echo esc_attr( $opt ); ?>" <?php selected( $result, $opt ); ?>><?php echo esc_html( ucfirst( str_replace( '_', ' ', $opt ) ) ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="date_from"><?php esc_html_e( 'From (UTC)', 'simple-page-builder' ); ?></label></th>
			<td><input name="date_from" id="date_from" type="datetime-local" class="regular-text" value="<?php echo esc_attr( $date_from ); ?>"></td>
		</tr>
		<tr>
			<th scope="row"><label for="date_to"><?php esc_html_e( 'To (UTC)', 'simple-page-builder' ); ?></label></th>
			<td><input name="date_to" id="date_to" type="datetime-local" class="regular-text" value="<?php echo esc_attr( $date_to ); ?>"></td>
		</tr>
	</table>
	<?php submit_button( __( 'Filter', 'simple-page-builder' ), 'secondary' ); ?>
</form>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:1em;">
	<?php wp_nonce_field( 'spb_export_logs' ); ?>
	<input type="hidden" name="action" value="spb_export_logs">
	<input type="hidden" name="api_key_id" value="<?php echo (int) $api_key_id; ?>">
	<input type="hidden" name="result" value="<?php echo esc_attr( $result ); ?>">
	<input type="hidden" name="date_from" value="<?php echo esc_attr( $date_from ); ?>">
	<input type="hidden" name="date_to" value="<?php echo esc_attr( $date_to ); ?>">
	<?php submit_button( __( 'Export as CSV', 'simple-page-builder' ), 'primary' ); ?>
</form>

<hr>

<table class="widefat striped">
	<thead>
		<tr>
			<th><?php esc_html_e( 'Time (UTC)', 'simple-page-builder' ); ?></th>
			<th><?php esc_html_e( 'Request ID', 'simple-page-builder' ); ?></th>
			<th><?php esc_html_e( 'API Key ID', 'simple-page-builder' ); ?></th>
			<th><?php esc_html_e( 'Endpoint', 'simple-page-builder' ); ?></th>
			<th><?php esc_html_e( 'Method', 'simple-page-builder' ); ?></th>
			<th><?php esc_html_e( 'Status', 'simple-page-builder' ); ?></th>
			<th><?php esc_html_e( 'Result', 'simple-page-builder' ); ?></th>
			<th><?php esc_html_e( 'IP', 'simple-page-builder' ); ?></th>
			<th><?php esc_html_e( 'Pages', 'simple-page-builder' ); ?></th>
			<th><?php esc_html_e( 'Message', 'simple-page-builder' ); ?></th>
		</tr>
	</thead>
	<tbody>
	<?php if ( empty( $rows ) ) : ?>
		<tr><td colspan="10"><?php esc_html_e( 'No logs found for the selected filters.', 'simple-page-builder' ); ?></td></tr>
	<?php else : ?>
		<?php foreach ( $rows as $r ) : ?>
			<tr>
				<td><?php echo esc_html( $r['timestamp'] ); ?></td>
				<td><code><?php echo esc_html( $r['request_id'] ); ?></code></td>
				<td><?php echo esc_html( (string) $r['api_key_id'] ); ?></td>
				<td><?php echo esc_html( $r['endpoint'] ); ?></td>
				<td><?php echo esc_html( $r['method'] ); ?></td>
				<td><?php echo esc_html( (string) $r['status_code'] ); ?></td>
				<td><?php echo esc_html( $r['result'] ); ?></td>
				<td><?php echo esc_html( $r['ip_address'] ); ?></td>
				<td><?php echo esc_html( (string) $r['pages_created'] ); ?></td>
				<td><code><?php echo esc_html( $r['message'] ); ?></code></td>
			</tr>
		<?php endforeach; ?>
	<?php endif; ?>
	</tbody>
</table>


