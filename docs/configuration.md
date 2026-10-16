# Configuration contract — schema 1 and Step 2A extensions

SPDX-License-Identifier: GPL-2.0-or-later.

The schema-1 contract remains supported. The implemented schema-2 extensions
add pinned bases and typed references without forcing migration of schema-1
themes. Both schemas are supported by native forms, previews and global colors.
See [the schema-2/read-API record](phase-1c-step-2a.md),
[administration](administration.md) and [installation](installation.md).

## Stored objects

`h5p_themer.settings.json` starts as:

```json
{
  "_config_name": "h5p_themer.settings",
  "schema_version": 1,
  "default_theme": null
}
```

`null` means no selected theme. A missing field, empty string, invalid ID, or
missing settings object is an error, not another spelling of that state.

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
  configuration. Omitted properties would retain Core behavior in a future
  renderer; no renderer exists yet.
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

Effective reads use native `Config::get()` per top-level schema field, honoring
field-level settings.php overrides. Override `colors` as a complete map; dotted
child overrides inside the map are not supported by this accessor. Overrides
must pass the same schema checks. Import validation passes `$original = TRUE`
and uses stored data, excluding environment overrides.

Reads remain available during H5P compatibility failures so administration can
inspect definitions. A future renderer must separately require
`h5p_themer_compatibility()['compatible']` before applying anything. Neither the
read API nor the compatibility function applies themes.

## Native configuration validation and import/export

`hook_config_info()` registers settings and the theme collection (`name_key` is
`id`, `label_key` is `label`). `hook_config_data_validate()` checks object shape
and identity. Create/update import hooks check the settings reference against
the final proposed theme: active storage for unchanged themes, staging storage
for created/updated themes, and rejection for deleted themes.

Single-object settings imports require their target theme to exist already.
Bulk imports can introduce a theme and select it together, regardless of file
order. A referenced definition must itself validate. Deleting the selected
theme is rejected unless the same bulk import clears/replaces/deletes settings.
A malformed settings object blocks theme deletion until repaired or explicitly
deleted/replaced. Deleting settings is permitted and leaves reads/status checks
reporting missing configuration; it does not recreate defaults.

Export uses Backdrop's ordinary configuration facilities. Export and import the
referenced definitions together. The tests exercise native single-file
validation and sync functions with memory storage; live sync batching remains
unqualified. Backdrop sync is not a multi-file database transaction: a failed
application can temporarily leave missing references, which reads handle safely.

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
disabled, and submit handlers recheck compatibility before writes. There are no
learner-specific controls; global playback colors use the separate runtime helper.

## Administration writes

See [administration.md](administration.md). Forms edit stored original values,
not environment overrides. A changed source/settings record rejects a stale
submission rather than overwriting another administrator's work. Each operation
writes/deletes only its target object; duplication never selects a new default.

Theme writes use the shared field validator and `Config::validateData()`.
Default writes additionally call the Phase 1A reference validator. Deletion
calls the Phase 1A deletion safeguard and also checks the effective default, so
a settings.php default override cannot silently become a broken reference.
Modified existing objects with top-level overrides are read-only in these forms.

All three submit handlers recheck validation under `h5p_themer_admin_write`.
The lock coordinates these administration forms; direct filesystem edits and
native config imports do not participate in it. Native Config storage remains
responsible for filesystem write behavior. No cross-object atomic transaction
or coordination with concurrent imports is claimed.

Malformed definitions remain visible but cannot be edited/duplicated/deleted
through these forms; repair them through validated configuration import. Missing
or malformed settings also require configuration repair. A valid settings object
with a missing referenced theme can be explicitly cleared/repointed in the UI.

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
