# Phase 2C Step 4A: page-local live theme switching

SPDX-License-Identifier: GPL-3.0-or-later

Step 4A activated the native learner selector and was transferred in Step 4B.
Step 4D manual Firefox verification failed because valid internal frame documents
were rejected. Step 4E identified the document-URL assumption. Step 4F corrects
that check in isolation only; the correction awaits controlled transfer and
Firefox verification. Phase 2C remains incomplete: there is
no preference persistence, account storage, browser storage, preference endpoint
or cross-origin messaging. Phase 2D remains planned.

## Behavior and supported contexts

The administrator Boolean remains false by default. Enabled ordinary Backdrop
playback pages receive one native, labeled H5P theme select above the first
supported player. Its initial choice is Site default on every page load. Choices
come from h5p_themer_theme_catalog(): Site default, H5P Core defaults, Daylight,
Lavender, Mint, Sunset, Dark and all valid saved custom themes. Labels are text.
The select is unnamed, uses native keyboard behavior and never moves focus during
switching. Its polite status region reports success, partial support or fallback.
A focused selector is not moved when an earlier activity appears; a subsequent
attachment after focus leaves may reposition it.

All eligible DIV activities share the parent document choice. Internal H5P
about:blank iframes receive the same choice through same-origin document access.
Only registered numeric content IDs participate. Editors, administrator previews,
players inside forms, standalone external embeds, srcdoc previews, nested host
pages and cross-origin resources are excluded. The existing external-embed global
runtime remains unchanged. Later supported DOM insertions are discovered using
one parent child-list observer; without MutationObserver, normal Backdrop
reattachment remains available. Complete AJAX-only H5P initialization itself is
not supplied by this module.

## CSS cascade and immutable assets

The existing catalog, selection resolver and strict runtime serializer remain the
only color authorities. Schema-1 customs retain sparse authored overrides;
schema-2 customs resolve their pinned palette plus saved overrides. The 33-property
allowlist and exact trusted preset-expression rules are preserved. The unsupported
contrast-cta-dark property is still excluded. No schemas or preset values change.

Enabled playback uses a reusable learner-v1-<SHA256>.css bundle in the existing
local public Themer directory. The existing atomic, verified publisher now accepts
only the colors-v1 or learner-v1 namespace. Colors-v1 remains the default and the
exact disabled-mode serializer output is preserved. A bundle is generated for a
server-rendered catalog, not for each selection. Unchanged content is reused;
label changes cannot reorder its rules or rotate CSS identity.

The bundle contains fixed selectors:

- html:not([data-h5p-themer-learner]) .h5p-content carries the global palette.
- html[data-h5p-themer-learner] .h5p-content sets each allowed property to inherit,
  neutralizing stale legacy global assets while inheriting actual native root
  values. It precedes explicit palette rules of equal specificity.
- html[data-h5p-themer-learner="t<64 lowercase hex digits>"] .h5p-content carries
  one validated palette. Opaque keys hash identity and canonical declarations.

Site default removes the attribute, using the global selection represented by
that server-rendered page. Explicit Core sets its own key with no palette rule:
Core values come from the installed native :root stylesheet. Daylight has its
own palette/key and is never Core. Sparse legacy themes cannot inherit the
administrator's palette, even when a legacy global CSS file remains attached.
Library styles using higher specificity or !important, hardcoded colors, inline
custom properties and libraries without variable support still need qualification.

A fixed non-H5P custom property on html identifies the exact loaded bundle. It is
only a readiness marker, not a theme override. It works independently of H5P CSS
aggregation or the browser asset URL. The controller never writes style elements,
inline styles, stylesheet URLs or raw CSS. Native Backdrop settings carry an atomic
JSON string containing text, option tokens, opaque keys and the bundle ID. This
avoids deep-merging deleted array/map entries into a later AJAX catalog. No extra
inline script/style CSP permission or new external dependency is introduced.

## Readiness, preservation and fallback

### Step 4F: internal iframe validation

The iframe element's src stays about:blank while H5P Core opens, writes and
closes its document. On the inspected activity, that rendered document inherited
the parent page URL. Requiring contentDocument.URL to remain about:blank prevented
the selected attribute from reaching the actual activity, even though the palette
stylesheet was loaded and the parent selector's change handler worked.

The corrected controller accepts a genuine about:blank document or the exact
parent page URL after URL normalization and removal of fragments. Scheme, host,
port, path, query and credentials must otherwise identify the same parent page;
sharing an origin is insufficient. Parent-derived URLs are limited to HTTP(S).
The source attribute must still be internal about:blank and srcdoc is excluded.

URL checks run only after validating all of the following:

- The iframe is connected to this parent document and its numeric content ID is
  present in H5PIntegration. Existing player/editor/preview discovery gates remain.
- The accessible document is the iframe's current contentDocument. Its window
  agrees with contentWindow, frameElement and window.document; borrowed or stale
  documents cannot stand in for the current playback document.
- Its html root has H5P's h5p-iframe class, and its direct body activity root has
  the same registered data-content-id as the iframe.

