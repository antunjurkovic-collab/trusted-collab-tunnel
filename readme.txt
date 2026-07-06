=== Trusted Collaboration Tunnel ===
Contributors: antunjurkovic
Tags: ai, llm, json, etag, sitemap
Requires at least: 5.0
Requires PHP: 7.4
Tested up to: 6.8
Stable tag: 3.0.0-alpha.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Draft-03 alpha WordPress reference implementation for the Collaboration Content Transfer (TCT) protocol.

== Description ==

This branch aligns the WordPress reference plugin toward draft-jurkovikj-collab-tunnel-03 internal.

It exposes machine-facing JSON representations for selected WordPress content, a JSON M-Sitemap, strong ETag validators, Content-Digest integrity headers, and conditional GET support.

This is an alpha reference branch, not a production-grade release.

== Draft-03 Notes ==

* M-URL JSON uses profile `tct-1`.
* M-Sitemap uses `lastModified` and advisory unquoted `etag` values.
* M-URL response bytes and strong ETag hashing use the same deterministic JSON encoder.
* Policy, receipts, stats, changes, and llms.txt are non-core deployment extensions.

== Changelog ==

= 3.0.0-alpha.1 =
* Draft-03 alignment workspace.
* Adds profile discovery parameter.
* Renames sitemap advisory timestamp to `lastModified`.
* Sends canonical JSON bytes for M-URL responses and ETag hashing.
* Narrows TCT route matching.
* Disables stats/change-feed writes by default.
* Sanitizes receipt contract identifiers.
