# Theme administration

SPDX-License-Identifier: GPL-2.0-or-later.

The implemented module provides immutable built-ins, both custom-theme schemas,
native picker companions, global color runtime and saved/unsaved theme previews.
Stored density is not applied. See [installation](installation.md) for setup.

## Routes and access

All paths start with `admin/config/content/h5p-themer`:

| Suffix | Purpose |
| --- | --- |
| (none), `/list` | Core, built-in and custom overview |
| `/add` | Create a schema-1 custom definition |
| `/<custom-id>/edit` | Edit custom text, density and sparse overrides |
| `/<custom-id>/duplicate` | Copy a custom theme under a new custom ID |
| `/<custom-id>/delete` | Native custom deletion confirmation |
| `/default` | Global default and learner-selection toggle |
| `/presets/<preset-id>/<version>` | Read-only built-in inspection |
| `/presets/<preset-id>/<version>/duplicate` | Create a pinned schema-2 custom copy |

The administrative catalog is explicitly pinned to `moodle-478fed30d2a4`.
Preset routes validate both the exact ID and version. There are no built-in
Edit or Delete routes. A path such as `/daylight/edit` always addresses the
custom namespace; it never edits the built-in. It is valid only if that
custom object exists. Existing custom IDs, including `presets` and
`daylight`, are not reserved away by the new route layout.

Every route requires an authenticated account with the restricted
`administer h5p themes` permission. Callbacks also check access. GET requests
never mutate definitions or defaults. Native Form API supplies session-bound
CSRF tokens. There is no competing token scheme or custom AJAX workflow;
the Step 2C script enhances only forms with editable color fields.
Step 2C adds no menu routes and performs no cache or menu rebuild.

## Overview and built-in inspection

The overview has three separate sections:

1. **H5P Core defaults:** original styling with no Themer color/density
   overrides. This is distinct from Daylight.
2. **Built-in themes:** Daylight (default preset), Lavender, Mint, Sunset and
   Dark. These appear directly from the catalog, without editable config
   objects. Compatible installations offer View and Duplicate only.
3. **Custom themes:** valid schema-1 and schema-2 definitions, sorted naturally
   by label then ID, with Edit, Duplicate and Delete operations.

Tables distinguish stored and effective global defaults. A settings.php
override can make these differ; the overview warns and shows both indicators.
Invalid custom objects appear as diagnostic rows with no unsafe operations.
Missing/invalid defaults never silently select Core or Daylight.

A built-in View page shows its label, ID, version, density information,
provenance, license and all 34 exact values. Expressions such as `color-mix()`
and `rgba()` are escaped text inside code elements on the inspection page. Runtime delivery
uses exact trusted catalog values for validated global and enabled learner themes.
Saved samples and a Core comparison are available. The extra `--h5p-theme-contrast-cta-dark` value is
preserved and explicitly marked as lacking established Core 1.28 runtime
support. The palette itself defines no density override.

## Creating and editing custom themes

Add theme retains the schema-1 behavior: a unique stable ID, editable label,
description, density and optional sparse hex overrides. IDs match
`[a-z][a-z0-9_]{0,63}`. Editing cannot change the machine ID. Label renaming
preserves all references.

Schema-1 editing preserves schema 1, sparse maps and exact hex spelling/case.
No migration or palette derivation occurs.

Schema-2 forms display the pinned base ID/version as read-only information.
Each of the 33 existing override fields separately identifies the inherited
value and whether a saved administrator override exists. Inputs contain only
stored overrides, never the effective palette. Blank means inherit the pinned
base; clearing a saved override restores inheritance after Save.

The inherited extra property is displayed read-only, and inherited non-hex
expressions remain exact. Only opaque `#RGB` or `#RRGGBB` can be entered as
custom overrides. Whitespace, alpha hex, functions, declarations, unknown
properties and injected top-level fields are rejected server-side.
Form API's NULL placeholders for omitted declared fields remain unset;
unknown submitted color keys are rejected even when their values are blank.

