# Configuration contract — schemas 1 and 2 and learner selection

> v0.4.3 release metadata is prepared, not packaged or published. Native Density
> qualifies only standalone official H5P.TrueFalse 1.8.24, H5P.MultiChoice 1.16.27,
> H5P.Blanks 1.14.37 and H5P.MarkTheWords 1.11.24 internal iframe players on Core
> API exactly 1.28. Other versions, PapiJo and embedded compound activities remain
> excluded. Colour-theme eligibility is separate from Density qualification.
> The permitted-colour selector and theme-only **Use site default theme** Reset
> preserve independent Small/Medium/Large preferences. The current administrator
> default is followed when no explicit learner colour preference exists.
> See [release scope and upgrade information](release-notes-v0.4.3.md) and
> [19 user-reported Firefox passes](v0.4.3-markthewords.md).
>
> Historical v0.4.2 contract: [independent Density and earlier migration rules](v0.4.2-development.md).
> Native Density retains its existing checkbox/PHP override and has no visible
> Density reset button; the separate theme Reset clears only the theme preference.
> The older version-specific material below remains historical.


SPDX-License-Identifier: GPL-2.0-or-later.

The schema-1 contract remains supported. The implemented schema-2 extensions
add pinned bases and typed references without forcing migration of schema-1
themes. Both schemas are supported by native forms, previews and global colors.
See [administration](administration.md), [installation and upgrades](installation.md)
and the [prepared v0.4.1 release notes](release-notes-v0.4.1.md). The
[schema-2/read-API record](phase-1c-step-2a.md) preserves historical design evidence.

<a id="native-density-activation-v040-development"></a>

## Native Density activation (v0.4.0 and v0.4.1)

The existing `density` values `small`, `medium` and `large` and serialized schema-1/2
objects are unchanged. Density is independent of a pinned preset's color inheritance.
Saved Density alone does not enable runtime changes. The site-level setting
`$settings['h5p_themer_native_density_enabled'] = TRUE;` must be boolean TRUE; it is
not a new theme/schema field and must not be added to exported theme JSON.

