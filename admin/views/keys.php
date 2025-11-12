<?php
/**
 * Keys view.
 *
 * @package Simple_Page_Builder
 */

use SPB\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$show_id = isset( $_GET['show_key'] ) ? absint( $_GET['show_key'] ) : 0;
if ( $show_id ) {
	$tid = 'spb_new_key_' . $show_id;
	$data = get_transient( $tid );
	if ( $data ) {
		delete_transient( $tid );
		echo '<div class="notice notice-success"><p><strong>' . esc_html__( 'New API Key generated. Save these values now - they will not be shown again.', 'simple-page-builder' ) . '</strong></p>';
		echo '<p>' . esc_html__( 'Name:', 'simple-page-builder' ) . ' <code>' . esc_html( $data['name'] ) . '</code></p>';
		echo '<p>' . esc_html__( 'API Key:', 'simple-page-builder' ) . ' <code>' . esc_html( $data['api_key'] ) . '</code></p>';
		echo '<p>' . esc_html__( 'Secret Key:', 'simple-page-builder' ) . ' <code>' . esc_html( $data['secret_key'] ) . '</code></p>';
		echo '</div>';
	}
}
?>

<h2><?php esc_html_e( 'Generate New API Key', 'simple-page-builder' ); ?></h2>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php wp_nonce_field( 'spb_generate_key' ); ?>
	<input type="hidden" name="action" value="spb_generate_key" />
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="spb_key_name"><?php esc_html_e( 'Name', 'simple-page-builder' ); ?></label></th>
			<td><input name="spb_key_name" id="spb_key_name" type="text" class="regular-text" required placeholder="<?php esc_attr_e( 'e.g., Production Server', 'simple-page-builder' ); ?>"></td>
		</tr>
		<tr>
			<th scope="row"><label for="spb_key_expiry"><?php esc_html_e( 'Expiry (optional)', 'simple-page-builder' ); ?></label></th>
			<td><input name="spb_key_expiry" id="spb_key_expiry" type="date" class="regular-text"></td>
		</tr>
	</table>
	<?php submit_button( __( 'Generate API Key', 'simple-page-builder' ) ); ?>
	<p class="description"><?php esc_html_e( 'Keys are shown only once. Copy and store them securely.', 'simple-page-builder' ); ?></p>
</form>

<hr>

<h2><?php esc_html_e( 'API Keys', 'simple-page-builder' ); ?></h2>
<?php
$keys = Database::get_api_keys( array( 'limit' => 100 ) );
if ( empty( $keys ) ) :
	?>
	<p><?php esc_html_e( 'No API keys yet.', 'simple-page-builder' ); ?></p>
<?php else : ?>
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Name', 'simple-page-builder' ); ?></th>
				<th><?php esc_html_e( 'Preview', 'simple-page-builder' ); ?></th>
				<th><?php esc_html_e( 'Status', 'simple-page-builder' ); ?></th>
				<th><?php esc_html_e( 'Created', 'simple-page-builder' ); ?></th>
				<th><?php esc_html_e( 'Last Used', 'simple-page-builder' ); ?></th>
				<th><?php esc_html_e( 'Requests', 'simple-page-builder' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'simple-page-builder' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $keys as $k ) : ?>
			<tr>
				<td><?php echo esc_html( $k['name'] ); ?></td>
				<td><code><?php echo esc_html( $k['key_hint'] ? $k['key_hint'] : '••••' ); ?></code></td>
				<td><?php echo 'active' === $k['status'] ? '<span style="color:green;">' . esc_html__( 'Active', 'simple-page-builder' ) . '</span>' : '<span style="color:#cc0000;">' . esc_html__( 'Revoked', 'simple-page-builder' ) . '</span>'; ?></td>
				<td><?php echo esc_html( $k['created_at'] ); ?></td>
				<td><?php echo esc_html( $k['last_used'] ? $k['last_used'] : '—' ); ?></td>
				<td><?php echo esc_html( (string) $k['request_count'] ); ?></td>
				<td>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
						<?php wp_nonce_field( 'spb_revoke_key' ); ?>
						<input type="hidden" name="action" value="spb_revoke_key">
						<input type="hidden" name="spb_key_id" value="<?php echo (int) $k['id']; ?>">
						<?php if ( 'active' === $k['status'] ) : ?>
							<input type="hidden" name="spb_action" value="revoke">
							<?php submit_button( __( 'Revoke', 'simple-page-builder' ), 'delete small', 'submit', false ); ?>
						<?php else : ?>
							<input type="hidden" name="spb_action" value="restore">
							<?php submit_button( __( 'Restore', 'simple-page-builder' ), 'secondary small', 'submit', false ); ?>
						<?php endif; ?>
					</form>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>