Saved colors are never silently normalized. Schema, pinned base and native
metadata come from trusted stored form context rather than posted fields.
Forms edit originals, not environment-specific effective values. Existing
objects with settings.php overrides are read-only until the overrides are
resolved. Duplicating an overridden custom source copies its stored original
without modifying that source or persisting its effective overrides.

General settings use a native collapsible fieldset, expanded initially. The
five color groups use native collapsible fieldsets, collapsed initially.
Backdrop opens groups containing validation errors. Submitted names and error
associations remain unchanged; grouping introduces no general settings object.

### Native color picker companions

With JavaScript and native color-input support, every editable hex field gets
an adjacent color picker with a distinct accessible label. The textfield is
the only submitted authority. The picker has no name and creates no extra
configuration property. Initializing or reattaching behaviors never fills or
rewrites the textfield.

Typing valid hex updates the picker display. Three-digit hex expands to six
digits for the picker only: typed shorthand and letter case remain exact.
Choosing a picker color writes an explicit six-digit hex override into the
textfield. Clearing means deleting the text and saving; there is no separate
Clear override button. Invalid or incomplete text remains untouched and is
still subject to strict server validation.

For schema 2, blank text inherits the exact pinned preset. Representable
inherited hex appears in the picker without becoming a saved override.
The exact inherited value remains visible in the existing description.
Inherited rgba() and color-mix() expressions are neither approximated nor
copied into editable fields. If no representable inherited hex exists, a blank
or invalid text value disables the picker until valid hex is typed. Its disabled
swatch is explicitly identified as not being a preview. If inherited hex is
available while text is invalid, the picker shows that inherited hex and
explains that choosing a color will replace the invalid text.

Schema-1 blank retains Core/no-override semantics. No preset is assigned or
substituted. Since the administration form has no resolved Core-color map,
its picker stays disabled until an explicit valid hex is entered.

Disabled/read-only fields remain guarded. Without JavaScript, or without native
color-input support, ordinary hex textfields and existing server validation
continue to work. With JavaScript disabled, native collapse CSS leaves every
fieldset visible. No external picker, custom CSS or additional dependency is
required. Initial built-in duplication stays metadata-only with a read-only
palette; color pickers appear when editing the saved custom copy.

## Duplicating a built-in

Select Duplicate from its overview row or View page. Supply a new custom ID,
label and description. Density initially is `large` and can be chosen in the
form. The form shows the inherited palette as read-only information.

Save creates exactly one schema-2 custom definition with the exact
`base_preset: {id, version}` reference and an empty `colors` map. To customize
colors, save this initial copy, then use its Edit operation. A forged initial
override map is rejected. All 34 base properties are available through
effective resolution without flattening them into stored custom colors.

The new custom ID may match a built-in ID, but must not collide with any
existing custom config object, including malformed objects. The source
catalog and global default remain unchanged; the copy is never selected
automatically. The exact immutable catalog snapshot is checked again during
validation and locked submission.

## Duplicating a custom theme

A schema-1 source produces a schema-1 copy. A schema-2 source produces a
schema-2 copy with the same exact base/version and sparse override map.
Description, density and editable values are prefilled and may be changed.
Every copy requires a new independent custom ID. Saving affects only the new
object, leaving the source and default unchanged. Stale/changed sources and
post-build ID collisions are rejected.

## Selecting and clearing the global default

The selector lists Core, five built-ins and all valid customs. Internal
selection tokens such as `core`, `builtin:daylight` and `custom:daylight`
are form-only keys. They are checked against the current allowed collection
during validation and again under the write lock; they are never persisted
as machine IDs or raw configuration references.

