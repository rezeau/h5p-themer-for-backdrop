# Proposed H5P integration-settings bridge — NOT IMPLEMENTED

**Historical, unapplied proposal.** Current global color runtime uses the existing
H5P styles hook without this bridge or H5P source changes. See [the current
roadmap](roadmap.md) for PLANNED learner-selection work; the following technical
proposal is neither implemented nor current installation guidance.

**Roadmap update:** runtime delivery now belongs to Phase 2; Phase 1C is reserved
for built-in presets. See [the revised roadmap](builtin-presets-roadmap.md).
This terminology change does not authorize or implement this proposal.

**Explicit approval gate:** this document authorizes no H5P changes. Phase 1A
neither implements nor requires this bridge. No vendored H5P Core change is
proposed. Source references below use the inspected Backdrop H5P commit
`3491220c29716a08cc809b90951f86807450c151` on `feature/h5p-api-1.28`.

## A. Limitation of existing hooks

`hook_h5p_scripts_alter(&$scripts, $libraries, $mode)` and its styles equivalent
alter asset lists, not the integration settings object. They receive libraries
and rendering mode, but no content/node object or reference to integration data.
They cannot directly set a supported `H5PIntegration.theme.density` before Core
initialization, nor add structured per-content integration metadata.

Actual calls are in `h5p_add_files_and_settings()` (`h5p.module:1201-1202`) for
DIV/internal iframe and `h5p_embed()` (`1700-1701`) for external embeds. The
documented `editor` mode does not prove equivalent calls in the current editor
adapter; its separate asset assembly must remain outside learner theming.

## B. Can Phase 2 global theming work without a bridge?

**Yes, in principle.** A themer can append scoped color CSS through the styles
hook and append a script that replaces density classes after initialization,
tracks all activity roots, and resizes the corresponding instances. Each
external embed can run that script inside its own document. A bridge is not
required just to change CSS custom properties or existing root classes.

That design needs browser qualification: initial layout may use large density,
the initialized event carries no instance identity at the inspected trigger,
and late changes may cause layout movement. A bridge reduces configuration
delivery ambiguity, but cannot by itself guarantee zero flicker or correct
third-party library resizing. No need for a bridge has been demonstrated by a
Phase 2 prototype because such work is not authorized yet.

## C. Operations needing an explicit settings seam

- Supported server-side modification of the final integration object, including
  native global density available before `H5P.init()`.
- Structured settings transported identically to page players and external
  embeds without rewriting serialized JavaScript or mutating shared service
  internals.

Theme storage, administration, CSS generation, and post-initialization theme
switching do not inherently require this bridge. Per-instance density still
needs a themer runtime: Core reads one document-level `theme.density`, not a
different value from each `contents` entry. Learner persistence is unrelated.

## D. Smallest proposed interface

One hook, called once per serialized integration document:

```php
function hook_h5p_integration_alter(array &$integration, array $context) {
  // $context['mode'] is 'page' or 'external'.
  // Existing contents and assets are available in $integration.
}
```

The return value is ignored. Hook implementations must preserve existing
integration keys and content/asset entries except the documented settings they
own. Themer-specific metadata should use its own `h5pThemer` namespace; do not
pretend that Core consumes a general `theme.colors` structure.

For capability discovery, propose a minimal accessor in the H5P adapter:

```php
function h5p_integration_settings_api_version() {
  return 1;
}
```

This version describes hook timing, modes, and delivery semantics, not Core's
version. There is no capability accessor or hook implementation in Phase 1A.

## E. Exact proposed insertion points

1. In `h5p_preprocess_page()` (`h5p.module:1716`), after obtaining the final
   integration from `h5p_add_files_and_settings(NULL, NULL)` and inside its
   existing truthy check, immediately before `json_encode()`/`backdrop_add_js()`.
2. In `h5p_embed()` (`1652`), after content/assets/language have been assembled,
   immediately before `include('library/embed.php')` (currently line 1710).
3. Document the hook and accessor in the adapter's `h5p.api.php`.

Illustrative pseudocode only (do not apply):