Only qualified top-level H5P.TrueFalse 1.8.24 iframe players on Core API 1.28 currently
apply custom saved profiles in theme mode. Core/built-ins restore original; Site default follows
effective Global Default, including hidden custom defaults, or restores original.
Unsupported players retain native Density; existing color/learner behavior remains
independent. No migration is required. See [upgrade/cache/rollback](installation.md#v021-to-v040-development-upgrade-and-rollback)
and [current readiness](v0.4.1-readiness.md); preview remains color-only and
Live Density Preview remains deferred.

v0.4.1 adds independent learner Density controls only when learner theme selection
is enabled and qualified players are ready. Small / Medium / Large T buttons
request native profiles without changing colors or saved configuration. Precedence:
learner override, then current theme's saved Density, then each player's original
H5P profile. Core/built-in theme changes preserve an explicit learner override.
**Use theme Density** clears it and resolves the current theme again. All-null
theme catalogs still deliver learner capability; disabled learner selection keeps
the existing global-only suppression when there is no saved Density.

## Stored objects

`h5p_themer.settings.json` starts as:

```json
{
  "_config_name": "h5p_themer.settings",
  "schema_version": 1,
  "default_theme": null,
  "learner_selection_enabled": false
}
```

`null` means no selected theme. A missing field, empty string, invalid ID, or
missing settings object is an error, not another spelling of that state.

The optional `learner_selection_enabled` key must be a JSON Boolean when
present. Missing means false in settings schemas 1 and 2. Integer/string/null
values are invalid. It belongs only to settings; custom theme schemas do not
change. Effective top-level settings.php overrides use the same strict validation.
`h5p_themer_learner_selection_enabled()` returns false for missing/invalid settings
and never saves or migrates. This administrator setting enables learner switching
for eligible activities. Implemented browser tab-session persistence adds no
configuration schema migration, learner-account preference or server-side storage.
Enable it through Global Default; it is optional and false by default.

An example definition for `h5p_themer.theme.ocean.json` is:

```json
{
  "_config_name": "h5p_themer.theme.ocean",
  "schema_version": 1,
  "id": "ocean",
  "label": "Ocean",
  "description": "Example partial palette, not an accessibility certification.",
  "density": "large",
  "colors": {
    "--h5p-theme-main-cta-base": "#006FBF",
    "--h5p-theme-background": "#F9FBFF"
  }
}
```

This example is documentation, not a shipped theme. Definitions are native
Backdrop configuration; no custom table or per-content/user assignment exists.

Schema-2 themes retain the same identity/label/density fields, integer
`schema_version: 2`, an exact `base_preset: {id, version}` such as
`{id: "mint", version: "moodle-478fed30d2a4"}`, and a sparse hexadecimal `colors`
map. Inherited preset expressions remain in immutable code data, never in the
custom override map. Preset pins do not change when the module release changes.

In settings schema 1, a non-null `default_theme` is a custom ID string. In schema
2 it is a typed `{kind: "custom", id}` or `{kind: "builtin", id, version}`
reference; null still means **H5P Core defaults (no overrides)** in **Global Default**.
The built-in and custom namespaces are distinct even when IDs match. Existing
theme/settings schemas are not migrated merely by viewing or editing a theme.

### Learner visibility and environment state

`h5p_themer.learner_visibility` is separate native configuration, created by
explicit installation/update activation rather than shipped as a default policy.
It has integer `schema_version: 1` and a `themes` map of optional typed tokens
such as `builtin:mint` and `custom:ocean` to strict Boolean values. Fixed choices
`site_default`, `core` and `builtin:daylight` are not editable policy entries.
After activation, an absent/false optional entry means hidden. Invalid policy or
incomplete activation after the persisted barrier fails closed for optional choices.

Before first activation, absent policy preserves the legacy unfiltered catalog.
Upgrade activation initializes existing resolvable optional choices as available;
fresh activation makes optional built-ins available and any existing customs
hidden. Completed activation preserves later saved visibility, including false
entries. New/copied/recreated custom IDs are hidden; deletion retains a false
tombstone. No existing theme data is migrated. See the
[upgrade procedure](installation.md#upgrading-an-existing-installation).

The installed module schema witness and `h5p_themer.visibility_activation`
journal are environment state, not theme fields. The server-side
`h5p_themer.visibility_form_generation` state value retires old visibility forms
before deletion, including identical same-ID recreation. These state values are
not learner preferences or exported theme provenance; preserve database state
alongside configuration for recovery. GET requests never initialize policy or
write these state values; native form tempstore caching is separate.

## Validation rules

- For this legacy shape, `schema_version` is integer `1`; schema 2 has the
  separately documented pinned-base/typed-reference shape. Other schemas fail.
- IDs match `[a-z][a-z0-9_]{0,63}` exactly and equal the configuration filename
  suffix. Labels can change without changing IDs or references. Changing an ID
  inside an existing named object is rejected.
- `label` is nonblank UTF-8 text, at most 255 bytes, without control characters.
  `description` is optional UTF-8 text, at most 4096 bytes; newlines and tabs are
  permitted. Both are text, not trusted HTML; administration output is escaped.
- Density is exactly `large`, `medium`, or `small`, without the `h5p-` prefix.
- `colors` is a sparse map, possibly empty. PHP's JSON decoder represents empty
  JSON objects and arrays identically, so both empty forms are accepted.
- Only the 33 explicit color custom properties returned by
  `h5p_themer_color_properties()` are accepted. The tests compare that list to
  the inspected Core stylesheet. No runtime source parsing is used.
- Color values are opaque `#RGB` or `#RRGGBB`, case insensitive. No whitespace,
  alpha hex, named colors, RGB/HSL functions, variables, `color-mix()`, URLs,
  declarations, or arbitrary expressions are accepted in schema 1.
- The Core `contrast-cta-light` property is allowed, but an override must be a
  permitted hex color. Its upstream default expression is not copied into
  configuration. Omitted properties retain Core behavior in the global renderer.
- Unknown fields are rejected. Native `_config_name` (matching the object name)
  and boolean `_config_static` metadata are permitted. Core storage strips its
  own `_config_name` on read; the theme's `id` remains the identity authority.
- Typography, spacing, radii, custom CSS, assignments, and learner preferences
  are not part of this schema.

No normalization or automatic palette derivation occurs. The layer neither
silently repairs malformed data nor claims a sparse palette is accessible.

## Read API

`h5p_themer_config_load($name, $type = 'active', $original = FALSE)`,
`h5p_themer_theme_load($id)`, and `h5p_themer_default_theme_load()` return:

```text
array('data' => <validated array or NULL>, 'errors' => <translated strings>)
```

Configuration names and storage types are checked before access. Missing,
malformed, or unreadable configuration returns NULL data with errors. An unset
default returns NULL data without errors. A broken reference returns NULL data
with errors. No arbitrary fallback theme is selected and no write is attempted.

The Add administration form has a separate initialization policy: Core/null or
an unresolved effective default starts a new independent definition from pinned
Daylight (`moodle-478fed30d2a4`), with a visible fallback explanation. Existing
resolvers and runtime behavior remain unchanged. Add's source snapshot and
fingerprints live only in cached form state; no schema fields or migrations are
introduced. Custom sources retain their schema, effective sparse hex overrides,
density and preset pin where present, with no reference to the source custom ID.
Blank Add inputs preserve copied starting values when the candidate is built.
Existing Edit/Duplicate blank inputs still remove overrides as before.
Add enables native Form API caching and rejects POSTs whose original trusted
state is missing, expired or invalid. No snapshot data is accepted from client
fields. New custom IDs must not match unresolved stored or effective custom
default references; repairing a default remains an explicit separate action.

Effective reads use native `Config::get()` per top-level schema field, honoring
field-level settings.php overrides. Override `colors` as a complete map; dotted
child overrides inside the map are not supported by this accessor. Overrides
must pass the same schema checks. Import validation passes `$original = TRUE`
and uses stored data, excluding environment overrides.

Reads remain available during H5P compatibility failures so administration can
inspect definitions. Runtime rendering separately requires
`h5p_themer_compatibility()['compatible']` before applying anything. Neither the
read API nor the compatibility function applies themes.

## Shared theme catalog (Phase 2C Step 2A)

`h5p_themer_theme_catalog()` returns `data/errors`. On success, data is keyed by
`site_default`, `core`, `builtin:<id>`, then `custom:<id>`. Each entry has exactly
`label` (raw text, escape for the output context) and `reference`:

- Site default: `{kind: "site_default"}`, an in-memory catalog sentinel that
  follows the current global selection. It is not accepted as stored global
  configuration and does not snapshot Core or any theme's colors.
- Core: null, resolved by the existing selection resolver to no overrides.
- Built-in: the existing typed `{kind, id, version}` pinned reference.
- Custom: the existing typed `{kind, id}` reference, independent of its schema.

This full read-only catalog includes all five built-ins and every resolvable
saved custom, including hidden choices. It is not the learner publication list.
`h5p_themer_learner_catalog()` applies the activation-gated visibility policy for
learner projections. Customs use effective overrides and existing validation/resolution,
including pinned inheritance; they are sorted naturally by label, then ID.
Core, Daylight and a same-ID custom remain separate. No second palette resolver
is introduced. Call the existing `h5p_themer_selection_resolve()` for entries
other than Site default; handle the sentinel by reading the current global
selection with `h5p_themer_default_selection_load()` when needed.

The catalog includes no admin description, provenance, configuration metadata or
private diagnostics. Invalid individual customs are excluded without repairs;
enumeration failure returns null data and a generic error, not a partial list.
The catalog function adds no assets, UI, persistence or HTTP endpoint. The learner
runtime projects its labels/tokens and validated opaque palette keys into native page JS settings; its Daylight label omits
the catalog-only "(default)" qualifier without changing immutable preset data.
The read-only catalog has
no static or persistent cache and is independent of the enable flag. It reflects
normal Config saves without a catalog cache flush. Administration invalidates cached
page/layout output when the enabled UI flag/options or any selectable palette change; external low-level changes retain
the existing caller responsibility for native Config/cache freshness.

## Native configuration validation and import/export

`hook_config_info()` registers settings, visibility policy and the theme collection
(`name_key` is `id`, `label_key` is `label`). `hook_config_data_validate()` checks
object shape and identity. Settings import validation checks the selected custom
reference against the final proposed plan. Generic theme/policy synchronization
mutations are rejected; an import cannot replace a definition to repair that
reference.

Settings-only imports require any selected custom theme to exist and validate.
Generic create/update/delete imports of theme definitions and learner visibility
policies are rejected at both validation and application. Mixed plans containing
such mutations are rejected even when an unrelated object validates first;
unchanged entries do not block a plan. There is no separate import interface or
privileged coordinator bypass. Administration retains default-theme deletion
protection. Deleting settings remains permitted and leaves reads/status checks
reporting missing configuration; it does not recreate defaults.

Export uses Backdrop's ordinary configuration facilities and existing object
schemas. Settings-only and unrelated native imports retain their supported
behavior. Bulk theme import, existing-theme updates, theme deletions and all
visibility-policy imports remain unsupported. Backdrop sync is not a multi-file
database transaction.

<a id="provisional-v021-single-schema-2-theme-creation"></a>

### v0.2.1 single schema-2 theme creation (unchanged in v0.4.0)

The module alters Backdrop's existing `config_import_single_form` at
Configuration manager → Single import/export → Import. Paste one exported JSON
object with `_config_name: "h5p_themer.theme.<id>"`. The theme must use integer
schema 2 and a known exact `base_preset` pin. The ID must match the configuration
name suffix. Existing destinations, including malformed files, are rejected.
Schema-1 theme import is not supported by this creation-only handler.

Both `synchronize configuration` and `administer h5p themes` permissions are
required, together with compatible H5P and completed, verified visibility
activation. Destination theme or visibility-policy overrides and active config
sync block creation. Stored and effective Global Default references are checked;
creation cannot silently activate an unresolved reference. IDs, labels, density,
descriptions, sparse hexadecimal overrides and preset pins are not normalized.

The native form dispatches theme validation/submission to module handlers;
settings and unrelated imports retain the core handlers. Generic config import
guards remain intact. Under `h5p_themer_admin_write`, the handler repeats checks,
persists and verifies only the destination's restrictive `custom:<id> = FALSE`
policy entry, then saves and reads back the definition. All other themes,
settings, policy entries and activation state are preserved. Visibility remains
an explicit later administration action. Existing stale visibility forms reject
the changed catalog/policy.

Native Config request caches are refreshed and the existing output-cache
invalidation helper runs. Hidden, unselected creation normally changes no
delivered output and needs no page/layout flush. Partial policy changes that
affect output still trigger invalidation. Writes are not atomic: failures may
leave a restrictive tombstone or a saved hidden definition. Errors acknowledge
uncertain persistence; there is no automatic visibility restoration or rollback.
Arbitrary low-level writers do not share this lock. Native import, JSON round
trip, duplicate rejection, imported Edit colors and hidden learner visibility
have passed manual testing on a disposable Backdrop site; cleanup restored its
original module and guards. Broader native permission, policy/update/delete
restriction, unrelated-import and failure-path integration checks remain
outstanding. The existing Edit form embeds saved and live-color previews; there
is no dedicated Preview action, and the imported theme's embedded preview was
not manually tested.

**Native `Config::save()` does not automatically call `validateData()` or import
hooks.** Low-level callers can bypass validation, as with other Backdrop config.
Phase 1B form handlers explicitly validate shape and references before saving,
enforce permissions/compatibility, and protect default-theme deletion. The new
`h5p_themer_config_field_errors()` exposes the same validator by field name;
`h5p_themer_config_errors()` retains the flat Phase 1A interface and schema.
There is no general public CRUD service API. Direct filesystem edits likewise bypass import hooks;
the read layer still rejects invalid effective data.

## Compatibility and preservation

The enabled `h5p` module and its `h5p_autoload_info()` must be available.
`class_exists('H5PCore')` invokes the registered Backdrop loader. The check reads
the public static `H5PCore::$coreApi` without constructing a service. Integer
major/minor 1/28 are accepted; missing, malformed, negative, older, or newer
versions fail. Autoload/read failures fail safely. No bridge is checked.

Installation and status reports use `hook_requirements()`. Enable H5P first:
selecting both previously disabled modules in one installation action may need
two steps because the dependency must already be enabled at check time.
Compatibility failures preserve stored data and do not disable modules or
attempt upgrades. Phase 1B administration remains readable with mutations
disabled, and submit handlers recheck compatibility before writes. Optional learner controls share the validated resolvers and serializer; disabled
playback retains the existing global runtime helper.

## Administration writes

See [administration](administration.md). Edit, custom Duplicate, Global Default
and Delete use stored originals. Add deliberately reads the effective Global
Default and custom overrides to build its independent seed. Preset Duplicate
uses immutable code data and initially stores no overrides. Blank Add fields
retain starting colors; blank Edit/custom Duplicate fields remove overrides.
No creation workflow changes its source or selects a new Global Default.

Native session-bound Form API tempstore preserves original contexts across
GET/POST and rebuilds. Missing, expired, invalid or wrong-session context is
rejected; posted snapshot fields cannot replace server state. Source/settings
and publication dependencies are rechecked on submission and under the lock.
Ordinary validation corrections retain their original snapshot rather than
capturing the latest source. Visibility checks the stored snapshot against the
current catalog/policy and deletion generation, preventing a stale republish
after concurrent hiding or same-ID recreation.

Creation/deletion may first write a restrictive visibility entry and verify it
before saving/deleting the definition. Edit preserves visibility. Save visibility
writes only policy; Global Default writes settings. Deletion also advances and
verifies the environment-local visibility-form generation. No new theme schema
field or custom-parent reference is introduced.

Theme writes use the shared field validator and `Config::validateData()`.
Default writes additionally call the Phase 1A reference validator. Deletion
calls the Phase 1A deletion safeguard and also checks the effective default, so
a settings.php default override cannot silently become a broken reference.
Edit/Delete and Global Default mutations reject conflicting top-level overrides.
Custom Duplicate can copy a source's stored original without persisting its
environment-specific effective overrides.

All four submit handlers recheck validation under `h5p_themer_admin_write`.
The lock coordinates these administration forms and the single-theme creation
handler; direct filesystem edits and generic native config imports do not
participate in it. Native Config storage remains
responsible for filesystem write behavior. No cross-object atomic transaction
or coordination with concurrent imports is claimed. Failed writes/read-back may
leave restrictive policy or partially applied definition changes. There is no
automatic rollback that restores visibility. Inspect diagnostics and stored state
before retrying; settings-only recovery does not repair malformed theme/policy data.

Malformed definitions remain visible but cannot be edited/duplicated/deleted
through these forms. They require a separately planned configuration recovery;
neither the administration forms nor native theme/policy imports provide a repair
workflow. Missing or malformed settings require valid settings-only configuration
repair through Backdrop's native facilities. A valid settings object with a
missing referenced theme can be explicitly cleared/repointed in the UI.

An explicit future Backdrop uninstall is separate: normal Backdrop module
uninstallation may remove module-owned configuration. No custom uninstall or
automatic cleanup is implemented.

## Inspected Backdrop conventions

Local Backdrop 1.35.2 sources:

- `core/includes/config.inc`: `config()`, `Config::validateData()`,
  `Config::getOriginal()`, `Config::save()`, prefix enumeration and storage.
- `core/modules/config/config.api.php`: configuration registration and
  data/create/update/delete validation hooks.
- `core/modules/config/config.sync.inc`: `config_sync_validate_file()` and
  `config_sync_file()`.
- `core/includes/bootstrap.inc`: `backdrop_autoload()`.
- `core/includes/install.inc`: `backdrop_check_module()`.
- `core/modules/system/system.admin.inc`: installation requirement ordering.
- `core/includes/form.inc`: preparation, token validation, input handling,
  nested validation, and handler dispatch.
- `core/modules/system/system.module`: native `confirm_form()` and element types.
- `core/modules/image/image.module` and `image.admin.inc`: administration routes,
  local actions, operations, and confirmation conventions.

H5P source was reverified on `feature/h5p-api-1.28`, commit
`3491220c29716a08cc809b90951f86807450c151`, before implementation. The module's
`h5p_autoload_info()` maps Core to `library/h5p.classes.php`; the actual class
declares major 1/minor 28. The source branch is evidence, not a runtime check.

Learner runtime CSS, readiness and fallback are documented in [Step 4A](phase-2c-step-4a.md).
No server-side learner preference configuration or storage is introduced. Browser
sessionStorage holds the optional preference described below.


## Browser tab-session preference

A single browser sessionStorage entry is scoped by the validated native Backdrop
basePath: `h5p_themer:learner-theme:v1:<basePath>`. At a root installation the key
is `h5p_themer:learner-theme:v1:/`. No user or content identifier is stored.
The value is only a stable current catalog token, such as `core`, `builtin:mint`
or `custom:checkpoint_test`; it is never a CSS hash, label, palette or URL.

The enabled controller validates restoration against its current server-provided
catalog. Site default removes the entry and follows the current administrator
theme. Explicit Core remains independent of that default. A label change keeps
a custom token valid; deletion/unresolvable choices are discarded on restoration.
The current palette mapping is used after palette changes. Already open pages
use their supplied catalog until normal page refresh/settings reattachment.

Initial storage reads and persisted-pageshow restoration wait until eligible
playback and matching styles are ready. Storage is not reread on ordinary
behavior reattachment, frame/style load or DOM discovery after initialization. Only
a selector change writes a valid token; invalid restoration can remove this
entry. Storage failures preserve page-only switching. Disabled pages neither
read nor write storage, and a prior preference may remain until re-enabled.
A missing/invalid basePath also leaves switching page-only.

The preference can survive same-tab navigation and reload. New tabs can inherit
an opener copy, and browser restore may retain a tab session. No localStorage,
cookies, account storage, network endpoint, cross-device preference or H5P answer
persistence is added. Session behavior is implemented and covered by the
user-confirmed Firefox acceptance results; see [the historical Step 5 record](phase-2c-step-5.md)
for its original scope. Browser session restoration can retain a preference;
there is no guarantee that every newly opened or restored tab starts empty.

### Independent learner Density preference

The native Density controller owns `null | small | medium | large`; null is theme
mode. Its independent sessionStorage key is
`h5p_themer:learner-density:v1:<validated basePath>`. Origin and validated basePath
isolate installations; no user, player, theme or bundle identifier is stored.
One shared choice applies across the same installation/tab, with distinct originals
per qualified player. Only the three enum strings are stored; missing/invalid data
uses theme mode. Reset removes only this key. Temporary player absence does not
clear it. Reads wait for usable qualification/readiness, including persisted
pageshow restoration. Disabled/excluded contexts do not access this preference.

Storage exceptions preserve in-memory operation; a failed write/removal can leave
an older preference for later navigation. Standard opener-copy and browser session
restore semantics apply. Saved-theme schemas and browser theme-selection tokens
are unchanged. The UI uses immutable controller snapshots, subscribes before reading
the snapshot, and unsubscribes on teardown. It never writes Density classes or
requests its own resize. Accessible names, pressed state, focus and polite hidden
announcements remain; visible mode/scope lines were removed by accepted refinement.
Firefox keyboard/zoom/narrow tests passed; forced colors and screen reader are
not tested. See [v0.4.1 readiness](v0.4.1-readiness.md).

## Compatibility and styling scope

Compatibility-based visibility changes no schema, palette or administrator setting.
A positive library-owned top-level `.h5p-theme` marker and matching stylesheet
readiness are required before learner UI or preference restoration. Missing
containers/styles are pending; an inspected unmarked container is currently
unsupported but remains observable, so this is not a permanent negative cache.

The fixed `data-h5p-themer-eligible` attribute records the controller's current
classification on an owned H5P content root; the attribute is never evidence of
capability. Learner palette declarations and reset rules both require it. While
an explicit choice is active in a document, unmarked roots keep the canonical
administrator-global declarations. Genuine Core and Site default remain distinct.

Initial restoration and persisted-pageshow restoration are deferred until a
compatible ready player exists. Ordinary refresh/discovery does not access
storage; removing the last eligible player does not delete a preference. Thus
compatible -> incompatible -> compatible same-tab navigation retains the token.
A class marker is a conservative supported-library convention, not universal
H5P certification. No runtime CSS scanning, version list, preference endpoint or
third-party library mutation is added. Exactly one selector controls all eligible
native DIV/internal-iframe activities on a page. Unsupported legacy activities do
not trigger it; unsupported siblings keep administrator-global styling. Editors,
administrator previews, unrelated/external frames and unsupported nested/form
contexts are excluded. See [the historical Step 6 record](phase-2c-step-6.md).

Site default (the configured global theme), explicit H5P Core defaults and
Daylight remain available whenever the validated learner catalog is usable.
After visibility activation, other built-in and valid custom themes appear only
when their saved visibility entry is explicitly true. New/duplicated custom themes
start hidden; deletion retains a hidden tombstone. Hidden themes remain defined,
and a hidden global theme still applies through Site default. Before the recorded
activation barrier, an absent policy preserves legacy visibility.
Core is distinct from Site default and Daylight. Schema-1 customs retain sparse
Core inheritance; schema-2 customs retain pinned inheritance. Switching changes
styling only, without H5P restart, iframe navigation or answer/scoring changes.
Themer adds no answer persistence across navigation or reload.

Generated CSS is immutable and content-addressed. A fixed-path JavaScript change
can still reuse a stale Backdrop aggregate; follow the [deployment procedure](installation.md#deployment-cache-refresh)
in a properly bootstrapped environment rather than editing caches or deleting CSS.
Global Core plus disabled learner selection stops future Themer override
attachments; a learner's explicit Core choice does not disable the feature.

## Qualification status

The accepted complete Checkpoint 3C Stage 1 baseline is **3,793 passed, 0 failed**
on each of PHP **7.4.33**, **8.3.18** and **8.4.22**, and **470 passed, 0 failed**
on Node.js **22.23.1**, using Backdrop **1.35.2** source definitions and H5P Core
API **1.28**. PHP includes 325 separate-request administration lifecycle assertions
and 54 readiness assertions. These suites use isolated collaborators and private
files. Later Checkpoint 3C Lite passed 46 focused assertions and all seven manual
Firefox acceptance tests, including an isolated database-backed warmed upgrade
and Density-setting OFF/ON. A separate clone passed 85 focused checks of actual
module disable/re-enable without uninstalling: schema 1003, all eight saved
configuration hashes and Global Default fresh_source were preserved. See
[current evidence and limits](v0.4.0-readiness.md). These results do not establish
every Backdrop 1.x release or other H5P APIs; full code rollback was not rehearsed.
The user confirmed Firefox Step 5E 10/10 PASS and Step 6E 9/9 PASS; no additional
browser testing is claimed by publication corrections. Source/tests check native
select/label association, but keyboard-only, screen-reader announcements, focus
on control removal, narrow/mobile layout, RTL/long labels, Chromium, Safari,
restricted storage and broader library compatibility are not fully qualified.
The v0.4.0 version designation is not a claim of universal compatibility or WCAG
certification. Historical checkpoint totals remain in their original phase records.
