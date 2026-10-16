# Phase 2A Step 2 — isolated global color runtime

**Historical development record.** This describes the evidence and scope at the original checkpoint, not the current deployment state. See [installation](installation.md), [administration](administration.md) and the [current roadmap](roadmap.md) for present guidance.

SPDX-License-Identifier: GPL-2.0-or-later. Implemented/tested on 2026-10-16.
At the isolated checkpoint described below, no live transfer or browser test
had occurred. Global runtime was subsequently transferred; current implementation
and manual verification status are in the README.

## Isolation

A timestamped ordinary working directory outside the live-linked project was
used for implementation. No personal project or temporary paths are published.

The timestamped destination did not exist. All 31 accepted Step 2C files matched
the prior verification JSON and were copied with identical SHA-256 hashes. The
copy contains no junctions, symlinks or Git metadata. The active module junction
continues to point to the untouched original project.

Source baseline and all 731 H5P integration-file hashes are recorded beside the
copy in the -source-baseline.json file. Final copied-file hashes and the exact
file delta are recorded separately in -verification.json. No audit artifacts are
added to the live-linked project. Config schemas, shipped defaults and exact
preset definitions are unchanged.

## Implementation

h5p_themer_h5p_styles_alter() permits only div, iframe and external modes. It uses
the existing strict API-1.28 compatibility check and effective selection resolver.
It neither initializes H5P services nor edits content/settings. Core/null and
empty sparse palettes return before storage lookup. No editor/runtime script
hook or new route is introduced.

The pure serializer emits only fixed .h5p-content declarations in the existing
33-property order. Exact custom hex case/shorthand survives. Built-ins must match
their immutable catalog map. Inherited non-hex values must exactly match the
identified catalog property/version. An unsupported catalog-only property is
excluded only with exact known provenance. Unknown properties/expressions fail.
There is no label/ID/selector/URL interpolation, derived palette or density output.

Schema 1 remains sparse; schema 2 combines the exact pinned base with sparse
validated overrides without writing a flattened palette. Existing configuration
overrides and validation remain authoritative. No catalog or schema changes occur.

## Generated CSS

The local public wrapper supplies physical storage and relative browser mapping.
Only browser-safe relative public-directory segments are accepted. Absolute,
remote, traversal and unsafe paths fail closed. Files are written only beneath
the physical public root's h5p_themer directory; the styles object receives a
normal relative path, never a public:// URI.

Names are colors-v1-<sha256-of-CSS>.css. Bump the serializer prefix if changing its
contract. Hashes in real filenames accommodate DIV's ignored version field and
Backdrop's filename-based aggregate keys. The existing H5P dependency order is
preserved; the global stylesheet is appended and deduplicated by exact path.

An exact existing regular file is reused without writes, even if the directory
is subsequently read-only. Unexpected contents, directories and links are not
repaired or overwritten. Creation uses a nonblocking advisory filesystem lock,
verified private temporary bytes, and same-directory rename. A small .write.lock
file persists in the cache directory. File publication uses no database lock or
managed-file record. Lock contention or filesystem failure does not block H5P
playback or attach partial data; later requests retry. Temporary files are cleaned
in finally blocks where possible. These guarantees coordinate this module's
writers, not arbitrary noncooperating filesystem tampering.

No generated CSS is written to the live site during this implementation. Tests
provide a separate private physical mapping for the otherwise identical APIs.

## Cache behavior

Pure before/after snapshots include runtime selection identity and serialized
colors, excluding labels/density. The two successful administration save paths
invoke invalidation after persistence. Native cache_clear_all() with default
arguments flushes page and layout_path bins, not CSS/JS assets. Failed, locked,
CSRF-rejected, unchanged, metadata-only and unselected saves do not invalidate
runtime page references. Returning to Core invalidates prior themed HTML.

Config::save() alone does not invoke config-update/import hooks. Standard single
and bulk import workflows have their own native final cache flushes; raw direct
programmatic or filesystem changes remain caller-managed. No import semantics
were altered. Already open players and independently cached external/CDN pages
need normal reload/invalidation; no hot theme switching is implemented.

## Tests and results

Before edits, PHP 7.4.33/8.4.22 each passed 1,141 checks and Node passed 35.
After implementation:

| Runtime | Result |
| --- | --- |
| PHP 7.4.33 | 1,268 passed, 0 failed, exit 0 |
| PHP 8.4.22 | 1,268 passed, 0 failed, exit 0 |
| Node 22.23.1 | 35 passed, 0 failed, exit 0 |

Historical commands from the isolated directory (replace placeholders and set
BACKDROP_SOURCE_ROOT as documented in the README for current Node runs):

    & '<php-7.4-executable>' -n tests\run.php --backdrop-root=<Backdrop-source-root> --h5p-root=<H5P-module-source-root>
    & '<php-8.4-executable>' -n tests\run.php --backdrop-root=<Backdrop-source-root> --h5p-root=<H5P-module-source-root>
    node --test --test-reporter=spec tests\js\admin-colors.test.cjs

PHP uses native Config/import/Form API definitions with in-memory services and
cache invalidation counters. Public storage is mapped exclusively to private
temporary fixtures, cleaned afterwards. Native OS lock contention, two independent
PHP publication processes, verified immutable reuse, corrupt files, path rejection,
read-only/occupied storage and exceptions are exercised. Native H5PCore asset URL
prefixing is exercised without constructing H5P services or a site bootstrap.
No SQL connection, live cache clear or live configuration write is performed.

The shell sandbox denied a temporary-file rename. Full filesystem tests were run
with normal local filesystem access and the same private mappings. This is not
a reason to bypass permissions in production: inaccessible storage fails closed.
Node tests remain the existing administration DOM/event-double suite; no runtime
JavaScript was added. Automated results do not establish Firefox visual PASS.

## Compatibility and browser gates

API 1.28 supplies theme variables but consumption varies by library/version.
Installed GuessIt 1.9, DragTextPapiJo 1.3, AccordionPapiJo 1.2,
AdvancedBlanksPapiJo 1.4 and DialogcardsPapiJo 1.17 contain variable consumers.
Several older/other libraries have none in their own assets. Do not force
h5p-theme classes or rewrite authored/hardcoded styles to claim universal support.
Editor mode is excluded; mixed authoring/playback, nested activities, fullscreen,
portals and actual browser expression support require qualification.

This adapter checks iframe aggregation via legacy system.performance keys while
Backdrop's stored performance settings use system.core. Its existing behavior was
not modified. Test both source branches with doubles and qualify actual runtime
asset order/aggregation; do not assume the performance UI describes iframe behavior.

After separate transfer authorization, first verify one existing variable-aware
activity in Firefox: identify its URL/library/mode, record Core/null, select Mint,
reload, inspect the iframe's stylesheet and computed CTA value #187571, then check
visible controls, feedback/fullscreen/resize. Re-save a selected custom color and
verify warm-cache changes. Return to Core, reload and verify no Themer attachment.
Then expand to DIV, external embed, multiple/nested players and authoring boundaries.
No authored content should be saved merely to test color application.

## Recovery and stop boundary

Normal rollback: select Core through administration, let native page/layout cache
invalidation complete and reload activity pages. If runtime source recovery is
needed, restore the separately authorized transfer's pre-runtime source snapshot
and perform separately authorized cache invalidation. Do not restore the database
or rewrite content: schemas/catalogs/authored data were not migrated. Leave old
immutable files until separately planned cleanup to avoid breaking stale references.

GO for separately authorized controlled transfer and subsequent pilot verification.
This working copy is not transferred. Density, Theme Preview, learner/per-content
controls and H5P integration modifications are not implemented.
