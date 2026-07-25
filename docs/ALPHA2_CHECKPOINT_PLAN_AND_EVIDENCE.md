# Alpha.2 Checkpoint Plan and Evidence

## Decision

The Git `draft-03-alignment` lineage was the correct source to repair. The
loose `3.0.0-alpha.1` package contained several later fixes, but it was not an
independent authoritative generation. Those changes were reconciled into Git
and frozen before current Draft-03 work began.

Alpha.2 is an additive internal generation. It does not rewrite or redirect
alpha.1, the remote Draft-02 `main`, or the unpublished Draft-03 source.

## Checkpoints

1. Complete alpha.1 reconstruction — complete
   - Commit: `28e180c`
   - Annotated tag: `tct-wordpress-v3.0.0-alpha.1-internal`
2. Isolated current Draft-03 conformance foundation — complete
   - Commit: `1a06d5a`
   - Pure code lives under `src/Draft03/`.
3. Current Draft-03 WordPress adapter and package candidate — complete
   - Commit: `74a7e7ee052a254a26a8a342a9159a92b1ce182a`
4. Disposable WordPress/PHP integration matrix — complete
   - Live-repair commits: `b1e39da` and
     `b295f341e909efc5c7c12fd393a59f78be793bc7`
   - Retained package source:
     `bfe7e8c6d2dbabe1de3ea8adf445750de406f313`
   - Matrix completed: 2026-07-26
5. Team review and internal alpha.2 freeze tag — pending
   - No external publication or merge to remote `main` is implied.

If the pinned Draft-03 source changes in a wire-affecting way, checkpoint 3 is
not silently updated. A new alpha checkpoint and complete conformance run are
required.

## Repository Evidence

Normative unpublished source:

- Revision: `draft-jurkovikj-collab-tunnel-03`
- Prepared: 2026-07-23
- SHA-256:
  `f1b2a1c9c50293c0df5936f58babb5f4fafa895510a4228ca73c71698d2a6159`
- The source hash was independently rechecked after implementation and was
  unchanged.

Acceptance commands and results:

- PHP syntax: 42 repository PHP files passed.
- PowerShell syntax: all three validation/build scripts passed parser checks.
- PHPUnit: 80 tests, 3,240 assertions passed.
- Static Draft-03 gate: 33 of 33 checks passed.
- `composer validate --strict`: passed.
- `composer audit --locked`: no vulnerability advisories.
- `git diff --check`: passed.

The tests include:

- all 24 finite and two nonfinite official RFC 8785 Appendix B rows by exact
  IEEE-754 bit pattern;
- 1,024 deterministic binary64 parity probes against Node
  `JSON.stringify`;
- Draft-03 Appendix C exact JCS body, ETag, and digest;
- UTF-16 object-member sorting and invalid UTF-8 rejection;
- M-URL and M-Sitemap schema/profile/URI rejection;
- RFC 9110 entity-tag lists, wildcard, weak comparison, and malformed input;
- identity content-coding negotiation;
- depth, node, key, string, output, source-block depth, sitemap item, and
  corrupt-cache boundaries;
- recursive Gutenberg order/no-duplication, Classic markup boundaries,
  heading document order, public exposure, and digest-backed API keys;
- exact cached M-URL ETag-to-M-Sitemap hint parity.

## Package Evidence

The clean-commit build generated two independently assembled ZIP files from
committed Git blob bytes. Their entry order, compression path, and DOS date
fields were fixed; the two ZIP byte sequences were identical.

Retained artifact:

`dist/trusted-collab-tunnel-3.0.0-alpha.2-internal.zip`

SHA-256:

`ff1c85f8630ae4b41a876a508494b2bb4338e59f66abac878081d02e3055d0dc`

Archive verification:

- 37 entries: 36 committed runtime/documentation files and one generated
  manifest.
- No `vendor/` or `tests/` entries.
- Every manifest file byte length and SHA-256 matched its archive entry.
- Manifest source commit was `bfe7e8c6d2dbabe1de3ea8adf445750de406f313`.
- Manifest and independently rechecked unpublished-draft digests matched this
  checkpoint.
