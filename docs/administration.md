# Theme administration

SPDX-License-Identifier: GPL-2.0-or-later.

The implemented module provides immutable built-ins, both custom-theme schemas,
native picker companions, global color runtime and saved/unsaved theme previews.
Native Density is optional and disabled by default; see [Optional native Density](#optional-native-density). See [installation and upgrades](installation.md)
and the [prepared v0.4.1 release notes](release-notes-v0.4.1.md).

## Optional native Density

H5P Core API 1.28 provides Small (compact), Medium (intermediate) and Large (spacious),
using native classes on the verified `.h5p-content` root. Large does not promise
increased container width. Only **qualified top-level H5P.TrueFalse 1.8.24 iframe
players** are supported initially. Other types/versions, nested, DIV, external and
ambiguous players retain original Density. Colors and the existing learner picker
continue under their separate compatibility rules. Learner Density is a button
group beside the existing theme picker, not another theme selector.

Activation requires the site setting `h5p_themer_native_density_enabled` to be
boolean TRUE. Absent/false/invalid values keep the feature OFF without changing stored
theme Density. Follow [activation, cache refresh and rollback](installation.md#v021-to-v040-development-upgrade-and-rollback).

| Existing selection | Native Density in theme mode, when enabled and qualified |
|---|---|
|Custom theme|Saved profile|
|H5P Core defaults|Restore that player's original native profile|
|Built-in theme|Restore original; current built-ins supply no override|
|Site default|Follow effective Global Default Density, even a hidden custom default; restore original if no override|

Reload after server configuration changes. Open pages retain their metadata; no
live remote switch is provided. No theme-schema/database migration is required.
Additional libraries need real browser qualification. Administration preview still
shows colors only; Live Density Preview is deferred unless separately authorized.

### Independent learner Density (v0.4.1)

With learner theme selection enabled, Small / Medium / Large native buttons display
progressively sized T glyphs. An explicit learner Density override takes precedence
over the current theme's saved Density, then each player's original native Density.
Theme and color switching, including Core and immutable built-ins, keeps that
override. **Use theme Density** resets it against the currently selected theme.
There is one shared preference for the installation and browser tab; each qualified
player retains its own original profile. Saved theme definitions are not modified.

The controls appear only when the native controller reports usable, ready, qualified
TrueFalse players. Mixed pages change only qualified players; unsupported-only pages
hide Density controls while preserving the existing theme picker's behavior.
An all-null Core/built-in catalog still permits learner Density choices. With learner
selection disabled, saved-theme Density continues under the v0.4.0 rules above.

The independent sessionStorage preference survives same-tab reload/navigation.
Reset removes only the Density key. Invalid values fall back to theme mode;
storage failures leave current-page choices usable and may retain older stored
data. This is not H5P answer saving, account storage or cross-device persistence.

Native buttons expose Small Density / Medium Density / Large Density names and
aria-pressed. In theme mode all three are unpressed; reset remains available.
Visible focus and double-border/underline selection cues do not rely on color.
Mode and scope descriptions and a dedicated polite announcement region are visually
hidden. Keyboard, 200% zoom and narrow layout passed user-reported Firefox testing;
Windows forced colors and screen reader were not tested. Native Small and Medium
can share text sizes while their spacing/scaling differs. Broader qualification
is not implied. See [current readiness](v0.4.1-readiness.md).

## Routes and access

All paths start with `admin/config/content/h5p-themer`:

| Suffix | Purpose |
| --- | --- |
| (none), `/list` | Core, built-in and custom overview |
| `/add` | Create an independent custom theme from the effective Global Default |
| `/<custom-id>/edit` | Edit custom text, density and sparse overrides |
| `/<custom-id>/duplicate` | Copy a custom theme under a new custom ID |
| `/<custom-id>/delete` | Native custom deletion confirmation |
| `/default` | Global Default and learner-selection toggle |
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

The overview includes a Site default row and three theme groups. The Core choice
in Global Default is **H5P Core defaults (no overrides)**; the overview and
learner selector abbreviate it to **H5P Core defaults**:

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
Missing/invalid defaults never silently change the global selection. Add theme
uses an explicitly identified Daylight starting palette when necessary.

A built-in View page shows its label, ID, version, density information,
provenance, license and all 34 exact values. Expressions such as `color-mix()`
and `rgba()` are escaped text inside code elements on the inspection page. Runtime delivery
uses exact trusted catalog values for validated global and enabled learner themes.
Saved samples and a Core comparison are available. The extra `--h5p-theme-contrast-cta-dark` value is
preserved and explicitly marked as lacking established Core 1.28 runtime
support. The palette itself defines no density override.

## Creating and editing custom themes

| Workflow | Starting data and blank-field behavior | Result |
| --- | --- | --- |
| Add | Effective Global Default; blank retains captured starting colors | Independent custom theme, hidden from learners |
| Edit | Stored saved overrides; clearing restores pinned-preset/Core inheritance | Same ID and schema; existing visibility preserved |
| Duplicate (custom) | Stored original custom definition; blank removes an override | Independent new ID, same schema/pin, hidden |
| Preset Duplicate | Built-in Duplicate action; initial palette is read-only | New schema-2 ID with exact pin and empty overrides, hidden; Edit afterward |

Add theme starts from the **effective Global Default**, including validated
top-level settings.php overrides. It displays the actual starting theme name.
**H5P Core defaults (no overrides)**/null, missing, invalid or unresolvable
defaults use the explicitly identified
**Daylight fallback**, pinned to `moodle-478fed30d2a4`. This affects only the new
theme's starting definition; it never repairs or changes global settings.

A built-in default creates a schema-2 seed with that exact preset/version and
no stored overrides. A schema-2 custom default retains its preset pin and copies
its effective sparse hexadecimal overrides and density. A schema-1 custom
default retains schema 1 and copies its effective sparse overrides and density.
Built-in palettes have no density, so their initial density is `large`.

On Add, color inputs start blank and show captured starting values in their
descriptions and available pickers. **Blank retains the captured starting color**;
enter `#RGB`/`#RRGGBB` or choose an available picker to replace it. Starting custom
overrides are copied on Save even when the inputs remain blank. Expressions stay
in the pinned preset and are never flattened into editable configuration.
Save creates an independent custom theme, leaves the source unchanged, and keeps
the new theme hidden from learners. Later edits/deletion of a source custom
theme cannot affect the saved copy. Publication still requires the separate
existing visibility controls.

The starting source, colors and dependency fingerprints are captured in trusted
cached form state, not configuration or hidden submitted fields. Add explicitly
enables Backdrop's native Form API tempstore cache, including its session-token
access check and configured form-cache expiry. A POST without its original valid
cached snapshot is rejected with instructions to reload; it never initializes a
replacement source. Rebuilds retain
that snapshot and submitted input. Validation, locked submission and the final
pre-save check reject changed/default/source dependencies instead of rebasing.
Reload Add theme to start again after a dependency changes.
Valid description-only edits and learner-selection-toggle changes do not stale
the starting palette; appearance, source identity and validation status do.

All creation paths reject an ID already referenced by a missing custom global
default, considering both stored settings and effective settings.php overrides.
Choose another ID, or repair the default explicitly through its separate controls.
This prevents a new definition from automatically activating a previously broken
global default. Built-in/custom namespaces remain distinct, and unrelated IDs
remain available. The reference check runs again under the lock and before save.

Each new theme requires a unique stable ID, editable label,
description, density and optional sparse hex overrides. IDs match
`[a-z][a-z0-9_]{0,63}`. Editing cannot change the machine ID. Label renaming
preserves all references.

Schema-1 editing preserves schema 1, sparse maps and exact hex spelling/case.
No migration or palette derivation occurs.

Schema-2 Edit forms display the pinned base ID/version as read-only information.
Each of the 33 existing override fields separately identifies the inherited
value and whether a saved administrator override exists. Inputs contain only
stored overrides, never the effective palette. Blank means inherit the pinned
base; clearing a saved override restores inheritance after Save.
Schema-1 Edit fields distinguish saved overrides from the Core fallback; clearing
an override removes its declaration. Previous custom-copy provenance is not
stored or inferred. Edit never claims live inheritance from another custom theme.
Its blank-field behavior deliberately differs from Add's captured starting colors.

The inherited extra property is displayed read-only, and inherited non-hex
expressions remain exact. Only opaque `#RGB` or `#RRGGBB` can be entered as
custom overrides. Whitespace, alpha hex, functions, declarations, unknown
properties and injected top-level fields are rejected server-side.
Form API's NULL placeholders for omitted declared fields remain unset;
unknown submitted color keys are rejected even when their values are blank.

Saved colors are never silently normalized. Schema, pinned base and native
metadata come from trusted stored form context rather than posted fields.
Edit and Duplicate use originals, not environment-specific effective values.
Editing an object with settings.php overrides is blocked until the overrides are
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

On Add, blank text displays the captured starting color; on schema-2 Edit and
Duplicate, blank text inherits the exact pinned preset. Representable
inherited hex appears in the picker without becoming a saved override.
Add status says "Starting color; no new override"; Edit/Duplicate retain their
inherited-color wording. The starting custom overrides are copied on Save.
The exact inherited value remains visible in the existing description.
Inherited rgba() and color-mix() expressions are neither approximated nor
copied into editable fields. If no representable inherited hex exists, a blank
or invalid text value disables the picker until valid hex is typed. Its disabled
swatch is explicitly identified as not being a preview. If inherited hex is
available while text is invalid, the picker shows that inherited hex and
explains that choosing a color will replace the invalid text.

Schema-1 Edit/Duplicate blank retains Core/no-override semantics. No preset is
assigned or substituted. On Add from a sparse schema-1 custom default, a field
without a captured color has an enabled
picker starting at black for choosing a color. This is a UI-only starting value,
not a Core preview or saved override; the text remains blank until a color is
chosen. Clearing or resetting an Add field restores its captured starting state. Existing
schema-1 edit/duplicate forms still require explicit valid hex to enable a picker
when no inherited hex is available.

Disabled/read-only fields remain guarded. Without JavaScript, or without native
color-input support, ordinary hex textfields and existing server validation
continue to work. With JavaScript disabled, native collapse CSS leaves every
fieldset visible. No external picker, custom CSS or additional dependency is
required. Initial built-in duplication stays metadata-only with a read-only
palette; color pickers appear when editing the saved custom copy.

### Add verification and browser checks

The focused native PHP regression entrypoint is:

```text
php -n tests/add-theme.php --backdrop-root="<absolute-Backdrop-source-root>" --h5p-root="<absolute-H5P-module-source-root>"
php -n tests/admin-color-form.php
```

The first command includes existing admin, schema, preset-copy, form enhancement
and publication regressions as well as the new Add cases. The full
`tests/run.php` also includes Add coverage. Both use isolated fixture storage;
neither bootstraps a site or changes an installed module. Runtime filesystem
tests additionally require temporary-file rename permissions.
Native cache-lifecycle cases run GET, POST and correction in separate PHP
processes with a private server/tempstore fixture. They cover expiration, missing
or invalid state, session mismatch, CSRF, forged input, stale sources, harmless
source/settings edits and missing-default ID collisions.

User-confirmed Firefox checks covered starting-theme instructions, native pickers,
creation/editing, saved colors, default-hidden creation, independent copying of a
customized Global Default and Daylight fallback from Core. The following broader
checks remain the acceptance checklist for a test deployment; this documentation
does not claim they have all passed:

1. Open Add with each built-in default, both custom schemas, a settings.php
   override, Core/null and a broken default. Verify the actual starting name,
   explicit fallback explanation, blank inputs and correct starting swatches.
2. Check blank Save, typed hex, picker choices, clearing and native Reset.
   For Add, blank retains the captured custom override; after saving, Edit
   clearing restores the pinned preset or Core. Verify expressions remain exact,
   and expression-only swatches explain why their picker is unavailable.
3. Trigger validation errors and rebuilds. Verify entered text, starting name
   and swatches remain stable. Change the source/default in another session;
   verify Save refuses the stale form and requires reloading Add.
4. Confirm Edit names the pinned preset/version and distinguishes saved overrides
   from inheritance without inventing a custom source. Check legacy Edit,
   custom Duplicate and preset Duplicate behavior.
5. Verify keyboard/screen-reader labels and instructions, collapsed error groups,
   no-JavaScript operation and browsers without native color inputs. Check narrow
   layouts and long/translated theme names.
6. Confirm a new copy is absent from learner choices, while source visibility and
   the global default remain unchanged. Visibility must require the existing
   separate publication action. Automated fixture tests do not establish browser
   or database-backed deployment behavior.

## Duplicating a built-in (Preset Duplicate)

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
Every copy requires a new independent custom ID. Saving creates the new
definition and its hidden visibility entry, leaving the source and Global Default
unchanged. Stale/changed sources and
post-build ID collisions are rejected.

## Selecting and clearing the global default

Open **Global Default**. Its selector lists **H5P Core defaults (no overrides)**,
five built-ins and all valid customs, including themes hidden from explicit
learner choices. Internal
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

## Learner visibility and publication

The Themes overview has **Available to learners** checkboxes and one explicit
**Save visibility** action. Changing a checkbox does not autosave. Site default,
Core and Daylight are checked, disabled fixed choices whenever the validated
learner catalog is usable. Optional valid built-ins and custom themes appear
only when their saved visibility entry is true. Invalid/unresolvable definitions
have diagnostics rather than a usable publication control.

Add, Duplicate and Preset Duplicate create hidden custom themes. Editing keeps
the existing visibility choice. Deletion retains a false tombstone, and recreation
of the same ID stays hidden. Hidden definitions remain editable and previewable;
they can still be Global Default and apply through Site default. This controls
learner choice availability, not access to H5P content or secrecy of palette data.

Fresh installation initializes optional built-ins as available and customs as
hidden. First-time upgrade activation initializes existing resolvable optional
choices as available without enabling learner selection. Once activation is
complete, updates/retries preserve the saved policy, including hidden entries.
Missing/damaged policy or incomplete activation after the persisted barrier hides
optional choices and blocks publication; page reads do not repair it. Follow the
[upgrade guide](installation.md#upgrading-an-existing-installation).

Visibility writes require compatible H5P, completed activation, trusted form state,
no conflicting policy/catalog overrides, and the publication lock. A concurrent
hide invalidates an old submission. A server-side deletion generation also retires
old visibility forms even when a same-ID replacement has identical data. Reload
after stale/expired context; ordinary input corrections retain the original state.
Only visibility is saved by Save visibility; definitions and Global Default do not change.

Provisional v0.2.1 supports creation of one schema-2 custom theme through
Configuration manager → Single import/export → Import. Both native configuration
and H5P theme administration permissions are required. Imported themes stay hidden
until explicitly published here. Existing-theme updates, deletions, bulk theme
synchronization and visibility-policy imports remain rejected. Settings-only and
unrelated imports and ordinary exports remain available. See the
[single-import contract](configuration.md#provisional-v021-single-schema-2-theme-creation).

## Administrator learner-selection setting

**Global Default** contains **Allow learners to select their H5P theme**. It saves the
optional strict Boolean learner_selection_enabled, false by default. Missing is
false in settings schemas 1 and 2; toggling preserves the schema/global reference.
Effective settings overrides, permissions, native CSRF, stale checks and locking
apply. Per-theme publication is controlled separately by the overview's
**Available to learners** checkboxes; enabling learner selection does not publish
a hidden theme.

When enabled, one H5P theme dropdown appears above the first eligible
activity and controls eligible DIV and same-origin internal-iframe activities on
the page. Unsupported activities do not trigger a selector. It restores a valid
session choice, otherwise Site default. The options remain distinct:

- Site default follows the administrator's configured global theme.
- H5P Core defaults explicitly uses native Core colors, independently of that
  global theme; it is not Daylight.
- Daylight is fixed; Lavender, Mint, Sunset and Dark require publication.
- Valid saved custom themes require publication, with existing inheritance.

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
does not expose a selector. Ordinary successful switching leaves the live status
empty and hidden; explanatory text is in the compact **?** help panel. Partial
support and fallback warnings stay visible in the polite live region outside
collapsed help. The help button has an intentional 24px minimum target size.
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
Recorded Firefox checks are described below; they do not establish universal
library, browser or assistive-technology support.

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

Add, Edit, custom Duplicate, Preset Duplicate, Global Default, Delete and Save
visibility retain trusted original context in native session-bound Form API
tempstore across GET/POST and rebuilds. Missing, expired, malformed or wrong-session
context fails closed; a POST never silently captures a new original. Relevant
source/settings/catalog/policy changes reject stale submissions. Normal validation
corrections retain entered values and the original context. A failed submit may
require a reload if its original cache is no longer available; no retry captures
a replacement snapshot.

Source snapshots, current references and overrides are rechecked on submit,
including under the lock. A default selected or overridden after a confirmation
form was built remains protected. Malformed custom definitions stay visible
for validated repair; forms do not silently repair or delete them.

When H5P is missing/disabled or its API is not exactly 1.28, definitions and
built-ins remain inspectable while mutations are disabled and rejected at
submit time. Config failures or busy locks yield errors without success
redirects. Locks are released on validation/storage failures.

The lock coordinates these administration mutations and native single-theme
creation, but not generic native imports or manual filesystem edits. Native Config storage remains responsible
for persistence. Multi-object import is not transactional, and no atomic
cross-process behavior or real filesystem concurrency is claimed from unit
tests. Settings-only import reference validation remains active; generic theme
synchronization and visibility-policy imports are rejected. Creation/deletion can first persist a restrictive policy
entry; deletion also updates the server-side visibility-form marker. These are
not atomic multi-object transactions. A failure may leave a hidden entry or a
definition already written/deleted. Inspect stored state before retrying; there
is no automatic rollback that restores learner visibility.

## Verification and limitations

The full `tests/run.php` runner uses native Backdrop Config/import and Form API
definitions with memory-only storage, permissions, sessions, locks and caches.
It does not bootstrap the site or connect to a database. Warnings/deprecations
fail the run. Coverage includes both schemas, exact preset inspection and copies,
typed defaults, sparse inheritance, forged fields, tokens, stale forms, overrides,
selected-theme deletion protection and storage/lock failures.

Node tests exercise the production picker and preview behaviors with DOM/event
doubles and installed Backdrop once/collapse scripts. They are not a browser
emulator. Set `BACKDROP_SOURCE_ROOT` and `PHP_EXECUTABLE` as described in the
[developer source-only checks](v0.4.0-readiness.md#developer-source-only-checks).
The accepted complete Checkpoint 3C Stage 1 baseline is **3,793 passed, 0 failed**
on each of PHP **7.4.33**, **8.3.18** and **8.4.22**, and **470 passed, 0 failed**
on Node.js **22.23.1**. PHP includes 325 separate-request administration lifecycle
assertions and 54 readiness assertions. Later Checkpoint 3C Lite passed 46 focused
assertions and all seven manual Firefox acceptance tests. A separate actual
Backdrop module disable/re-enable passed 85 focused checks without uninstalling;
this is distinct from Density-setting OFF/ON. See [current evidence](v0.4.0-readiness.md).
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
learner selector's full narrow/mobile layout. Checkpoint 3B later covered narrow
windows and 200% zoom for its qualified Firefox fixture. Keyboard-only operation, screen-reader
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
invalidation. With native Density enabled, effective Density-only changes invalidate its metadata separately, without changing color CSS hashes.

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
configuration, content, caches or runtime CSS files. Native Density is not applied in this representative color-only preview; live Density preview is deferred to Checkpoint 3D.
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
and the [development roadmap](roadmap.md).

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

## Two-color learner dropdown — independent integration

The optional select-only combobox adds two decorative colors before each name,
including inside the expanded list. The native select remains canonical and is
the fallback when metadata, styles or required browser capabilities are missing.
Only one presentation is focusable and exposed to assistive technology.

Arrows, Home/End and type-ahead explore without applying a theme. Enter/Space or
clicking a row commits through the existing native change handler exactly once.
Escape, outside click and focus loss cancel exploration. Tab/Shift+Tab commit an
open candidate and allow normal focus movement. A check identifies the committed
row; an outline identifies the active candidate. Session restoration never
simulates a user selection. Forced-colors mode suppresses decorative swatches.

This refinement is integrated and regression-tested in an independent Git clone.
It passed **12/12 user-confirmed initial Firefox functional checks** in the
disposable test environment; the compact layout was subsequently accepted in
Firefox, with all nine themes visible without scrolling at normal zoom. Desktop
option rows have a 32px minimum; coarse-pointer option rows retain a 44px minimum.
Those are recorded checkpoint results, not a claim about the current state of
every installation. NVDA, touch, forced-colors, RTL, high zoom and other-browser
verification remain limited; these results do
not establish full WCAG compliance. See the [implementation record and manual
checklist](phase-2d-learner-icons.md) for acceptance evidence and remaining limits.
