# Alpha.6 Cross-Host Deployment Evidence Consolidation

Status: documentation-only consolidation; mandatory review stop

Recorded: 2026-07-29

Evidence base:
`90490b53a3009e13342227517da5a3f7a28be597`

## 1. Purpose

This document provides one generation-aware index of the public and
controlled deployment evidence collected for the TCT Draft-03 WordPress
reference implementation.

It distinguishes:

- exact Alpha.6 tests from historical Alpha.5 observations;
- protocol or routing passes from complete deployment-support claims;
- controlled local evidence from Internet-facing public evidence; and
- one exact host/configuration result from a provider-wide statement.

No new host test was performed for this consolidation. Historical Alpha.5
results are not promoted to Alpha.6 compatibility claims.

This evidence file is outside the deterministic package allowlist. No Alpha.6
packaged input changes, and the retained ZIP remains pinned to its recorded
source commit. Rebuilding or replacing that artifact from this later
evidence-overlay commit is not authorized.

## 2. Alpha.6 Identity

The Alpha.6 evidence uses:

```text
plugin version:
3.0.0-alpha.6

package source commit:
817f1bc26d557d91b0f28990f418fa1c2a4186ac

artifact:
trusted-collab-tunnel-3.0.0-alpha.6.zip

artifact bytes:
99,356

artifact SHA-256:
387659f8996e3051dd43d6caacdbaa0e92d416e02c9d43a89d70c97a81b3bf42

package entries:
64

published Draft-03 source SHA-256:
d106c6b10fad897b434834d74682bf093e66a0a5aea1dbf116691e301ef692fd
```

Alpha.6 changes the WordPress URL-path-prefix adapter and external-validator
base-path reporting. It does not change Draft-03 JSON, profiles,
canonicalization, identity, validators, cache behavior, response negotiation,
exposure policy, or the `tct_v03_alpha2` internal cache namespace.

## 3. How to Read the Results

A validator `PASS` describes the exact selected resources, request matrix,
deployment state, vantage, and time. It is not a permanent host guarantee.

The cache interoperability classifications apply only to exact evidence
lanes:

- **Verified** requires complete current disposable or public-path evidence;
- **Guided** requires a reviewed versioned recipe;
- **Unknown** means no current compatibility claim; and
- **Incompatible** means the tested configuration did not preserve the
  selected-representation and validator contract.

No current lane is **Supported by adapter**. The plugin does not configure,
purge, or repair an external cache, proxy, CDN, server, or host.

## 4. Exact Alpha.6 Evidence

| Lane | Vantages and coverage | Recorded outcome | Bounded interpretation |
| --- | --- | --- | --- |
| Controlled Apache, root-hosted | Exact ZIP; external validator; WordPress 7.0.2/PHP 8.3; pretty routes | `97/97` checks passed | Controlled protocol and adapter pass without a later cache or CDN |
| Controlled Apache, `/subsite` | Exact ZIP; external validator; pretty and query M-URLs; base-path reporting | `97/97` checks passed | Alpha.5 path-prefix blocker is repaired in the controlled Alpha.6 generation |
| WordPress Playground CLI | Exact ZIP; Playground CLI 3.1.47/PHP 8.3; external validator | `97/97` checks passed | PHP.wasm, extraction, and root-hosted routing pass; this is not durable browser-origin evidence |
| Controlled W3TC 2.10.3, pretty permalinks | External validator plus Doctor; cold/warm cache; lifecycle; custom routes; epoch; large response; restart | Strict Disk Enhanced configuration passed `97/97`; Doctor passed `52/52` | Passing controlled candidate for a future Guided recipe; default and unexcluded configurations are not compatible |
| Controlled W3TC 2.10.3, plain permalinks | Disk Basic with query caching; external validator plus Doctor; lifecycle and restart | Strict configuration passed `97/97`; Doctor passed `52/52` | Passing controlled candidate; W3TC rejects Disk Enhanced with default plain permalinks |
| Public Namecheap/LiteSpeed plus W3TC 2.10.3 | Direct DNS-only hostname; external validator plus Doctor; cold/warm cache; lifecycle; 84,872-byte M-URL; ordinary-page cache proof | Baseline, cold, warm, and final external matrices passed `97/97`; Doctor passed `52/52` | Passing exact public deployment and candidate manual recipe; not a Namecheap, LiteSpeed, or W3TC provider-wide claim |
| Public Wasmer64 small-state snapshot | Standalone general validator, Alpha.6 external validator, and owner-reported Doctor; three small M-URL samples | General validator `24/24`; external validator `97/97`; Doctor reported pass | Valid timestamped Alpha.6 positive snapshot, but insufficient for a durable Wasmer deployment-support classification |