- All entry DOS date fields were `1980-01-01 00:00:00`.

## Disposable Integration Evidence

Docker Desktop supplied a Linux engine. The retained ZIP was copied into,
installed with `--force`, activated, and manifest-checked in both disposable
lanes without a source-directory bind mount:

- Minimum lane: WordPress `6.0.11`, PHP `8.1.34`, Apache, and MariaDB `10.11`.
- Current lane: WordPress `7.0.2`, PHP `8.3.32`, Apache, and MariaDB `10.11`.

The minimum WordPress files were populated using the official WordPress CLI
inside the official `wordpress:php8.1-apache` runtime because no exact
`6.0.11-php8.1-apache` image tag was available. A complete bundled
Twenty Twenty-One theme was used after the downloaded Twenty Twenty-Two test
fixture was found to be incomplete. Neither condition changed plugin source.

Final retained-package live results:

- WordPress 6.0.11/PHP 8.1, pretty permalinks: 113 of 113 checks passed.
- WordPress 6.0.11/PHP 8.1, plain permalinks: 113 of 113 checks passed.
- WordPress 7.0.2/PHP 8.3, pretty permalinks: 113 of 113 checks passed.
- WordPress 7.0.2/PHP 8.3, plain permalinks: 113 of 113 checks passed.

Those checks covered root discovery, complete sitemap sampling, exact raw-byte
ETag/digest/length identity, profile and canonical links, catalog hints,
`If-None-Match` `304`, `HEAD`, `405`, identity-forbidden `406`, and advertised
`gzip` requests. The origin returned no `Content-Encoding`; the raw body,
strong ETag, digest, and length remained the identity values and responses
carried `Vary: Accept-Encoding` and `no-transform`.

Gutenberg nested-block, Classic markup, non-ASCII, attachment, category, term,
author, thumbnail, and image-alt fixtures were exercised. The complete
write-path invalidation matrix passed in both lanes against the pre-commit
candidate of the runtime later frozen at `b295f34`; no write-path logic changed
afterward. It proved:

- save and category assignment changed the M-URL and sitemap identities;
- thumbnail removal/restoration and attachment alt-text edits changed both;
- author display-name and term edits changed both;
- a relevant site-option edit incremented the cache epoch exactly once and
  changed the homepage identity;
- trash removed the item and returned `404`, restore returned `200` and
  restored the exact current hint, and permanent deletion removed it again.

WordPress 6.0 itself emits PHP 8.1 deprecation diagnostics from its legacy
Requests classes when diagnostics are forcibly displayed. The machine-route
guard prevented those bytes from contaminating a first fresh-worker machine
response. The final matrix retained `WP_DEBUG` and diagnostic logging but set
`WP_DEBUG_DISPLAY` to false, which also kept unrelated core diagnostics from
prematurely committing ordinary HTML headers. Eight repeated fresh-worker
minimum-lane root-discovery probes then passed.

## Live Repairs Confirmed by the Matrix

- WordPress `wp_magic_quotes()` slashes quoted request headers, so
  `If-None-Match` is now unslashed before the shared parser sees it.
- Plain-permalink C-URLs now map to
  `?p=<id>&tct_m_url=1` or `?page_id=<id>&tct_m_url=1` instead of appending a
  path suffix after a query string.
- Permalink-structure changes bump the Draft-03 cache epoch.
- Root and C-URL discovery headers run after WordPress query state exists and
  are suppressed on protocol-resource responses.
- The live validator hashes and decodes the original response bytes rather
  than a reconstructed .NET string.

## Remaining Gates

The disposable integration gate is closed. Alpha.2 is ready for Team review,
but no alpha.2 tag has been created by this checkpoint.

No actual CDN was selected or deployed. CDN/proxy transformation validation is
therefore a deployment-specific gate, not a claimed result of this disposable
origin matrix.

Publication remains separately gated on submitting the exact pinned Draft-03
text without a wire-affecting change, reviewing legal and public-facing
metadata, and validating the selected production origin/proxy/CDN path. This
checkpoint does not authorize publication, a merge to the older remote
Draft-02 `main`, or external distribution.
