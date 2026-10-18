# Phase 2C Step 6B: compatibility-based learner selector

SPDX-License-Identifier: GPL-3.0-or-later.

## Status and scope

Implemented only in an ordinary, unlinked isolated copy of the verified Step 5D
63-file project, excluding Git metadata. Controlled transfer and Firefox testing
are separate pending checkpoints. Phase 2C is not complete; Phase 2D is untouched.
No live source, database, configuration, cache or content writes are performed.

The only production changes are `js/h5p_themer.learner.js` and
`h5p_themer.learner-runtime.inc`. Catalog resolution, colors, palette-key mappings,
presets, configuration schemas and iframe identity checks are unchanged.

## Positive evidence and reversible states

The controller inspects each registered native player's own H5P content root.
Exactly one direct child with class `h5p-container` must exist. Its library-owned
`h5p-theme` class is positive evidence; nested/unrelated markers, generic H5P
markup, API version, catalog metadata and the generic bundle marker are not.

- SUPPORTED: that container has the marker and the matching parent/player
  stylesheet readiness markers are present.
- PENDING: the container/document is incomplete, ambiguous or not yet ready.
- UNSUPPORTED: the currently inspected container has no recognized theme marker.

UNSUPPORTED is not a permanent result. Direct-root and container-class observers
remain active, so an unmarked container can later qualify without reattachment.
No hard-coded library list, runtime CSS scanning, polling or timeout is added.
This is conservative detection, not certification of every library's visuals.

## Selector and mixed-player isolation

No eligible ready activity means no selector. One or many eligible activities
share one native selector placed above the first eligible player. Incompatible
players do not determine placement. Removing the last eligible player removes
the selector and applied learner attributes, while keeping discovery active.

The fixed `data-h5p-themer-eligible` attribute records classification on a content
root; its presence is never read as capability evidence. Both learner palette
rules and the inherited-Core neutralization rule require this attribute.

The canonical administrator-global rule covers all roots when no learner choice
is active, and ineligible roots when an explicit learner choice is active. Thus
an unsupported DIV sibling retains its normal global palette while an eligible
DIV uses explicit Core, a built-in or a custom theme. An unsupported iframe never
receives the learner attribute, and keeps normal global styling. Palette values,
Core/Daylight distinction and pinned/sparse custom inheritance are unchanged.

Initial pending activity/style readiness does not expose an unusable control.
The existing polite status reports applied/partial support only once a control
exists. There is no promise of zero rendering flash or universal recoloring.

## Observation, ownership and continuity

The existing page-level insertion/removal observer still excludes player-internal
changes. New per-player observers are limited to direct root child changes and
the top-level container's `class` attribute, with no recursive player subtree.
No answer, feedback or descendant-class monitoring is added. Controller-owned
eligibility attributes are outside those observation filters.

Only after the unchanged iframe ownership/content-ID/URL/window/document checks
pass can a child root be inspected or observed. The native frame-element load
listener handles initial document readiness/replacement. Validated child documents
also have filtered stylesheet load/error listeners. Replaced/removed documents
release their root/class observers, resource listeners and owned attributes;
removed frames also release the element load listener. Reattachment reuses active
records and does not duplicate controls or listeners.

No H5P initialization, frame navigation, instance replacement, answer/score change,
reporting/event dispatch modification or activity DOM replacement is added. No
programmatic focus operation is performed; existing focused controls are kept
while they remain available, including when an earlier player becomes eligible.

Without MutationObserver, existing frame/resource events and explicit Backdrop
behavior reattachment can discover support; class-only changes otherwise require
reattachment. Real supported Firefox behavior still needs qualification.

## Session preference behavior

The Step 5 single namespaced sessionStorage token and exception handling remain.
Initial reads, validation and invalid-token cleanup are deferred until an eligible
ready player exists. An incompatible-only page performs zero preference access,
including on persisted pageshow, even for an invalid stored token.

Removing support never calls the full stop path merely for lack of compatibility
and never deletes the preference. Compatible -> incompatible -> compatible page
navigation can restore it. Persisted pageshow marks restoration pending; when
support is available it rereads/revalidates once. Ordinary refresh, frame/style
load and DOM discovery do not repeat reads after initialization or write a valid
preference. User selection writes a stable token; Site default removes the key.

Disabled/editor/preview contexts still stop the controller without storage access.
Core remains explicit. Browser storage failures remain nonfatal. New-tab opener
copy and browser session restoration caveats from Step 5 remain. No localStorage,
cookies, account/server preference or H5P answer persistence is implemented.

## Automated verification

- PHP 7.4.33: 1,854 passed, 0 failed (four added CSS-scope assertions).
- PHP 8.4.22: 1,854 passed, 0 failed.
- Node.js 22.23.1: 291 passed, 0 failed (244 previous checks plus 47 dedicated
  compatibility checks).
- PHP syntax: all 26 PHP-related files pass on both executables.
- JavaScript/CommonJS syntax: all 11 files pass.
- `BACKDROP_SOURCE_ROOT` is explicit. Native definitions, installed source scripts
  and in-memory collaborators are used; no live bootstrap/database is loaded.
  Runtime asset tests publish only into test-owned temporary storage.

The learner fixture now models native direct containers, theme opt-in, deferred
rendering, targeted mutation delivery and active observer accounting. The shared
DOM fixture and production catalog/serializer remain unchanged. Existing tests
were updated narrowly for the new CSS selectors, deferred initial UI/storage,
and per-player observers. Security tests that need an active selector now include
an independent eligible player; their rejected-frame assertions remain intact.

Dedicated tests cover both DIVs and internal frames, unmarked ImageChoiceRounds,
generic/nested/forged marker rejection, delayed containers/classes/documents/styles,
ambiguous containers, dynamic insertion/removal, mixed global/Core/custom styling,
observer and listener cleanup, session preservation, pageshow, exclusions, focus,
state identity and absence of lifecycle/network/persistent-store operations.
These DOM doubles model the fixed selector contract; they are not a browser CSS
engine or proof of actual authored activity behavior.

## Pending controlled browser checkpoints

After separately authorized transfer/cache preparation, verify ordinary playback
on a compatible single activity, the compatible multi-activity View, and the
reported Image Choice Rounds page. Confirm one/one/no selector respectively.
Add separately authorized mixed DIV and iframe coverage, delayed initialization,
keyboard/status behavior, stored-token navigation and Back/Forward restoration.
Confirm independent answers remain intact during style-only changes; reload and
navigation can naturally reset H5P answers because Themer does not persist them.

Legacy/unmarked third-party activities can be false negatives. Nested/composite
activities are not certified by a marker somewhere inside them; only the own
recognized top-level container qualifies. The source-based convention must be
qualified against representative libraries. No source modification, library
upgrade, dependency rebuild or browser success is claimed for the reference
Image Choice Rounds activity by this isolated step.