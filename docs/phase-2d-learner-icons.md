# Two-color learner dropdown — independent integration

Status: integrated and regression-tested in an independent Git clone. Phase 2D
passed 12/12 user-confirmed initial Firefox functional checks in the disposable
test environment; the compact layout was subsequently accepted in Firefox. It
has not been deployed to the original Backdrop site or published. These results
do not establish full WCAG compliance or comprehensive accessibility coverage.

## Architecture and boundaries

`h5p_themer.learner-icons.inc` projects exactly two hex colors per stable catalog
token. It uses `h5p_themer_theme_catalog()`, the existing selection resolver and
the effective global resolver. Core values come from the installed H5P root CSS;
there is no copied runtime Core palette. Sparse schema-1 values fall back to that
Core pair. Schema-2 values use existing pinned inheritance. Only presentation
hex values are expanded/uppercased; stored definitions are untouched.

The optional `icons` payload map is separate from options, runtime CSS, keys and
session preference tokens. It contains no descriptions, base references or full
palette. The parser accepts only one unambiguous Core root rule with the two
fixed literal hex declarations, caps its input size, and rejects missing,
duplicate or non-hex values. Failure produces null metadata and a native control.
The fixed module stylesheet is read only; no remote CSS is fetched.

`h5p_themer.learner-picker.js` is a presentation adapter. It builds safe text and
validated CSSOM background colors; it never interprets label HTML. The native
select and its existing change listener still apply themes and save preferences.
The only commit bridge sets that select value and dispatches one bubbling change.
Exploration, label updates and restoration do not dispatch changes. Existing
eligibility, same-origin iframe validation and player state handling are retained.
No runtime bundle, serializer, schema, preset catalog or admin form is changed.

The adapter requires working Popover API support and its own stylesheet readiness
marker before hiding the native presentation. Its list uses the browser top layer
to avoid iframe/ancestor stacking and overflow clipping. While open, position is
constrained to the viewport, flips above when useful and updates on scroll/resize
and visual-viewport changes. Ordinary refresh reuses DOM/focus/candidate. Catalog
or external selection changes cancel exploration and resynchronize. Destruction
removes listeners and restores native attributes. A detached popup cannot prevent
cleanup. Metadata is not polled; normal server settings delivery remains required.

## Accessibility and interactions

Reference: [WAI-ARIA APG combobox pattern](https://www.w3.org/WAI/ARIA/apg/patterns/combobox/)
and [select-only example](https://www.w3.org/WAI/ARIA/apg/patterns/combobox/examples/combobox-select-only/).
The presentation keeps DOM focus on one labeled select-only combobox. A listbox
contains named options; aria-activedescendant tracks exploration. aria-selected
and a visible check track the committed selection independently of the active
candidate outline. Icons, checks and the arrow are decorative and aria-hidden.
The description and existing polite status remain available.

- Pointer activation, Enter or Space opens the closed control.
- Arrows clamp to available candidates; Home/End select the first/last candidate.
- Printable characters support prefix type-ahead and repeated-letter cycling.
- Enter/Space or a row click commits once and closes.
- Tab/Shift+Tab commits an open candidate, closes and permits natural focus advance.
- Escape, click-away and external focus loss cancel without applying or storing.
- Restoration and catalog synchronization update presentation without a user event.

Two equal blocks occupy approximately 24 by 14 pixels with a neutral border and
divider. Flex layout places them at inline start with a half-em gap; labels wrap.
Desktop option rows have a 32px minimum height; coarse-pointer option rows retain
a 44px minimum. Labels can wrap and rows can grow. The viewport-aware maximum
height and scrolling remain available when needed. Forced-colors CSS hides
swatches while retaining system text, focus outlines and checks. No gradient,
image, icon font or animation.

## Fallback and compatibility limits

If metadata is absent/invalid, the picker script or CSS is missing, or required
browser APIs are unavailable, the native selector remains functional. Successful
initialization alone hides it from display, tab order and the accessibility tree.
A later presentation failure restores the native control and focus when needed.
JavaScript-disabled playback keeps existing global behavior; H5P itself needs JS.
No CSP setting is relaxed. Original-site CSP and aggregated asset delivery remain
to be verified during an authorized deployment. Older browsers without Popover
use native options without colored icons. Mobile assistive-technology support is
not established.

## Automated evidence

Run the complete PHP suites with explicit PHP 7.4.33 and PHP 8.4.22 executables,
using `-n tests/run.php --backdrop-root=<existing-source-root>`.
Set `BACKDROP_SOURCE_ROOT` and `PHP_EXECUTABLE` explicitly for the Node suite,
then run `node --test tests/js/*.test.cjs` under Node 22.23.1.
Redirect TEMP and TMP to dedicated ordinary temporary storage outside live public
directories.
Tests never include site settings or bootstrap a live database. Storage/cache
collaborators are isolated. A sandbox that makes PHP is_readable/is_writable fail
requires a normal-permission execution of this site-free harness.

PHP tests cover the seven base choices, arbitrary saved custom pairs, pinned and
sparse inheritance, effective overrides, strict validation, rename/delete, no
configuration writes and unchanged bundle/keys. Node tests retain the earlier
291 tests and add focused picker/fallback/lifecycle/player contracts. DOM doubles
are not browser rendering or assistive-technology evidence. The independent-clone
regression results are PHP 7.4.33: 1,898/1,898; PHP 8.4.22: 1,898/1,898; and
Node.js 22.23.1: 351/351, all with zero failures. PHP syntax checks covered 28 files
on each version; JavaScript syntax checks covered 14 files.

## Controlled acceptance and remaining qualification

The user confirmed **12/12 initial Firefox functional checks** in the disposable
test environment. The compact layout was subsequently accepted: all nine themes
were visible without scrolling at normal zoom, and the two-color icons and spacing
looked correct. These are user-confirmed results, not browser tests rerun during
documentation editing.

The checklist below remains a reference for broader qualification. The recorded
12/12 functional result does not establish completion of every item or environment.

1. Firefox: inspect icons inside the expanded list for Core, Daylight, all built-ins,
   both saved customs and a future valid custom. Check exact resolved color pairs.
2. Keyboard: Tab, Shift+Tab, Enter, Space, Escape, Home/End, arrows and prefix/repeat
   type-ahead. Confirm no theme or preference changes until commit, one on commit.
3. NVDA with Firefox: name, role, current value, expanded state, candidate and
   committed option announcements, description, status, no duplicate native UI.
4. Pointer/touch: row activation, scrolling a long list, outside click, no focus
   trap; opening near viewport edges, iframe overlap and nested scrolling.
5. 200–400% zoom, narrow/mobile width, long translated labels, RTL and pinch zoom.
6. Windows forced colors: readable labels, committed checks and candidate focus.
7. Back/forward restoration, same-tab reload, catalog rename/deletion while open,
   loss/reappearance of eligible activities and failed/missing presentation assets.
8. One and multiple DIV/internal iframe activities, unrelated frames unchanged,
   answers/scores/progress retained; Core versus Site default semantics unchanged.
9. Chromium/Safari and representative mobile AT where supported; native fallback
   without Popover. Check Console/CSP and actual Backdrop aggregation delivery.

NVDA, touch, forced-colors, RTL, high zoom and other-browser verification remain
limited. Existing Firefox 5E (10/10) and 6E (9/9) passes describe the prior Phase 2C
control and are separate from the Phase 2D results above.
Original-site deployment, cache refresh and publication require separate authorization.