| Existing settings | Explicit choice | Saved representation |
| --- | --- | --- |
| Schema 1 | Core | Schema 1, null |
| Schema 1 | Custom | Schema 1, original-style custom ID string |
| Schema 1 | Built-in | Schema 2, typed built-in ID and exact catalog version |
| Schema 2 | Core | Schema 2, null |
| Schema 2 | Custom | Schema 2, typed custom ID |
| Schema 2 | Built-in | Schema 2, typed built-in ID and exact catalog version |

A legacy string `daylight` always preselects the custom theme. It never means
the built-in. Merely viewing the selector does not save, migrate or select
anything. Choosing a built-in is the only necessary schema upgrade in this
form; selecting Core/custom under schema 1 retains schema 1.

A broken custom reference remains an invalid selection until an administrator
explicitly clears or replaces it. Malformed settings, including an unknown
catalog version, produce a clear diagnostic and require validated configuration
repair. Settings.php overrides retain precedence and block UI settings writes.

## Administrator learner-selection setting

Global Default contains **Allow learners to select their H5P theme**. It saves the
optional strict Boolean learner_selection_enabled, false by default. Missing is
false in settings schemas 1 and 2; toggling preserves the schema/global reference.
Effective settings overrides, permissions, native CSRF, stale checks and locking
remain unchanged. There are no per-theme approval checkboxes.

When enabled, one native H5P theme dropdown appears above the first eligible
activity and controls eligible DIV and same-origin internal-iframe activities on
the page. Unsupported activities do not trigger a selector. It restores a valid
session choice, otherwise Site default. The options remain distinct:

- Site default follows the administrator's configured global theme.
- H5P Core defaults explicitly uses native Core colors, independently of that
  global theme; it is not Daylight.
- Daylight, Lavender, Mint, Sunset and Dark are the five built-in palettes.
- Every valid saved custom theme is available, with existing inheritance.

Core restores actual native root values, including when global CSS is present;
sparse schema-1 customs inherit Core, while schema-2 customs inherit the pinned
base. Choices update colors without navigation, reinitialization or changing
answers/scoring. The implemented session preference remembers a valid catalog
choice through same-tab navigation and reloads using
sessionStorage when available. Selecting Site default removes the preference;
selecting Core explicitly remembers Core independently of the administrator
default. Stored custom machine IDs survive label changes; unavailable/deleted
choices fall back to Site default and are removed on restoration when possible.
The native select has an associated label and polite status region; source/tests
check control identity and ordinary focus preservation. Initial pending playback
does not expose a selector; the visible control reports applied or partial support.
Keyboard-only and assistive-technology acceptance still require manual qualification.
Storage failure leaves page-only switching usable.
No account, cross-device or durable cross-session preference is implemented, and
H5P answers are not persisted by Themer. Opener-created tabs may copy a preference,
and browser session restoration may retain it. Disabled learner selection does
not access storage or apply a saved preference; an existing entry can remain
until the feature is re-enabled. See [the historical session implementation
record](phase-2c-step-5.md) for the contract and earlier checkpoint scope.

External/cross-origin embeds, editors, administrator previews and unsupported
nested/form contexts are excluded. Delayed frames and later DOM activities are
handled with load listeners and targeted observers, without polling. Unavailable
learner JavaScript leaves global styling active. H5P still needs its own scripts.
New server catalog delivery detects deleted choices; open pages do not poll for
deletions or administrator changes. See [the historical switching architecture](phase-2c-step-4a.md).
Phase 2C functionality has been transferred and passed the user-confirmed Firefox
checks below; this does not establish stable production or universal library support.

The combined administration output fingerprint includes the enabled catalog and
all palette bundle dependencies. Changing a selectable palette invalidates cached
page/layout output once, through the existing mechanism. Disabled behavior is
preserved. No invalidation occurs during a learner switch.

## Deletion, failures and concurrency

Built-ins are immutable code data and have no administration deletion path.
Custom deletion uses native confirmation, permission/CSRF checks and the
module write lock. It is rejected if the object is selected by either the
stored or effective global default, whether the reference is legacy or typed.
Invalid effective settings block deletion conservatively. A built-in default
does not protect an unrelated same-name custom object.

