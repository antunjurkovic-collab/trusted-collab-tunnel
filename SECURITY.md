# Security Policy

## Supported Versions

| Version | Supported |
| ------- | --------- |
| 3.0.0-alpha.x | Alpha review only |
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
- This branch uses constant-time comparison for presented keys.
- Hashed/env-backed key storage is recommended before production use.

Operational notes:

- Stats and change-feed writes are disabled by default to avoid request-time DB write amplification.
- Broad substring route matching was narrowed to exact TCT route shapes.