The existing exact stylesheet marker remains required before applying any theme.
Missing or mismatched CSS, inaccessible frames and unrelated navigated documents
retain fallback/partial-support behavior. Exceptions are contained. Rejection
cleans only the document previously tracked for that frame; it cannot clear a
borrowed parent document or interfere with another activity. Replacement/load
handling applies the latest selection without reinitializing or navigating H5P.

The regression fixture now models an initial blank document followed by a written
H5P document whose URL defaults to the parent URL. Explicit cases retain genuine
about:blank coverage. DOM/window ownership, registered identity and marker failures
are tested independently, including unrelated same-origin navigation. The earlier
fixture's fixed about:blank URL had concealed this defect. These remain DOM/URL
contract tests; real browser security and playback qualification are still pending.

### Existing readiness and fallback behavior

The parent document and every internal frame must show the matching bundle marker.
A missing/outdated parent bundle disables the select and restores Site default.
Unavailable frames retain global playback; other supported activities can update,
with partial-support status. Frame load listeners apply the latest choice after
H5P creates a document or its linked resources load. A captured parent link-load
listener handles delayed parent styles. There is no polling. Removed frames and
disabled controllers release listeners, retained document references and owned
attributes. Only one parent observer is used; activity-internal animation mutations
are ignored.

Switching only changes the root data attribute. It never calls H5P.init, changes
content parameters, alters reporting/storage, recreates instances, replaces
activity DOM, reloads/navigates frames or controls media. This is supported by
state/identity regression tests; real answers, scores, media continuity, layouts,
fullscreen and focus still require Firefox checks on representative libraries.

Invalid selections return to Site default and announce fallback. A deleted theme
is removed on the next normal server page/AJAX catalog delivery. An already open
page cannot discover a server-side deletion immediately without polling or a new
endpoint; neither is introduced. A changed catalog with missing matching CSS
fails closed to Site default. A later administrator global change also requires
new server-rendered settings/styles; existing pages do not subscribe to updates.

Without the learner controller, the root attribute is absent and global styling
remains active. H5P retains its ordinary playback behavior when its own scripts
are available. Completely disabling JavaScript also disables H5P's own JavaScript
runtime; the Themer does not add a new playback requirement.

## Cache behavior

Enabled administration snapshots include the complete validated option/key/bundle
metadata. Changing any selectable palette rotates the bundle and invalidates
native page/layout output through the existing combined save mechanism. Label
changes update options; no-op, rejected and density-only changes do not affect
colors. Disabled mode retains prior cache behavior. Catalog lookup remains
read-only and uncached. No cache invalidation occurs for a learner switch.
Direct programmatic configuration changes retain the caller's normal cache
freshness responsibility. Historical immutable assets are not deleted here.

## Automated and manual verification

Run the full PHP runner under each supported executable. Set BACKDROP_SOURCE_ROOT
explicitly for Node and PHP_EXECUTABLE to a PHP CLI executable. Node's new contract
fixture invokes a CLI-only PHP worker using memory Config and installed source
class definitions, without bootstrapping Backdrop or opening a database. Public
asset writes in PHP tests map only to private temporary directories. DOM/cascade
checks are contract models, not a browser CSS/layout engine.

Pending manual checkpoints, after a separately authorized transfer:

1. Start with one real activity and verify Site default against the saved global
   theme. Select explicit Core, then each built-in and each custom schema.
2. Use a non-Core global palette to verify genuine Core and sparse-custom fallback;
   confirm schema-2 Copy of Mint starts with its saved #3366CC override.
3. Answer questions and start media, switch repeatedly and verify answers, scores,
   progress, media continuity and frame URLs. Confirm native keyboard/focus and
   status announcements, including a narrow viewport.
4. Check DIV plus internal iframe/multiple activities, delayed frames and newly
   inserted supported players. Test CSS aggregation enabled/disabled and browser CSP.
5. Check invalid/deleted choices after normal catalog refresh, stale assets,
   inaccessible/external frames and excluded editor/admin-preview contexts.
6. Check disabled learner selection and unavailable learner JavaScript against
   baseline global styling. Confirm no preference survives a page reload.

Recovery remains a separate authorized operation: disable the administrator
Boolean through normal administration, refresh affected pages using normal native
cache procedures, or restore a verified pre-transfer project copy. Preserve
configuration, custom themes and H5P content. No recovery, live cache operation,
configuration toggle, HTTP request or browser test was performed in Step 4A.

## Step 4F verification scope

Only the learner controller, its iframe fixture, switching tests and this document
change in the isolated correction. PHP runtime architecture, generated CSS rules,
preset data, configuration schemas and administration settings remain unchanged.
All learner choices still use the existing validated palette bundle. The complete
PHP/Node regression suites and changed JavaScript syntax checks must pass before
transfer is proposed. No live request, cache operation, preference persistence or
transfer is part of Step 4F. Firefox must repeat the failed node/7 switching check
after a separately authorized transfer and any necessary asset-cache preparation.

Step 4F isolated regression results: PHP 7.4.33 and PHP 8.4.22 each passed 1,850
checks with zero failures; Node.js 22.23.1 passed 174 tests with zero failures
(including 32 added cases). All three changed JavaScript files passed syntax
checks. A memory-only baseline/correction comparison reproduced the old rejection
and verified the corrected attribute on a parent-derived H5P document. These
results do not establish corrected Firefox behavior on the live site.
