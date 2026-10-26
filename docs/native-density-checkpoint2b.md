# Native Density — Checkpoint 2B, Stage 1

**Current status annotation:** Checkpoint 2B Firefox acceptance is complete. The original stage-specific observations and pending statements below are retained as history. Current activation uses strict boolean `h5p_themer_native_density_enabled`; the temporary Checkpoint 2B content-ID gate is historical. The v0.4.0 scope is recorded in the current readiness note. The later Checkpoint 3C Lite superseded the full Stage 2/3 plan and passed 46 focused assertions and all seven manual Firefox tests. A separate actual module disable/re-enable verification passed 85 checks without uninstalling. Full code rollback was not rehearsed. See [current readiness](v0.4.0-readiness.md) and [installation](installation.md).

Repository implementation only. No installation, database, persistent
configuration or service is changed by this stage. Checkpoint 1's seven files
remain independent and unchanged. Module version metadata remains 0.2.1 until a
separate version-preparation task.

## Activation and scope

The controller is disabled unless site settings contain an allowlist with exactly
one positive content ID. The following is a **future isolated-deployment step**,
not an instruction to change any site during Stage 1:

```php
$settings['h5p_themer_native_density_content_ids'] = array(6);
```

This is a temporary developer gate, not a new persistent theme setting, content
assignment UI or configuration migration. The value is the H5P **content ID**, not
necessarily the node ID. Confirm both again before deployment. Missing, malformed
or multiple-ID allowlists disable attachment. The gate must never be enabled in
the normal local or online installation for this checkpoint.

Only a single matching `iframe.h5p-iframe` is supported. Duplicate matching
iframes fail closed. The parent integration, iframe/window/document identity,
same-page written document, direct content root/container, unique realm-local
instance, root status and True/False 1.8 library identity must all agree. DIV,
nested, external, srcdoc, editor and representative-preview contexts are excluded.

## Integration

`h5p_themer.native-density.inc` uses the existing effective selection resolvers and
visibility-filtered learner catalog. Its separate JSON payload contains the
target ID, Global Default profile, existing learner-bundle readiness ID and
profile mappings keyed by the existing opaque learner palette keys. It does not
contain color declarations, private theme metadata, hidden theme IDs or CSS.

The existing script-alter hook attaches the controller to the parent page. It is
not inserted into H5P's iframe dependency list. The ordinary playback route guards
and compatibility checks exclude administration, editors, external embeds and
unsupported Core APIs. Global-only mode does not enumerate the learner catalog.

`js/h5p_themer.native-density.js` listens for native initialization, resource/load
events, relevant iframe insertion/removal and changes to the existing learner
selection attributes. It does not add a selector, preference storage, endpoint,
polling, global instance-position lookup or player recreation.

After initialization it captures the original effective native profile once per
owner. It replaces only `h5p-small`, `h5p-medium`, and `h5p-large` on the validated
iframe's `.h5p-content`. Other classes, answers, feedback and colors are untouched.
An originally unclassified root can return to its original native fallback.

| Existing theme selection | Native Density |
|---|---|
| Custom theme | Its validated saved Density |
| Core | Captured original profile |
| Built-in | Captured original profile; current immutable built-ins have no Density override |
| Site default | Effective Global Default Density, or original if that default has no override |

There is no invented built-in override setting. Hidden custom Global Defaults
still supply Site default's profile; hidden themes do not become explicit choices.
For a root temporarily ineligible for learner colors, the existing Global Default
fallback is preserved for Density too.

All color CSS, keys, bundle identities and existing learner behavior stay intact.
No Density CSS variables, custom spacing rules, H5P Core/library edits or preset
rewrites are introduced. Existing native CSS must be available before application.

Resize work is coalesced into at most one animation-frame callback per target.
An effective native profile change requests `H5P.trigger(instance, 'resize')`, even
if the first root-height measurement is equal: native Question also recalculates
button and feedback layout. Identical-profile/color-only changes and net-zero
cycles request no resize. Core owns iframe height. Cleanup restores a still-owned
profile and can request one explicit resize for that restoration. Removed or
replaced owners cannot receive stale callbacks; a failing native API is not retried
for that owner.

During a temporary settings/CSS-readiness or selection-attribute handoff, the
current owned profile is retained and pending work is parked. This avoids an
unnecessary original/custom profile bounce. Invalid/disabled settings release the
controller and restore its original profile without changing color selection.

