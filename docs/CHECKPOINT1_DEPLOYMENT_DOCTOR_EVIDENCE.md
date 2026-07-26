# Checkpoint 1 Deployment Doctor Evidence

Status: accepted and frozen by project owner

Contract: [`CACHE_INTEROPERABILITY_CONTRACT.md`](CACHE_INTEROPERABILITY_CONTRACT.md)

Plan: [`CHECKPOINT1_DEPLOYMENT_DOCTOR_IMPLEMENTATION_PLAN.md`](CHECKPOINT1_DEPLOYMENT_DOCTOR_IMPLEMENTATION_PLAN.md)

Evidence date: 2026-07-26

## Source Checkpoints

- `f9dc0b9ff74834092471eedf51afdc40f6ada5b9` — isolated bounded Doctor,
  WordPress adapter, administrator action/UI, report storage/export, and tests.
- `c37a09d18c65af239bda26a63c3f9a1edd016afc` — bounded raw-byte external
  validator and static checks.

The existing alpha.3 representations, routes, cache namespace, validators,
schemas, and settings behavior were not redirected to the Doctor.

## Scope Delivered

- internal non-stable `TCT\Compatibility\Doctor` pure orchestration;
- one-hop injected transport seam with manual same-origin redirect handling;
- `wp_safe_remote_request()` production transport;
- automatic redirect and response decompression disabled;
- original response bytes retained through coding inspection;
- fixed request, redirect, body, total-body, sample, timeout, execution, check,
  signal, text, and serialized-report ceilings;
- zero-copy JSON structural preflight before `json_decode()` allocation;
- existing Draft-03 schema and JCS certification reused without normalization;
- deterministic typed check and layer outcomes;
- diagnostic-only allowlisted WordPress/server/public-response signals;
- explicit nonce/capability-protected administrator run action;
- one-hour random owner-scoped diagnostic transient;
- compact secret-free JSON export with `no-store`; and
- raw-byte-safe external PowerShell validator.

No adapter, provider API, setting mutation, representation-cache mutation,
epoch advance, purge, automatic remediation, public diagnostic endpoint,
package, or deployment was added.

## Review Correction Made During Implementation

The review identified one pre-allocation resource gap in the plan's direct
`json_decode()` path. A hostile but body-bounded JSON response could contain
more nodes or depth than the Draft-03 JCS domain and allocate the decoded tree
before certification rejected it.

`BoundedJsonPreflight` now scans the existing byte string without constructing
a second tree. It charges JSON values, excludes object keys from node charging,
and rejects excessive logical depth, value nodes, or an impossibly large
encoded key before decoding. Public boundary tests cover:

- node limit minus one, limit, and limit plus one;
- scalar logical depth at and above the limit;
- an empty container at the maximum accepted logical depth;
- encoded-key failure; and
- malformed/unbalanced structures.

The JSON decoder receives the small extra parser-depth allowance required to
decode every structure that the inclusive Draft-03 logical-depth contract can
certify. JCS remains the final exact domain authority.

## Local Acceptance

Environment:

- Windows host;
- PHP `8.4.6`;
- PHPUnit `10.5.64`; and
- Node.js available for unchanged generated JCS parity evidence.

Commands:

```powershell
composer validate --strict --no-check-publish
composer test
& ./scripts/validate-draft03-static.ps1
```

Results:

- Composer validation passed.
- PHPUnit: **118 tests, 3,417 assertions, all passed**.
- The original **80** tests remain present and passed.
- Static acceptance: **40 checks, all passed**.
- PHP syntax checks passed for all new pure, WordPress, test, and fixture PHP
  files.
- PowerShell parser checks passed for both validation scripts.

## Boundary and Subprocess Evidence

Public tests cover:

- response-body limit minus one, limit, and limit plus one;
- gzip decoded limit minus one, limit, and limit plus one;
- aggregate-body attribution distinct from per-response attribution;
- request and redirect accounting;
- same-origin redirect success;
- cross-origin redirect fail-closed behavior;
- deterministic failure after three followed redirect hops;
- total-time `inconclusive` precedence;
- gzip transformation and weak ETag classification without UTF-8 decoding;
- invalid gzip and gzip expansion;
- noncanonical JCS;
- protected-deployment `not_tested`;
- report outcome parity for all five outcome variants;
- 128 KiB aggregate diagnostic-text enforcement;
- 2 MiB final serialized-report enforcement;
- collection count enforcement; and
- user/job report isolation.

Five fresh PHP subprocess modes passed with no panic, abort, stack overflow, or
memory exhaustion:

| Mode | PHP memory limit | Result |
| --- | ---: | --- |
| Exact 16 MiB raw response | 64 MiB | completed; byte budget accepted |
| Raw response at 16 MiB + 1 | 64 MiB | completed; typed failure |
| Gzip expansion above decoded ceiling | 32 MiB | completed; typed failure |
| Excessively deep JSON | 32 MiB | completed; preflight depth failure |
| Worst-escaping bounded diagnostic | 32 MiB | completed below 2 MiB |

