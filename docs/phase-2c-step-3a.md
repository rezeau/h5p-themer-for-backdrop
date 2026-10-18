# Phase 2C Step 3A — isolated learner selector UI

SPDX-License-Identifier: GPL-2.0-or-later.

## Scope and baseline

This untransferred implementation starts from the 48-file Step 2B project.
The accepted active configuration baseline contains 103 files: Themer schema 2,
Core/null, explicit learner_selection_enabled false, and the two H5P maintenance
timestamps advanced to 1792245975. No live configuration is changed. Separate
external verification records contain machine-local paths and SHA-256 inventories;
those paths are deliberately not embedded in publishable documentation.

Only the disabled selector UI is implemented. No storage, account/session API,
endpoint, theme switching, CSS-variable change, H5P content edit or Phase 2D work
is included. The new control is not a completed learner theme-selection feature.

## Inspected Backdrop/H5P attachment mechanisms

Actual integration source: h5p.module::_include_h5p() emits either
.h5p-content[data-content-id] or an iframe.h5p-iframe inside .h5p-iframe-wrapper.
h5p_add_files_and_settings() populates H5PIntegration.contents and calls scripts
and styles alter hooks in div/iframe modes. DIV dependency scripts load on the
page, while iframe dependencies load inside the activity. The editor initializes
core assets through a separate path without these per-content hooks. External
h5p_embed() has its own standalone document with no Backdrop behavior bootstrap.

The new scripts-alter hook adds fixed JS/CSS to the parent Backdrop asset
collector, with native settings containing only text and option tokens. It does
not alter the dependency list passed to H5P, including external/editor lists.
Attachment requires the effective enable flag, exact supported Core API and a
non-administrative playback path. One request guard prevents duplicate settings.
No new menu routes or dependencies are introduced. Backdrop's module_implements()
persists hook discovery in the bootstrap cache. An existing cached h5p_scripts
list may omit this new implementation until a separately authorized
module_implements_reset() (or appropriate native cache maintenance) after a later
transfer. Page/layout invalidation alone does not refresh that bootstrap list.
No such cache operation was run here.

## DOM controller and accessibility

A Backdrop behavior scans the owning top-level document for real playback roots:
numeric data-content-id, matching H5PIntegration.contents entry and no excluded
editor/form/preview ancestry. Internal iframe src must remain about:blank with no
srcdoc. The behavior never reads its contentDocument or enters a child window.
For an iframe, insertion is before the wrapper; for DIV playback, before the root.
Nested activity markup cannot acquire another control. One closure-owned control
is reused/repositioned on repeated or partial behavior attachment.

The native select is disabled, unnamed, initially Site default. Its visible H5P
theme label is explicitly associated with a collision-safe stable ID; a visible
message is associated through aria-describedby. Labels/options use textContent,
not HTML. No focus call, keyboard interception, change listener or player mutation
is made. Disabling or loss of all supported players removes only this control.

One optional child-list observer detects added/removed playback/editor containers.
It ignores activity-internal mutations and its own control mutations. There is no
polling, attribute observation or frame listener. Without MutationObserver, normal
Backdrop reattachment still discovers later content. Without JavaScript, no
learner control is inserted, and the existing global renderer remains unchanged.

The stylesheet affects only learner-control descendants, using border-box, 100%
width/max-width, zero minimum width and wrapping text. It contains no fixed pixel
width, activity rules, custom-property overrides or fonts. Static/DOM tests check
these constraints; actual 375px Firefox layout and assistive technology remain
manual qualification items, not claimed automated browser results.

## Catalog and cache behavior

The existing h5p_themer_theme_catalog() supplies all choices in its existing order.
Its validated custom references retain both schemas and pinned inheritance; no
new resolver exists. Only the UI Daylight label drops the immutable catalog's
(default) qualifier. Site default, Core, built-in/custom same-ID choices remain
separate. Invalid customs stay excluded and no private admin metadata is sent.

The catalog stays uncached. Administration output snapshots combine the existing
global-runtime fingerprint and enabled UI text/options. Toggle changes and
visible catalog membership/label changes invalidate native page/layout output
once; disabled UI and non-visible color/density/description edits add no extra
invalidation. The existing global color invalidation remains effective. No live
cache clear or runtime CSS generation occurs during this isolated work.

## Verification and limitations

Complete suites: PHP 7.4.33 1,810 passed / 0 failed; PHP 8.4.22 1,810 / 0;
Node.js 22.23.1 114 / 0, with BACKDROP_SOURCE_ROOT explicitly supplied. PHP uses
native Config/Form API with memory-only configuration/cache collaborators and
private temporary filesystem fixtures; no live database bootstrap occurs. Node
runs the real module behaviors with DOM/event/observer doubles and installed
Backdrop once/collapse scripts. These are not browser/layout emulation.

No browser or live-site HTTP tests were performed. Before browser approval, check
one and multiple actual DIV/internal iframe activities, repeated navigation,
editor/preview exclusions, JavaScript off, 375px layout, long custom labels,
visible explanation, focus/keyboard behavior and unchanged answers/progress.

This stage intentionally excludes standalone external/cross-origin frames, sites
embedded within another host window, players inside forms and universal nested
embed support. Later insertion needs H5P integration data already supplied by the
host; the controller does not bootstrap AJAX H5P content. Pages initially without
an H5P render-hook invocation receive no learner assets. Cached/open pages need
normal server invalidation/reload to observe administrative changes; no live
broadcast or automatic update of already-open pages is implemented.

Transfer, browser verification, persistence and runtime switching require separate
authorization. Phase 2C remains incomplete.
