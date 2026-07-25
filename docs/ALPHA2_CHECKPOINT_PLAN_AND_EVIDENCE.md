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
4. Disposable WordPress/PHP integration matrix — pending
   - Required before alpha.2 can be frozen as an install-tested internal
     reference.
5. Team review and internal alpha.2 freeze tag — pending
   - No external publication or merge to remote `main` is implied.

If the pinned Draft-03 source changes in a wire-affecting way, checkpoint 3 is
not silently updated. A new alpha checkpoint and complete conformance run are
required.

## Checkpoint 3 Evidence

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
- PHPUnit: 78 tests, 3,237 assertions passed.
- Static Draft-03 gate: 30 of 30 checks passed.
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

`3d28f75a4824b216674664d5ecad16e4ffde966b09324b14c95e6df6ec9071f3`

Archive verification:

- 37 entries: 36 committed runtime/documentation files and one generated
  manifest.
- No `vendor/` or `tests/` entries.
- Every manifest file byte length and SHA-256 matched its archive entry.
- Manifest source commit and unpublished draft digest matched this checkpoint.
- All entry DOS date fields were `1980-01-01 00:00:00`.

## Open Evidence Gate

No live WordPress result is claimed. Two disposable validation mechanisms were
attempted:

- the exact WordPress Playground CLI installation stalled without producing a
  runnable process;
- Docker Desktop could not expose its engine because its WSL distribution
  reported a failed local drive mount.

Both attempts were stopped and cleaned up. This is an environment/evidence
blocker, not evidence of a plugin failure, but it remains a real gate:

- install and activate the retained package on disposable WordPress;
- exercise pretty and plain permalink modes;
- run PHP 8.1 and a newer supported PHP version;
- run `scripts/validate-live.ps1`;
- verify origin-server/CDN compression does not transform identity responses;
- test save, term, image-alt, thumbnail, author, trash, restore, delete, and
  option invalidation;
- repeat on the intended minimum and current WordPress versions.

Only after that matrix is green should Team review create an internal alpha.2
freeze tag. Publication still requires a separate decision after the exact
Draft-03 text is submitted and legal/public-facing metadata is reviewed.