Source snapshots, current references and overrides are rechecked on submit,
including under the lock. A default selected or overridden after a confirmation
form was built remains protected. Malformed custom definitions stay visible
for validated repair; forms do not silently repair or delete them.

When H5P is missing/disabled or its API is not exactly 1.28, definitions and
built-ins remain inspectable while mutations are disabled and rejected at
submit time. Config failures or busy locks yield errors without success
redirects. Locks are released on validation/storage failures.

The lock coordinates these administration mutations, not concurrent native
imports or manual filesystem edits. Native Config storage remains responsible
for persistence. Multi-object import is not transactional, and no atomic
cross-process behavior or real filesystem concurrency is claimed from unit
tests. Existing final-state import reference validation remains active.

## Verification and limitations

The full `tests/run.php` runner uses native Backdrop Config/import and Form API
definitions with memory-only storage, permissions, sessions, locks and caches.
It does not bootstrap the site or connect to a database. Warnings/deprecations
fail the run. Coverage includes both schemas, exact preset inspection and copies,
typed defaults, sparse inheritance, forged fields, tokens, stale forms, overrides,
selected-theme deletion protection and storage/lock failures.

Node tests exercise the production picker and preview behaviors with DOM/event
doubles and installed Backdrop once/collapse scripts. They are not a browser
emulator. Set `BACKDROP_SOURCE_ROOT` and `PHP_EXECUTABLE` as described in the [README](../README.md).
The complete Phase 2C totals are 1,854/1,854 PASS with PHP 7.4.33, 1,854/1,854 PASS
with PHP 8.4.22, and 291/291 PASS with Node.js 22.23.1, all with zero failures.
Learner tests use the actual
PHP-produced palette contract and check frame readiness, fallback, cascade and
activity identity. The user confirmed Firefox Step 5E **10/10 PASS** and Step 6E
**9/9 PASS**. Step 6E covered True/False selection/switching, legacy Image Choice
Rounds selector hiding, session persistence, multiple activities, answer continuity,
Core versus Site default, Copy of Mint and no new Firefox Console errors.

The user separately confirmed manual Firefox PASS results for saved previews,
unsaved live edits, invalid input handling, inheritance/reset, multiple overrides,
native picker synchronization and JavaScript-disabled fallback. A 375px viewport
was usable but cramped in administration/preview. That does not qualify the
learner selector's narrow/mobile layout. Keyboard-only operation, screen-reader
announcements, control-removal focus, RTL/long translated labels, Chromium, Safari,
restricted-storage environments and broader libraries remain incompletely qualified.
This is local functional evidence, not WCAG certification or universal browser
support. No browser checks were run during publication corrections.
Historical phase records retain their earlier
checkpoint-specific totals and pending-test statements.

## Global runtime colors

Choosing a global theme makes its supported colors available to compatible H5P
playback after reload. With learner selection disabled, Core/null means no
Themer overrides, generated asset or public-directory creation. Enabled supported
playback loads the immutable learner bundle even when global Core is selected. Built-ins use exact catalog values; schema-1
customs use stored sparse overrides; schema-2 customs use the pinned base and
sparse overrides. Unresolvable global references or incompatible APIs add no
overrides. If learner preparation fails, the existing global path remains available
when it can be resolved and published safely; unusable assets are not attached.
Failures do not silently repair configuration.

CSS is scoped to `.h5p-content`, delivered through the H5P styles hook in div,
iframe and external playback modes, and excluded from editor mode. Only variables
consumed by a library affect it; authored/hardcoded colors can remain unchanged.
External embeds must be served through the compatible integration on the
Themer-enabled site. The parent page cannot theme an unrelated remote provider.
Multiple/nested players, cross-origin boundaries, fullscreen and independent
caches need context-specific qualification.