```php
// Normal page: immediately before existing serialization.
$context = array('mode' => 'page');
backdrop_alter('h5p_integration', $integration, $context);

// External endpoint: immediately before including the existing template.
$context = array('mode' => 'external');
backdrop_alter('h5p_integration', $integration, $context);
```

Do not insert the hook into `h5p_get_core_settings()` as well: that would create
duplicate invocation semantics and run before final contents/assets exist.
No changes to `library/js/h5p.js` or `library/embed.php` are proposed.

## F. Rendering behavior and editor boundary

- **DIV:** the hook alters the page integration later consumed by all players.
- **Internal iframe:** `H5P.getHeadTags()` assigns
  `window.parent.H5PIntegration`; the altered page object reaches it without a
  second server hook. It remains shared: never mutate global integration state
  as though it belongs exclusively to one iframe.
- **External embed:** the hook alters the independent object serialized by
  `library/embed.php`. The host page needs no DOM access to the player.
- **Editor:** the editor currently forces creation of page integration through
  `h5p_add_files_and_settings(TRUE, TRUE)`. An empty `contents` collection should
  not receive learner theme settings. A mixed page containing authoring and
  playback is harder: `mode = page` is intentionally not a claim of learner-only
  context. Before approval, agree whether such pages use class-based density
  only or require a separate authoring-context flag. Do not infer that a route
  name or an empty-content test solves every mixed-page case.

## G. Initialization timing and asset ordering

Core `H5P.init()` (`library/js/h5p.js:93-141`) reads density before constructing
the instance. Native configuration must use `large`, `medium`, or `small`.

For internal iframes `H5P.getHeadTags()` (`415-437`) orders Core styles, library
styles, Core scripts, library scripts, then integration assignment. The external
template also loads scripts before its body integration assignment. Injected
scripts must not read integration settings eagerly at file evaluation.

The bridge alters the serialized object, not script execution order. Themer
initialization still needs a tested ready/lifecycle strategy, late-load handling,
per-root tracking, and aggregation coverage. Existing assets should remain on
the current hooks; moving every asset operation into the bridge is unnecessary.
The page hook must not schedule assets too late for Backdrop output generation.

## H. Compatibility/capability reporting

Phase 2 would check the exact Core 1.28 requirement independently of the bridge
accessor. If a bridge-dependent delivery strategy is approved, absence of version
1 should produce an actionable status and suppress only that delivery path.
Do not silently guess support from release strings, Git branches, hook files,
or the existence of theme CSS. Never delete definitions on capability failure.

If the bridge-free strategy is chosen instead, absence of the accessor must not
be treated as incompatible. This decision belongs to the Phase 2 design gate.

## I. Effects on existing H5P behavior

With no implementing modules the proposed hook is a no-op; existing asset lists,
serialization, player initialization, editor configuration, content parameters,
and exports should be unchanged. The adapter already owns both serialization
points. Do not patch Core, content JSON, installed libraries, or the editor.

The new hook would let trusted PHP modules change settings, so its documentation
must specify ownership, conflict handling, and appropriate JSON-safe values.
Core access controls and statistics settings must remain untouched by Themer.
The accessor advertises a contract, not successful theming of every library.

## J. Tests required before approval/merge

1. No-implementer parity of integration JSON, HTML, assets, and editor output.
2. Exactly one hook invocation per serialized page/external document; explicit
   mode and complete contents at each call. Internal iframes inherit the result.
3. Native density present before initialization in DIV/internal/external modes.
4. Integration script timing with aggregation on/off, cold/warm cache, and
   different load speeds; no undefined-global errors or duplicated listeners.
5. Multiple/mixed players, repeated content, nested activities, fullscreen,
   resized content, and dynamically inserted players.
6. Editor-only and mixed editor/playback pages, with an agreed editor policy.
7. Existing access, anonymous/authenticated embed, statistics, saved state,
   language, export, and content JSON behavior preserved.
8. Capability absence/version mismatch and Core mismatch fail without writes.
9. Focused source-level/adapter tests plus browser tests in an authorized isolated
   installation. The main Backdrop site is not a fallback test environment.

**Outstanding approval:** whether native pre-initialization density justifies
this interface, and how authoring context is handled on mixed pages. No H5P
changes should start until this proposal has been reviewed and authorized.