The separate metadata fingerprint is included in the existing combined admin
cache snapshot, independently of the current route. Successful relevant
Density-only edits therefore invalidate stale HTML once; color stylesheet hashes
remain unchanged. Hidden unselected edits and no-ops remain output-neutral.
Already-open pages retain their server-supplied settings until ordinary reload or
settings reattachment; there is no live configuration polling.

## Automated verification

The PHP tests cover disabled/invalid gates, profiles, Core/built-ins, hidden global
versus selectable choices, unchanged configuration/color bytes and keys,
global-only behavior, attachment guards and actual combined-cache invalidation.

The JavaScript tests consume a site-free PHP metadata export and run the
production behavior alongside the existing learner behavior. They cover native
classes, original restoration, selection semantics, unrelated classes/state,
isolation, unsafe identities, iframe/container replacement, readiness handoffs,
bounded/no-op resizing, cancellation, invalid transport and native API failure.
DOM doubles do not establish real Backdrop rendering or Firefox geometry.

Use the existing README's source-root/runtime requirements and isolated suite
commands. The new focused test is `tests/js/native-density-integration.test.cjs`.
Test logs are kept in ignored `artifacts/native-density-checkpoint2b-stage1/`.
Installed Backdrop/H5P source is read only; PHP's filesystem fixtures are private
temporary directories, not site public storage. No live site bootstrap occurs.

## Proposed Stage 2 deployment — requires separate authorization

1. Recheck repository changes, the unchanged Checkpoint 1 hashes, and the intended
   target's canonical paths. The candidate is
   `C:\wamp64\www\backdrop-themer-v020-fresh`, database
   `backdrop_themer_v020_fresh`, loopback server port 8094. Reconfirm that its
   module directory is not shared or linked to a protected installation, its
   private settings point to that exact database/configuration, and its existing
   HTTP/mail guards remain intact. Do not start or stop another service.
2. Record rollback copies/hashes of the isolated site's current module, settings
   and relevant configuration; preserve existing content, libraries, grants and
   isolation guards. Any database backup or test-fixture changes must be covered
   by the Stage 2 authorization. Do not bootstrap a protected site.
3. Deploy the **complete restored module baseline plus these repository changes**
   only into that isolated site's `modules/h5p_themer`. Its audited code predates
   v0.2.1, so copying only the four changed production files is insufficient.
   Include root module PHP/metadata, runtime JS/CSS and module defaults; exclude
   tests, dev, artifacts, credentials and Git data. Do not overwrite site active
   configuration. Do not enable/uninstall/update modules or replay migrations.
4. Confirm the current True/False content ID (audited node/content 6), then add
   the single-ID site-settings gate only in this disposable site. Verify the
   native iframe path and existing color behavior before accepting Density.
5. Under explicit fixture authorization, prepare selectable Small/Medium/Large
   custom themes through existing supported administration, preferably with
   identical colors. The audited existing fresh-site custom themes are hidden.
   Add a separate control activity/test page only if authorized; do not publish
   hidden existing themes merely to populate the selector. Leave Global Default
   and unrelated visibility untouched unless a stated test requires a temporary,
   recorded change with restoration.
6. Refresh the isolated site's JS/output caches using its established guarded
   full CLI bootstrap and native `backdrop_clear_js_cache(); cache_clear_all();`
   operations. Do not expose a cache-clearing web endpoint or manually delete
   generated CSS. Verify actual browser-delivered JS/settings and a reachable
   test URL before handing it to the user. Port 8094 is a candidate, not a claim
   of HTTP verification during Stage 1.
7. Perform one Firefox acceptance cycle: all three custom profiles before and
   after Check/feedback; retained answer/score/Retry; Core and built-in original
   restoration; Site default following custom versus Core Global Default; correct
   colors; independent control unchanged; fitting iframe height, no overflow and
   no console errors. Reload between server configuration changes and compare
   each player's geometry while its own answer/feedback state is held constant.
8. Stop for the user's explicit Firefox PASS/FAIL. Do not commit, push, broaden
   the target scope, or deploy to either protected installation.

Known limits: initial original-profile flash is possible; competing third-party
Density writers are not qualified; multi-ID allowlists and duplicate target
iframes are deliberately unsupported. Real Backdrop delivery, aggregation and
Firefox acceptance await Stage 2. No Stage 2 action is authorized by this file.