The gzip, deep-JSON, and diagnostic subprocess probes were repeated under PHP
`8.1.34`; all completed successfully. PHP `8.1.34` syntax checks passed for
every new PHP file.

## Disposable WordPress Evidence

Docker Desktop Linux engine `29.5.3` supplied Apache and MariaDB `10.11`.
Source ending at `c37a09d` was mounted read-only as the plugin; no package was
built.

All checkpoint-specific containers, volumes, and the Docker network were
removed after evidence collection.

| Lane | Permalinks | Public outcome | Requests | Samples | Checks |
| --- | --- | --- | ---: | ---: | ---: |
| WordPress 6.0.11 / PHP 8.1.34 | pretty | pass | 18 | 3 | 52 |
| WordPress 6.0.11 / PHP 8.1.34 | plain | pass | 18 | 3 | 52 |
| WordPress 7.0.2 / PHP 8.3.32 | pretty | pass | 18 | 3 | 52 |
| WordPress 7.0.2 / PHP 8.3.32 | plain | pass | 18 | 3 | 52 |

Each lane used the real WordPress safe HTTP stack. The result retained:

```text
origin_implementation: not_tested
wordpress_cache_integration: not_tested
public_delivery_path: pass
```

Provider/server detection therefore did not become a compatibility pass.

### Administrator Action and Export

On the minimum lane:

- administrator login rendered the compatibility page without initiating a
  probe;
- the explicit action returned `302`;
- the redirect contained a random 32-lowercase-hex job identifier;
- the result page reported public `PASS`;
- sampled response content was absent from the HTML;
- export returned `tct-deployment-doctor-report-v1`;
- export reported public `pass`;
- export carried `no-store`;
- declared and observed export length both equalled **22,000 bytes** for the
  observed run; and
- sampled response content was absent from the JSON.

### Deliberate Single-Worker Lane

The minimum Apache lane was temporarily constrained to one
`MaxRequestWorkers`. The explicit administrator action:

- completed in **3.59 seconds**;
- reported public `INCONCLUSIVE`;
- did not report public `FAIL`; and
- displayed the equivalent external-validator command.

This proves the loopback boundary without requiring a background worker or
recurring job.

### Debug-Display Condition

WordPress 6.0 emits legacy Requests deprecation text on PHP 8.1 when
`WP_DEBUG_DISPLAY` is forced on. As in the accepted alpha.2 matrix, the final
minimum lanes retained `WP_DEBUG` but set `WP_DEBUG_DISPLAY` to false.

When display was initially forced on, unrelated core deprecation output
committed the administrator response before the post-action redirect. The
Doctor still produced bounded stored reports, but the redirect could not be
sent. This is a diagnostic development configuration, not a protocol-route or
Doctor resource failure. Checkpoint 1 does not globally suppress diagnostics
on ordinary administrator responses.

## Public Negative Evidence

The committed external validator was run read-only against the existing
`https://llmpages.org` alpha.3 installation with one sampled M-URL:

```text
schema: tct-external-validator-report-v1
requests: 10
redirects: 0
checks: 63
failed: 19
exit: 1
```

It completed without a UTF-8 exception. It continued to identify the known
public-delivery observations:

- delivered identity responses lack `no-transform`;
- gzip-advertised responses are gzip-coded;
- their ETags are weak rather than the strong identity ETags; and
- `identity;q=0` is not preserved through the observed public path.

These are end-to-end public observations. The validator does not claim that a
detected provider caused them.

The live plugin, host, LiteSpeed, Cloudflare, and WordPress settings were not
changed. The live alpha.3 installation does not contain this source-only
Doctor checkpoint.

## Known Boundaries

- Origin-only behavior is not independently observed by the runtime Doctor.
- WordPress cache-product presence is diagnostic and normally `not_tested`.
- Protected resources require a future separately reviewed ephemeral external
  credential path.
- Loopback-restricted hosts require the external validator.
- Reports expire after one hour and are marked stale on version/route mismatch.
- Provider recipes are not implemented by this checkpoint.
- At this evidence checkpoint, the alpha.3 package builder deliberately
  selected only its frozen root `includes/*.php` and `src/Draft03/*.php`
  runtime generation. The project owner subsequently authorized a separate,
  narrow alpha.4 packaging closure. That closure must package the nested
  Doctor runtime explicitly without widening protocol behavior.

## Acceptance Mapping

1. Existing representation and route tests: passed unchanged.
2. New boundary/generated/WordPress/subprocess tests: passed.
3. Isolation: additive Doctor namespace, WordPress adapter, wiring, tests, and
   validators only.
4. Adapter/purge/third-party mutation: absent.
5. Package rebuild: not performed.
6. Live-site change: not performed.
7. Source, commands, versions, results, and negative lane: recorded here.
8. Body/credential absence and deterministic bounds: tested.
9. Loopback failure: disposable `inconclusive` proof passed.

## Verdict

The project owner reviewed this packet and accepted and froze Checkpoint 1 on
2026-07-26.

The later alpha.4 packaging authorization does not authorize Checkpoint 2,
cache adapters, purges, provider mutation, shared-cache claims, or protocol
changes.
