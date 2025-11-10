<?php
/**
 * Docs view.
 *
 * @package Simple_Page_Builder
 */

use SPB\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$endpoint = esc_url( get_rest_url( null, 'pagebuilder/v1/create-pages' ) );
?>

<h2><?php esc_html_e( 'API Documentation', 'simple-page-builder' ); ?></h2>

<h3><?php esc_html_e( 'Endpoint', 'simple-page-builder' ); ?></h3>
<p><code>POST <?php echo $endpoint; ?></code></p>

<h3><?php esc_html_e( 'Authentication', 'simple-page-builder' ); ?></h3>
<p><?php esc_html_e( 'Send the following headers. Do not use Basic Auth or user credentials.', 'simple-page-builder' ); ?></p>
<ul>
	<li><code>X-SPB-API-Key: &lt;your_api_key&gt;</code></li>
	<li><code>X-SPB-API-Secret: &lt;your_secret_key&gt;</code></li>
	<li><?php esc_html_e( 'Rate limit: 100 requests/hour per key (configurable).', 'simple-page-builder' ); ?></li>
</ul>

<h3><?php esc_html_e( 'Request Body', 'simple-page-builder' ); ?></h3>
<pre><code>{
  "pages": [
    {"title": "About Us", "content": "&lt;p&gt;About content...&lt;/p&gt;", "slug": "about"},
    {"title": "Contact", "content": "Contact details..."}
  ]
}</code></pre>

<h3><?php esc_html_e( 'Response (201 Created)', 'simple-page-builder' ); ?></h3>
<pre><code>{
  "request_id": "req_abc123xyz",
  "total_created": 2,
  "pages": [
    {"id": 123, "title": "About Us", "url": "https://example.com/about"},
    {"id": 124, "title": "Contact", "url": "https://example.com/contact"}
  ]
}</code></pre>

<h3><?php esc_html_e( 'Example cURL', 'simple-page-builder' ); ?></h3>
<pre><code>curl -X POST "<?php echo $endpoint; ?>" \
  -H "Content-Type: application/json" \
  -H "X-SPB-API-Key: &lt;your_api_key&gt;" \
  -H "X-SPB-API-Secret: &lt;your_secret_key&gt;" \
  -d '{ "pages": [ {"title": "Hello", "content": "World"} ] }'
</code></pre>

<h3><?php esc_html_e( 'Webhooks', 'simple-page-builder' ); ?></h3>
<p><?php esc_html_e( 'On success, a webhook is sent to the configured URL with HMAC-SHA256 signature in X-Webhook-Signature header.', 'simple-page-builder' ); ?></p>
<pre><code>{
  "event": "pages_created",
  "timestamp": "2025-10-07T14:30:00Z",
  "request_id": "req_abc123xyz",
  "api_key_name": "Production Server",
  "total_pages": 2,
  "pages": [
    {"id": 123, "title": "About Us", "url": "https://example.com/about"},
    {"id": 124, "title": "Contact", "url": "https://example.com/contact"}
  ]
}</code></pre>

<h3><?php esc_html_e( 'Errors', 'simple-page-builder' ); ?></h3>
<ul>
	<li><strong>401</strong> — <?php esc_html_e( 'Invalid or missing credentials', 'simple-page-builder' ); ?></li>
	<li><strong>403</strong> — <?php esc_html_e( 'Key revoked or expired', 'simple-page-builder' ); ?></li>
	<li><strong>429</strong> — <?php esc_html_e( 'Rate limit exceeded', 'simple-page-builder' ); ?></li>
	<li><strong>400</strong> — <?php esc_html_e( 'Invalid payload', 'simple-page-builder' ); ?></li>
	<li><strong>503</strong> — <?php esc_html_e( 'API disabled', 'simple-page-builder' ); ?></li>
	<li><strong>207</strong> — <?php esc_html_e( 'Partial success (some pages failed)', 'simple-page-builder' ); ?></li>
	<li><strong>201</strong> — <?php esc_html_e( 'Pages created', 'simple-page-builder' ); ?></li>
	<li><strong>200</strong> — <?php esc_html_e( 'OK (not used here)', 'simple-page-builder' ); ?></li>
	<li><strong>5xx</strong> — <?php esc_html_e( 'Server errors', 'simple-page-builder' ); ?></li>
	<li><strong>4xx</strong> — <?php esc_html_e( 'Client errors', 'simple-page-builder' ); ?></li>
</ul>

<h3><?php esc_html_e( 'JWT Mode (Optional)', 'simple-page-builder' ); ?></h3>
<p><?php esc_html_e( 'Enable JWT in Settings, then send a Bearer token (or X-SPB-JWT). Token must be HS256-signed with the Webhook Secret and include ak_fp = sha256(API Key) and exp.', 'simple-page-builder' ); ?></p>


