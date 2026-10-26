# Checkpoint 3B Stage 1 â€” production activation and multi-player lifecycle

**Current status annotation:** Checkpoint 3B Firefox acceptance is complete. The original stage-specific observations and pending statements below are retained as history. Current development activation uses strict boolean `h5p_themer_native_density_enabled`; the temporary Checkpoint 2B content-ID gate is historical. v0.4.0 remains unreleased. The later Checkpoint 3C Lite superseded the full Stage 2/3 plan and passed 46 focused assertions and all seven manual Firefox tests. A separate actual module disable/re-enable verification passed 85 checks without uninstalling. Full code rollback was not rehearsed. See [current readiness](v0.4.0-readiness.md) and [installation](installation.md).

Repository implementation and automated tests only. No deployment, candidate clone,
HTTP/browser test, site bootstrap, site cache operation, database connection or
configuration/schema migration is part of this stage. Checkpoint 3C wording,
metadata, README and preview changes are deliberately deferred.

## Activation

Proposed syntax for a separately authorized candidate installation only:

```php
$settings['h5p_themer_native_density_enabled'] = TRUE;
```

Only boolean TRUE enables this feature. Absent, FALSE, 1, strings, arrays and NULL
leave it disabled. The former `h5p_themer_native_density_content_ids` setting is no
longer consulted by repository code; it does not silently enable production
Density. No settings.php was edited. The accepted Checkpoint 2B installation still
runs its unchanged deployed controller and its original content-6 gate.

Policy D combines an internal exact-version qualification tuple, proven top-level
player ownership and effective theme profiles. Current qualification is only
H5P.TrueFalse 1.8.24 with H5P Core API1.28. Future versions require explicit registry,
matching runtime identity/adapter changes and their own browser qualification;
there is no public registry editor or generic compatibility inference.

The existing script-alter hook still handles learner/color attachment. Density
attachment moves to hook_node_view, after native H5P content building. This gives
an actual rendered main node rather than a dependency list. The content ID comes
from h5p_get_content_id (including native revisioning semantics). A fresh H5PBackdrop
API object performs read-only loadContent/loadLibrary calls; no Core services are
constructed. Content ID, main library name/major/minor/library ID, installed patch,
actual resolved iframe mode and library development-mode guards must agree.
Missing/unavailable/untrusted metadata fails closed before theme catalog work.
The API's installed patch is authoritative; browser libraryInfo lacks that field.
Content-authored dependencies and node/client version claims are never sufficient.

Parent metadata uses protocol version2: one atomic JSON theme payload plus a
Backdrop-merged players map keyed `cid-ID`. Each entry includes ID, exact qualified
library and patch24. The JS still proves iframe/realm/root/container/instance
ownership independently. Invalid individual entries do not disable valid siblings.
Shared JS/payload attaches once, distinct player records merge separately. Duplicate
content embeddings are deliberately rejected in the browser, even across distinct
iframe realms. The mutable lifecycle record is keyed by the full actual frame,
document, root, container, API and instance tuple, not by content ID alone.

## Preserved behavior

Custom applies its saved native profile; Core and immutable built-ins restore the
original; Site default follows effective Global Default (including hidden custom
Default), or restores original. Hidden optional themes stay absent from the existing
selector. No new selector, CSS variables, spacing rules, stored Density changes,
color CSS/hash/key changes, iframe dependencies or H5P/library changes.

Attachment considers all selectable custom profiles, even from an initial Core
selection. Global-only mode avoids the learner catalog. With no effective global
or selectable override, no Density payload/controller is necessary. Unsupported
nodes return before catalog/metadata computation.

## Lifecycle and resize

Each player has independent original/last profile, pending frame/height/epoch,
failed-owner state and child-document load/attribute observers. Frame jobs coalesce
and target only the verified native instance. Effective changes notify native
Question layout even if initial height is equal; identical/color-only/net-zero
changes do not resize. A failing owner restores its profile where provable and
is not retried; siblings continue. Activity instances, answers, scores and feedback
are never recreated or mutated by this controller.

Repeated attach reuses records/listeners. unload/move detach cancels pending work,
restores only a still-owned profile, releases child listeners/observers and marks
partial-context frames in a WeakSet until explicit reattachment. serialize detach
is a no-op. Document detach removes all native page listeners/observers. DOM
removal cleanup is idempotent with detach. A detached but still uniquely owned
iframe can have its original class restored without resize, preventing our old
override from becoming the original after reinsertion. Competing instance ownership
prevents restoration writes; unrelated/replacement documents are not touched.