Generated public CSS is immutable derived cache data named with serializer
version and CSS hash. Verified files are reused; lock contention may leave a
request temporarily unthemed. Successful global-reference or selected-custom
color changes invalidate native page/layout caches. The enabled learner UI also
invalidates for its toggle, displayed catalog and any selectable palette changes.
No-op and rejected saves do not. Open pages retain their server-rendered global/catalog snapshot until refresh;
cache invalidation does not subscribe them to administrator changes. Existing imports
retain native cache handling; direct programmatic changes require caller-managed
invalidation. Stored density remains editable but is not applied.

Code deployments that change the fixed-path learner JavaScript require the
[targeted JS/output cache procedure](installation.md#deployment-cache-refresh).
It prevents new content-addressed learner CSS from being paired with a stale
controller. To stop Themer overrides, select global Core and disable learner
selection, then save/reload. Explicit Core in the learner control does not disable
the feature or change administrator settings.

## Saved Theme Preview

Built-in View and existing custom Edit pages show administrator-only representative
samples in a script-free sandboxed iframe. A separate Core-defaults comparison
uses the actual installed Core variable stylesheet with no Themer overrides.
Built-ins resolve their immutable preset values; custom previews resolve saved
stored data from either schema. The unsupported extra catalog property is not
emitted. No actual H5P activity is created or edited by this sample.

The frame embeds trusted fixed Core/sample CSS, not remote fonts or activity
assets, with a restrictive CSP and no `allow-scripts`. Rendering does not write
configuration, content, caches or runtime CSS files. Density is not applied.
The appearance is representative and cannot certify every library's rendering,
contrast, keyboard behavior or accessibility.

## Live Preview of unsaved custom colors

Custom Edit starts from the saved theme. Parent-side JavaScript updates only
the dedicated override style block as valid authoritative hex fields change.
Native picker changes synchronize through those same textfields. Multiple valid
overrides combine; labels distinguish saved colors from an unsaved draft.
The iframe document, Core/sample styles, Core comparison, CSP and sandbox do not
change for a keystroke.

Blank restores exact pinned inheritance for schema 2, including trusted
expressions; schema-1 blank removes the declaration for the actual Core cascade.
Invalid/incomplete input is left in its textfield, retains the field's last valid
preview color and displays an explanatory message. Server validation still
rejects invalid values on Save.

Reverting colors or native form reset restores the corresponding form defaults.
Reset is observed; the module does not add a separate reset button. A cancelled
reset remains cancelled. Preview updates do not submit, save, fetch or publish
runtime CSS. Only the normal explicit **Save** action can persist validated edits.

With JavaScript disabled, the saved preview and Core comparison remain available.
Hex textfields and fieldsets stay accessible, and PHP still validates and saves.
Unsaved live updates require JavaScript. Native color inputs are enhancements,
not required submission values.

See the [installation guide](installation.md), [preview development record](phase-2b-preview.md)
and [implemented Phase 2C / planned Phase 2D](roadmap.md).

## Compatibility-based selector visibility

A learner selector is shown only after at least one registered native activity
confirms theme compatibility and stylesheet readiness. The controller recognizes
the existing library-owned `.h5p-theme` class on that player's own direct
`.h5p-container`; a class in nested content or an unrelated activity does not
qualify it. Legacy/unmarked libraries may remain unrecognized even if they can
consume some theme variables. There is no library-version allowlist.

One control themes eligible DIV and validated internal-iframe players. Ineligible
DIV siblings and iframe documents retain normal administrator-global styling.
The control disappears when no eligible activity remains, and can reappear for
a late or newly compatible player without H5P restart or navigation. Session
preferences are not read or deleted on incompatible-only pages; they can be
restored when eligible playback returns. Editors and admin previews remain
excluded. This visibility behavior is implemented and included in the recorded
Firefox acceptance results. See [the historical Step 6 record](phase-2c-step-6.md)
for its original isolated scope and conservative detection limitations.
