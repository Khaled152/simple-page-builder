<?php
/**
 * Created pages view.
 *
 * @package Simple_Page_Builder
 */

use SPB\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$api_key_id = isset( $_GET['api_key_id'] ) ? absint( $_GET['api_key_id'] ) : 0;
$rows = Database::query_created_pages(
	array(
		'api_key_id' => $api_key_id,
		'limit'      => 100,
	)
);
?>

<h2><?php esc_html_e( 'Pages Created via API', 'simple-page-builder' ); ?></h2>
<p class="description"><?php esc_html_e( 'Showing up to the 100 most recent entries. Timestamps are in UTC.', 'simple-page-builder' ); ?></p>

<form method="get" action="">
	<input type="hidden" name="page" value="spb-page-builder">
	<input type="hidden" name="tab" value="pages">
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="api_key_id"><?php esc_html_e( 'API Key ID', 'simple-page-builder' ); ?></label></th>
			<td><input name="api_key_id" id="api_key_id" type="number" class="regular-text" value="<?php echo (int) $api_key_id; ?>"></td>
		</tr>
	</table>
	<?php submit_button( __( 'Filter', 'simple-page-builder' ), 'secondary' ); ?>
</form>

<hr>

<table class="widefat striped">
	<thead>
		<tr>
			<th><?php esc_html_e( 'Created (UTC)', 'simple-page-builder' ); ?></th>
			<th><?php esc_html_e( 'Post ID', 'simple-page-builder' ); ?></th>
			<th><?php esc_html_e( 'Title', 'simple-page-builder' ); ?></th>
			<th><?php esc_html_e( 'URL', 'simple-page-builder' ); ?></th>
			<th><?php esc_html_e( 'API Key ID', 'simple-page-builder' ); ?></th>
		</tr>
	</thead>
	<tbody>
	<?php if ( empty( $rows ) ) : ?>
		<tr><td colspan="5"><?php esc_html_e( 'No pages found.', 'simple-page-builder' ); ?></td></tr>
	<?php else : ?>
		<?php foreach ( $rows as $r ) : ?>
			<?php
				$post_id = (int) $r['post_id'];
				$title   = get_the_title( $post_id );
				$url     = get_permalink( $post_id );
			?>
			<tr>
				<td><?php echo esc_html( $r['created_at'] ); ?></td>
				<td><?php echo esc_html( (string) $post_id ); ?></td>
				<td><?php echo esc_html( $title ); ?></td>
				<td><a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $url ); ?></a></td>
				<td><?php echo esc_html( (string) $r['api_key_id'] ); ?></td>
			</tr>
		<?php endforeach; ?>
	<?php endif; ?>
	</tbody>
</table>