The tree observer watches direct children only along known iframe ancestor chains
and body; it has no subtree:true observation. Relevant load/native initialized
signals and Backdrop attach discover late players. There are no polling timers,
new animations, scroll/focus calls or broadcast resize. Full page navigation lets
the browser dispose the realm; pageshow revalidates. AJAX insertion should use the
normal Backdrop attachment contract, especially into previously unobserved nested
containers; silent H5P registry mutation without events is not supported.

## Caches and delivery

The existing combined administration cache dependency now reads the new metadata
shape and includes enable policy, registry and installed library qualification.
Density-only effective changes invalidate stale HTML independently of color hashes.
No-op and hidden-unselected edits remain neutral. Controller stays on the parent
page; native H5P iframe assets are untouched. Installation switch/library policy
changes require relevant output/JS cache refresh and page reload in the later
candidate stage. This is not an immediate remote kill switch; old open pages retain
their server-supplied metadata. Asset aggregation and new node_view delivery still
require real Backdrop/Firefox verification.

## Verification and source-only test boundaries

Existing suites include real Backdrop Config/Form definitions but replace site
storage/services with memory collaborators and private temporary directories. No
site settings/bootstrap or database connection is loaded. API qualification uses
a narrow H5PBackdrop double matching the inspected installed read API. Node uses
these PHP exports plus DOM doubles, not a browser. Full source read access is needed
because the sandbox can report existing Core CSS unreadable; escalation changes
access permissions, not the fixture/site boundary. Test artifacts and baseline
copies/hashes are ignored under artifacts/native-density-checkpoint3b-stage1/.

PHP7.4.33,8.3.18,8.4.22 regression logs are recorded there. The seven standalone
Checkpoint1 files must retain their accepted hashes. No full release or browser
qualification is implied by these unit/regression results.

## Stage 2 proposal â€” separate authorization required

Do not reuse or alter the accepted site at port8094. Create an independently backed
up disposable candidate only when authorized: its own root, DB, configuration,
private storage, loopback origin and outbound guards. Verify copied source/library
versions and disable activation first. Back up module/settings/config/DB before
candidate fixture edits; record candidate-only restoration commands.

Render two distinct TrueFalse1.8.24 nodes on one parent page through normal Backdrop
node view rendering, so each passes the new server qualification hook. Start from
Core to demonstrate later custom selection. Use same-color custom Small/Medium/
Large themes, a hidden Medium Global Default, selectable Core/built-ins and optional
learner-disabled global mode. Verify each player's original independently with a
controlled candidate-only initialization fixture; never patch Core/library sources.

Unsupported control: prefer a real MultiChoice1.16.27 already present in the chosen
candidate source. If unavailable, preparing/importing that local fixture needs
separate candidate-only authorization; do not alter another installation to obtain
it. Also test an external embed, a DIV negative fixture and duplicate-content
rejection, without representing those modes as supported. Preserve normal native
playback and existing colors on all controls.

Firefox acceptance: one native class per eligible root; visible Small/Medium/Large;
answers/feedback/score and Retry survive selection; originals restore for Core;
Site default follows hidden Medium; color changes still work; no effect on control;
remove/replace/reinsert one player while sibling stays functional; no stale resize,
listener growth, clipping, console/PHP errors or missing assets. Test aggregation
on/off, keyboard use and disable/cache-refresh/reload. Do not invent a URL before
candidate creation and actual reachability verification.

Rollback: candidate-only disable + relevant cache refresh/reload, or restore its
module/settings backups and refresh caches. Theme Density values remain stored.
Restore candidate config/DB only to undo its test fixtures, never against another
site. No reverse schema migration is needed. Stop for explicit Firefox PASS/FAIL;
no commits, pushes, tags, releases or Checkpoint3C work without later authorization.

## Stage 1 final results

- PHP7.4.33: 3739 passed,0 failed.
- PHP8.3.18: 3739 passed,0 failed.
- PHP8.4.22: 3739 passed,0 failed.
- Node22.23.1: 470 tests passed,0 failed; focused Density integration64passed.
- PHP syntax: all five changed PHP/module files under all three PHP versions.
- JS syntax: production controller and changed integration tests.
- Tracked diff and untracked-file whitespace checks passed (CRLF recognized).
- Seven Checkpoint1 hashes unchanged;673 recorded site files unchanged.

An initial sandbox-only PHP run failed in an existing color stylesheet filesystem
check; the full-access matrix passed without changing color implementation.
Intermediate new-test/expectation failures were corrected before the final runs.
No unresolved or pre-existing code-suite failure remains in the final matrix.
Real Backdrop delivery and Firefox testing are still pending, not simulated passes.
