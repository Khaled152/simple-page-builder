<?php
/**
 * Settings view.
 *
 * @package Simple_Page_Builder
 */

use SPB\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings = Settings::all();
$updated = isset( $_GET['updated'] );
if ( $updated ) {
	echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved.', 'simple-page-builder' ) . '</p></div>';
}
$jwt_error = isset( $_GET['jwt_error'] ) ? sanitize_key( wp_unslash( $_GET['jwt_error'] ) ) : '';
if ( $jwt_error ) {
	$msg = 'missing_api_key' === $jwt_error ? __( 'Please provide an API Key to generate a JWT.', 'simple-page-builder' ) : __( 'Missing Webhook Secret. Set or regenerate it first.', 'simple-page-builder' );
	echo '<div class="notice notice-error"><p>' . esc_html( $msg ) . '</p></div>';
}
$show_jwt = isset( $_GET['show_jwt'] );
if ( $show_jwt ) {
	$tid = 'spb_generated_jwt_' . get_current_user_id();
	$jwt = get_transient( $tid );
	if ( $jwt ) {
		delete_transient( $tid );
		echo '<div class="notice notice-success"><p><strong>' . esc_html__( 'JWT generated. Copy and use it as Bearer token.', 'simple-page-builder' ) . '</strong></p>';
		echo '<p><textarea readonly rows="3" style="width:100%;">' . esc_textarea( $jwt ) . '</textarea></p>';
		echo '</div>';
	}
}
?>

<h2><?php esc_html_e( 'Settings', 'simple-page-builder' ); ?></h2>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php wp_nonce_field( 'spb_save_settings' ); ?>
	<input type="hidden" name="action" value="spb_save_settings" />
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Enable API', 'simple-page-builder' ); ?></th>
			<td><label><input type="checkbox" name="spb_enable_api" value="1" <?php checked( ! empty( $settings['enable_api'] ) ); ?>> <?php esc_html_e( 'Allow external applications to call the API', 'simple-page-builder' ); ?></label></td>
		</tr>
		<tr>
			<th scope="row"><label for="spb_webhook_url"><?php esc_html_e( 'Default Webhook URL', 'simple-page-builder' ); ?></label></th>
			<td><input type="url" id="spb_webhook_url" name="spb_webhook_url" class="regular-text" value="<?php echo esc_attr( $settings['default_webhook_url'] ?? '' ); ?>" placeholder="https://example.com/webhook"></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Webhook Secret', 'simple-page-builder' ); ?></th>
			<td>
				<code><?php echo esc_html( substr( (string) ( $settings['webhook_secret'] ?? '' ), 0, 4 ) . '…' . substr( (string) ( $settings['webhook_secret'] ?? '' ), -4 ) ); ?></code>
				<p class="description"><?php esc_html_e( 'Used to sign webhooks via HMAC-SHA256 (X-Webhook-Signature).', 'simple-page-builder' ); ?></p>
				<label><input type="checkbox" name="spb_regen_webhook_secret" value="1"> <?php esc_html_e( 'Regenerate secret on save', 'simple-page-builder' ); ?></label>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="spb_rate_limit"><?php esc_html_e( 'Rate Limit (requests/hour per key)', 'simple-page-builder' ); ?></label></th>
			<td><input type="number" id="spb_rate_limit" name="spb_rate_limit" class="small-text" min="1" value="<?php echo (int) ( $settings['rate_limit_per_hour'] ?? 100 ); ?>"></td>
		</tr>
		<tr>
			<th scope="row"><label for="spb_default_expiration_days"><?php esc_html_e( 'Default Key Expiration (days)', 'simple-page-builder' ); ?></label></th>
			<td><input type="number" id="spb_default_expiration_days" name="spb_default_expiration_days" class="small-text" min="0" value="<?php echo (int) ( $settings['default_key_expiration_days'] ?? 0 ); ?>"> <span class="description"><?php esc_html_e( '0 means no default expiration', 'simple-page-builder' ); ?></span></td>
		</tr>
		<tr>
			<th scope="row"><label for="spb_auth_mode"><?php esc_html_e( 'Authentication Mode', 'simple-page-builder' ); ?></label></th>
			<td>
				<select id="spb_auth_mode" name="spb_auth_mode">
					<option value="api_key" <?php selected( ( $settings['auth_mode'] ?? 'api_key' ), 'api_key' ); ?>><?php esc_html_e( 'API Key + Secret (recommended)', 'simple-page-builder' ); ?></option>
					<option value="jwt" <?php selected( ( $settings['auth_mode'] ?? 'api_key' ), 'jwt' ); ?>><?php esc_html_e( 'JWT (optional bonus)', 'simple-page-builder' ); ?></option>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Uninstall Data', 'simple-page-builder' ); ?></th>
			<td><label><input type="checkbox" name="spb_delete_on_uninstall" value="1" <?php checked( ! empty( $settings['delete_data_on_uninstall'] ) ); ?>> <?php esc_html_e( 'Delete all plugin data on uninstall', 'simple-page-builder' ); ?></label></td>
		</tr>
	</table>
	<?php submit_button( __( 'Save Settings', 'simple-page-builder' ) ); ?>
</form>

<hr>

<h2><?php esc_html_e( 'Generate JWT (Admin Utility)', 'simple-page-builder' ); ?></h2>
<p class="description"><?php esc_html_e( 'Use this tool to generate a JWT for a specific API Key. Paste the API Key (not the secret). The token is signed with the Webhook Secret.', 'simple-page-builder' ); ?></p>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php wp_nonce_field( 'spb_generate_jwt' ); ?>
	<input type="hidden" name="action" value="spb_generate_jwt" />
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="spb_jwt_api_key"><?php esc_html_e( 'API Key', 'simple-page-builder' ); ?></label></th>
			<td><input type="text" id="spb_jwt_api_key" name="spb_jwt_api_key" class="regular-text" placeholder="<?php esc_attr_e( 'Paste API Key here', 'simple-page-builder' ); ?>"></td>
		</tr>
		<tr>
			<th scope="row"><label for="spb_jwt_exp_secs"><?php esc_html_e( 'Expires In (seconds)', 'simple-page-builder' ); ?></label></th>
			<td><input type="number" id="spb_jwt_exp_secs" name="spb_jwt_exp_secs" class="small-text" min="60" value="3600"> <span class="description"><?php esc_html_e( 'Minimum 60 seconds', 'simple-page-builder' ); ?></span></td>
		</tr>
	</table>
	<?php submit_button( __( 'Generate JWT', 'simple-page-builder' ) ); ?>
</form>


