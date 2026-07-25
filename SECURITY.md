# Security Policy

## Supported Versions

| Version | Supported |
| ------- | --------- |
| 3.0.0-alpha.2 | Internal alpha review only |
| 3.0.0-alpha.1 | Frozen internal reconstruction |
| 2.x | Legacy draft-02 implementation |

## Security Notes

This draft-03 alignment branch is not production-approved yet.

Core guidance:

- Serve M-URLs and M-Sitemaps over HTTPS.
- If a C-URL requires authentication, protect the corresponding M-URL similarly.
- Do not expose sensitive, paywalled, embargoed, or private content through public mode.
- Treat policy descriptors, usage receipts, stats, changes, and llms.txt as non-core deployment extensions.
- Do not store secrets in repository files.

Receipt extension:

- `AI-Usage-Receipt` is optional and disabled by default.
- `X-AI-Contract` is accepted only when it matches the narrow grammar `[A-Za-z0-9._:-]{1,128}`.
- Receipt HMAC keys should be random, at least 32 bytes, and rotated if compromised.

API key extension:

- API keys should be generated randomly and transmitted only over HTTPS.
- Presented keys are SHA-256 hashed before constant-time digest comparison.
- Persistent configuration contains lowercase SHA-256 digests only.
- Comma-separated plaintext runtime keys can be supplied with `TCT_API_KEYS`;
  environment configuration remains the deployer's secret-management
  responsibility.
- Legacy `tct_api_keys` plaintext option values are not consulted by alpha.2.

Operational notes:

- Stats and change-feed writes are disabled by default to avoid request-time DB write amplification.
- Broad substring route matching was narrowed to exact TCT route shapes.
- One default exposure policy is shared by endpoints, catalogs, and discovery
  links. Access-control plugins must impose any stronger site-specific rule
  through `tct_post_is_exposable`.
- JCS depth, nodes, key bytes, string bytes, and total identity bytes are
  bounded. Sitemap item count, query size, and response bytes are bounded.
- Receipt secrets are accepted only from `TCT_RECEIPT_HMAC_KEY` (or an
  explicit deployment filter), never from the admin form. Receipt emission
  fails closed without a key of at least 32 bytes.
