# Simple Page Builder

Bulk create WordPress pages via a secure REST API with API key authentication, rate limiting, request logging, webhook notifications, and an admin UI.

## Features

- POST `/wp-json/pagebuilder/v1/create-pages` to create multiple pages in a single request
- Custom API Key + Secret authentication (not Basic Auth)
- API key storage hashed like passwords + fingerprints for lookups
- Rate limiting (default 100 req/hour per key, configurable)
- Request logging (status, IP, UA, pages created, etc.) with CSV export
- Webhook on success with HMAC-SHA256 signature header `X-Webhook-Signature`
- Admin UI under Tools → Page Builder:
  - API Keys: generate, revoke, list, last used, request count
  - API Activity Log: filters + export CSV
  - Created Pages: list pages created via API
  - Settings: webhook URL, secret management, rate limit, API enable/disable
  - API Documentation: example cURL, request/response examples
- Uninstall option to delete all data

## Installation

1. Copy the `simple-page-builder` folder into `wp-content/plugins/`
2. Activate the plugin in WordPress Admin → Plugins
3. Go to Tools → Page Builder to generate API keys and configure settings

## Authentication

Send these headers with every request:

- `X-SPB-API-Key: <your_api_key>`
- `X-SPB-API-Secret: <your_secret_key>`

Keys are shown only once when generated. Store them safely.

## Endpoint

`POST /wp-json/pagebuilder/v1/create-pages`

### Request Body

```json
{
  "pages": [
    { "title": "About Us", "content": "<p>About...</p>", "slug": "about" },
    { "title": "Contact", "content": "Contact details..." }
  ]
}
```

### Response (201 Created)

```json
{
  "request_id": "req_abc123xyz",
  "total_created": 2,
  "pages": [
    { "id": 123, "title": "About Us", "url": "https://example.com/about" },
    { "id": 124, "title": "Contact", "url": "https://example.com/contact" }
  ]
}
```

Status codes:
- 201 Created – all pages created
- 207 Multi-Status – partial success (some failed)
- 400 Bad Request – invalid payload
- 401 Unauthorized – missing/invalid credentials
- 403 Forbidden – key revoked/expired
- 429 Too Many Requests – rate limit exceeded
- 503 Service Unavailable – API disabled

## Webhook

On success, the plugin POSTs the payload below to the configured Webhook URL. It includes an HMAC-SHA256 signature of the JSON body in the `X-Webhook-Signature` header using the secret in Settings.

```json
{
  "event": "pages_created",
  "timestamp": "2025-10-07T14:30:00Z",
  "request_id": "req_abc123xyz",
  "api_key_name": "Production Server",
  "total_pages": 2,
  "pages": [
    { "id": 123, "title": "About Us", "url": "https://example.com/about" },
    { "id": 124, "title": "Contact", "url": "https://example.com/contact" }
  ]
}
```

Retry logic: up to 2 retries with exponential backoff (1s, 2s).

## Example cURL

```bash
curl -X POST "$(wp option get siteurl)/wp-json/pagebuilder/v1/create-pages" \
  -H "Content-Type: application/json" \
  -H "X-SPB-API-Key: <your_api_key>" \
  -H "X-SPB-API-Secret: <your_secret_key>" \
  -d '{ "pages": [ {"title":"Hello","content":"World"} ] }'
```

## Postman

Import the collection at `docs/postman.json`.

## Security Notes

- Keys are stored hashed using `password_hash` and verified with `password_verify`
- Fingerprints (sha256) are used for fast lookup; plaintext not stored
- Keys can be revoked and have optional expiration
- Rate limiting per key using transients
- Webhook signature uses a separate secret stored in plugin settings

## Uninstall

Optionally delete all data on uninstall (toggle in Settings).

## License

GPL-2.0-or-later