Authoritative detail:

- [`ALPHA6_PATH_PREFIX_REPAIR_PLAN_AND_EVIDENCE.md`](ALPHA6_PATH_PREFIX_REPAIR_PLAN_AND_EVIDENCE.md)
- [`W3TC_ALPHA6_CONTROLLED_DOCKER_EVIDENCE.md`](W3TC_ALPHA6_CONTROLLED_DOCKER_EVIDENCE.md)
- [`NAMECHEAP_W3TC_ALPHA6_PUBLIC_EVIDENCE.md`](NAMECHEAP_W3TC_ALPHA6_PUBLIC_EVIDENCE.md)
- validator Milestone 2 at
  `e2ae543206dfba7066edba1f260b514425cd8fb2`

## 5. Wasmer Generation Boundary

The Alpha.6 Wasmer64 observation does not erase the Alpha.5
larger-representation counterexample on the same public hostname.

Alpha.5 initially passed in a small three-item state. A disposable lifecycle
then caused larger fresh JSON responses to be gzip-coded despite
`no-transform`. The external validator failed seven advertised-gzip checks.
After deleting the larger post, the small baseline passed again.

The Alpha.6 positive observation selected the restored small baseline with the
same recorded M-Sitemap and M-URL body identities. Alpha.6 did not change
delivery transformation or compression diagnostics. No Alpha.6 large-payload
lifecycle was executed on Wasmer.

The WordPress Doctor and packaged external validator also retain the known
one-selected-M-URL advertised-gzip sampling boundary documented during the
Alpha.5 lifecycle. A small-state pass must not be presented as proof that
larger sampled representations remain uncoded.

Therefore:

- the recorded Alpha.6 snapshot remains a genuine `PASS`;
- Wasmer64 remains **Unknown** for a durable deployment-support claim; and
- the Alpha.5 counterexample remains relevant until an Alpha.6 large-response
  matrix independently closes it.

Historical authority:
[`WASMER_ALPHA5_PUBLIC_DELIVERY_EVIDENCE.md`](WASMER_ALPHA5_PUBLIC_DELIVERY_EVIDENCE.md).

## 6. Historical Alpha.5 Host Evidence

These observations remain useful delivery-envelope evidence because Alpha.6
did not change the affected HTTP identity or transformation behavior. They do
not classify an untested Alpha.6 deployment.

| Lane actually tested | Alpha.5 outcome | Relevance to Alpha.6 | Current Alpha.6 classification |
| --- | --- | --- | --- |
| `llmpages.org` through LiteSpeed, Cloudflare, and the existing Worker | Doctor failed; external validator failed `21/97` due to missing `no-transform`, coded gzip responses, weakened coded-response ETags, and failure to preserve `identity;q=0` | Production apex remains a confounded CDN/Worker lane, not a clean reference path | **Unknown**; no Alpha.6 rerun recorded |
| Pantheon development URL | Doctor failed before and after cache clearing; external validator failed `18/97` | Weak public ETags, gzip coding, and identity-prohibition behavior are outside the Alpha.6 routing repair | **Unknown**; Alpha.5 exact lane remains **Incompatible** |
| TasteWP disposable URL | Doctor failed; external validator failed `28/97` | Weak ETags, missing length, gzip coding, and identity-prohibition behavior are outside the routing repair | **Unknown**; Alpha.5 exact lane remains **Incompatible** |
| InfinityFree Free with W3TC enabled | Site-initiated Doctor failed before M-URL certification | Demonstrates that cache-plugin detection or activation is not compatibility evidence | **Unknown**; Alpha.5 exact enabled configuration remains **Incompatible** |
| InfinityFree Free with W3TC disabled | Site-initiated Doctor passed, but independent external validator failed `21/29` after receiving a JavaScript/cookie challenge | Demonstrates why both site-initiated and independent external vantages are required | **Unknown**; Alpha.5 external automated-client lane remains **Incompatible** |
| Temporary browser Playground scope | Homepage resources passed, two path-prefixed non-home pretty M-URLs returned `404`; browser Fetch could not reliably send `Accept-Encoding` | The adapter defect is repaired by Alpha.6 controlled `/subsite` and Playground CLI evidence; temporary browser scopes are not durable public origins | Not a provider compatibility classification |

Historical authority:

- [`ALPHA5_PUBLICATION_ALIGNMENT_PLAN_AND_EVIDENCE.md`](ALPHA5_PUBLICATION_ALIGNMENT_PLAN_AND_EVIDENCE.md)
- [`PANTHEON_ALPHA5_PUBLIC_DELIVERY_EVIDENCE.md`](PANTHEON_ALPHA5_PUBLIC_DELIVERY_EVIDENCE.md)
- [`TASTEWP_ALPHA5_PUBLIC_DELIVERY_EVIDENCE.md`](TASTEWP_ALPHA5_PUBLIC_DELIVERY_EVIDENCE.md)
- [`INFINITYFREE_W3TC_ALPHA5_PUBLIC_DELIVERY_EVIDENCE.md`](INFINITYFREE_W3TC_ALPHA5_PUBLIC_DELIVERY_EVIDENCE.md)
- [`PLAYGROUND_AND_PATH_PREFIX_ALPHA5_EVIDENCE.md`](PLAYGROUND_AND_PATH_PREFIX_ALPHA5_EVIDENCE.md)

## 7. Consolidated Verdict

The accumulated evidence supports these conclusions:

1. The Alpha.6 package can implement and serve TCT Draft-03 correctly when
   the selected delivery path preserves its responses.
2. Alpha.6's path-prefix repair is supported by root, `/subsite`, query-form,
   and Playground CLI evidence.
3. W3TC 2.10.3 is not inherently incompatible, but its default Browser Cache
   and unexcluded Disk Enhanced behavior break the tested TCT lane.
4. One strict W3TC configuration passes comprehensively in controlled Docker,
   and one host-compatible strict configuration passes on the exact public
   Namecheap test installation.
5. Host caches, response transformers, CDNs, Workers, compression, and browser
   challenges remain independent deployment variables. Plugin source tests
   cannot prove those layers compatible.
6. Universal installation cannot mean universal end-to-end conformance.
   Every selected deployment still requires both the site-initiated Doctor
   and independent external validation after relevant configuration changes.

No Alpha.7 protocol or routing repair is justified by the consolidated
evidence. The known compression-diagnostic sampling boundary remains a
separate publication decision, not an authorized implementation slice.

## 8. Publication-Relevant Gaps

Before an Alpha.6 public experimental prerelease is announced, a separately
reviewed publication checkpoint should:

1. reconcile the internal Alpha.6 generation with the currently published
   GitHub repository and release history;
2. review repository visibility, branch/tag naming, license, package
   provenance, and the retained ZIP;
3. publish a bounded deployment-envelope guide rather than a universal host
   claim;
4. decide whether the exact W3TC 2.10.3 recipe is ready to become a reviewed
   manual Guided recipe;
5. decide whether to publish Alpha.6 with its one-selected-M-URL compression
   probe plainly disclosed or require a separately versioned diagnostic
   correction first;
6. document that Doctor and external-validator results are timestamped and
   become stale after relevant delivery-path changes;
7. retain Alpha.5 negative observations as historical evidence without
   advertising them as current Alpha.6 provider results; and
8. keep the Cloudflare Worker, cache adapters, automatic purging, and provider
   configuration outside the Alpha.6 release boundary.

Retesting every previously observed free/disposable host is not a prerequisite
for an experimental prerelease. Those hosts remain unclaimed unless a new
versioned evidence lane is explicitly opened.

## 9. Scope and Stop

This checkpoint changes documentation only. It does not authorize:

- plugin, package, validator, Worker, draft, host, DNS, CDN, or cache changes;
- a provider-wide compatibility or incompatibility claim;
- a W3TC adapter or automatic settings mutation;
- an Alpha.7 generation;
- WordPress.org submission;
- publication, pushing, tagging, prerelease creation, or deployment; or
- another product milestone.

Stop for review of the generation boundaries, matrix, classifications, and
publication-relevant gaps.
