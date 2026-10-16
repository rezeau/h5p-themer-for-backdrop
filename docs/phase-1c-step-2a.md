# Phase 1C Step 2A: catalog and configuration compatibility

**Historical development record.** This describes the evidence and scope at the original checkpoint, not the current deployment state. See [installation](installation.md), [administration](administration.md) and the [current roadmap](roadmap.md) for present guidance.

Implemented on 2026-10-16. SPDX-License-Identifier: GPL-3.0-or-later.

This is the historical Step 2A record. [Step 2B](phase-1c-step-2b.md) now
implements native administration for both schemas; the intermediate
schema-1-only UI limitation below describes Step 2A and is superseded by
Step 2B. Pickers/collapse behavior is separately scoped to Step 2C.

Phase 1B is closed: the user accepted the documented normal generated-cache,
dependency and export exceptions. Step 2A changes source and isolated tests
only. The live module is junction-linked to this project, so source changes
are immediately visible; no browser requests, site bootstrap, database access,
live imports, configuration saves or cache operations are part of this step.

## Catalog

`h5p_themer.presets.inc` contains five immutable entries, in order: daylight,
lavender, mint, sunset, dark. Every entry has exactly 34 exact source colors.
The only version is `moodle-478fed30d2a4`, derived from Moodle revision
`478fed30d2a4af58638f2ddbea3603db1d4d8c5f`.

Catalog entries include `id`, `version`, `label`, `density` (NULL), `colors`,
separate `presentation`, `provenance`, and `unsupported_properties` arrays.
The latter records `--h5p-theme-contrast-cta-dark`. Retaining/resolving this
property does not claim that Core 1.28 uses it. Four presets retain the exact
color-mix expression; Dark retains its exact RGBA expression. No generated or
normalized palette is substituted. Source paths, line ranges, Git blobs,
copyright and license details are recorded in each entry and
[the notices](../THIRD-PARTY-NOTICES.md).

All new read APIs return `array('data' => ..., 'errors' => array(...))` unless
noted otherwise. Errors have NULL data and never silently select another theme.

- `h5p_themer_preset_versions()` returns the explicit version list.
- `h5p_themer_presets($version)` enumerates a pinned catalog.
- `h5p_themer_preset_load($id, $version)` loads one exact identity.

Versions are required, with no `latest` alias. Returned arrays contain only
arrays/scalars and are returned by value, never by reference. Caller mutation
cannot change authoritative data. Future catalog updates must retain old
version definitions; do not change values under an existing version or merely
add a version name while reusing changed definitions. Built-ins are not native
config objects, and lookup/enumeration installs or selects nothing.

## Schema 1 remains unchanged

The shipped settings file remains schema 1 with `default_theme: null`.
All existing custom shapes, labels, IDs, descriptions, sparse colors, exact
hex text/case and density semantics are retained. Strings in schema-1 settings
always name custom objects, including `daylight` or `dark`. No automatic
migration, save, version rewriting or new-install default change occurs.

## Schema 2 custom definition

```json
{
  "schema_version": 2,
  "id": "my_dark",
  "label": "My Dark",
  "description": "",
  "density": "large",
  "base_preset": {"id": "dark", "version": "moodle-478fed30d2a4"},
  "colors": {"--h5p-theme-background": "#AbC"}
}
```

Schema 2 requires an exact base reference with only `id` and `version`.
The remaining field rules match schema 1. The custom `colors` map remains
sparse and accepts only the existing 33 property names and #RGB/#RRGGBB.
Arbitrary functions, RGBA, color-mix, alpha hex, custom properties and extra
fields remain prohibited as administrator input. The inherited 34th property
is preserved but is not editable as a custom override.

`h5p_themer_theme_load($id)` retains its original contract and returns the
validated stored/effective definition, with sparse overrides, not a flattened
palette. `h5p_themer_theme_resolve($id, $type = 'active', $original = FALSE)`
returns a typed custom result with effective `colors`, custom identity/label,
density, `base_preset` and `unsupported_properties`. For schema 1, its palette
is the unchanged sparse map; for schema 2, it overlays validated hex values
onto all 34 exact pinned base values. Expressions remain strings; no CSS
evaluation occurs. This preserves a copy across later catalog updates.

## Typed global defaults

Schema-2 settings use `default_theme: null` for Core, or one exact object:

```json
{"schema_version":2,"default_theme":{"kind":"builtin","id":"daylight","version":"moodle-478fed30d2a4"}}
```

```json
{"schema_version":2,"default_theme":{"kind":"custom","id":"my_dark"}}
```

Core has no object/string alias. Schema 2 rejects legacy string references,
unknown kinds, additional keys and unknown catalog IDs/versions. Schema 1
rejects typed objects. Both schemas can coexist and reference custom themes
of either schema, without converting stored data.

- `h5p_themer_selection_reference($selection, $schema_version = 2)` validates
  and decodes identity only. Custom existence is checked by resolution/import.
- `h5p_themer_selection_resolve($selection, $schema_version = 2,
  $type = 'active', $original = FALSE)` resolves Core, built-in or custom.
- `h5p_themer_default_selection_load()` resolves effective global settings.

Resolved Core is an explicit `kind: core` result with empty colors and NULL
density. Built-ins return `kind: builtin` plus catalog data, with no density
override. Custom results retain their own identity and density. A missing or
invalid selection returns errors, not a Core or Daylight fallback.

`h5p_themer_default_theme_load()` preserves its definition-accessor behavior:
Core returns NULL without errors; custom references return their unflattened
definition; a new built-in reference returns the exact catalog entry. Use the
new typed resolver where effective colors and selection kind are required.

## Overrides, imports and deletion

Effective reads honor native top-level settings.php overrides, including the
new `base_preset` field and whole `colors` maps. Original/import reads exclude
those overrides. Overrides must pass the same schema validation. The existing
administration stale/original-value write guard also detects overridden base
fields on schema-2 originals. No override is persisted by these read helpers.

Native import validation checks shape, exact bases, strict overrides and
references. Custom references use final staging data when created/updated,
active data otherwise, and reject deleted targets. Built-in references require
the catalog version in the installed module, not an editable config object.
Single imports require custom targets already to exist. Selected custom
deletion protection handles legacy and typed references, including a legacy
default pointing to a schema-2 custom. A built-in selected default does not
protect an unrelated custom object sharing its ID. Clearing/replacing settings
or explicitly deleting settings in the same bulk import releases its reference.

Exports retain the original schema and sparse representation. Import the
referenced custom definitions/settings together and deploy code containing
the pinned catalog version. Native multi-file sync is not transactional: an
intermediate missing target reports an error until the target is applied.
Low-level Config::save() still bypasses import validation; no public write API
or additional transaction layer is introduced.

## Boundaries and next step

The Phase 1B administration implementation is unchanged and remains qualified
for schema-1 management only. Do not activate/import schema-2 objects on the
live site or use the old forms to manage them before Step 2B updates all form,
selection and effective-default deletion checks. In particular, old default
forms assume a string reference. Isolated tests exercise schema 2 directly
through Config/import validation and the new read APIs.

Step 2B must preserve each original schema, show/duplicate immutable presets,
handle typed references and effective overrides throughout the forms.
Picker and group behavior is deferred to Step 2C. Step 2B was not authorized by
completion of this step. No routes, forms, pickers, fieldsets, assets, player,
editor, bridge, assignments or learner-selection behavior are added here.

Downgrading to Phase 1B code cannot read schema 2. Preserve exported settings
and definitions before a future authorized schema-2 deployment; source-only
rollback is insufficient after schema-2 saves. Runtime application remains
Phase 2 and requires separate authorization/qualification.

## Verification

Use the existing `tests/run.php` runner with `-n` and explicit PHP 7.4.33 and
8.4.22 executables. The fixture records a separate literal extraction of the
pinned upstream source, with source blob IDs. Checks cover catalog parity,
immutability, strict references/overrides, effective resolution, schema-1
preservation, native mixed-schema JSON import/export, staged reference order,
deletion guards, override safety and zero-write/read boundaries. The harness
uses in-memory storage, never site settings, live Config storage or a database.

Results on 2026-10-16:

| Explicit executable | Full supported runner result | Exit code |
| --- | --- | --- |
| `<php-7.4-executable>` | 671 passed, 0 failed | 0 |
| `<php-8.4-executable>` | 671 passed, 0 failed | 0 |

The unchanged preflight baseline was 368 passed, 0 failed on each executable.
The additional 303 checks cover Step 2A. No live browser or configuration-import
test was performed; the administration UI is deliberately deferred.

Final SHA-256 inventories matched their preflight values for all 340 files in
the separate H5P integration tree and all 102 active Backdrop configuration
files. The project administration source, shipped settings, metadata,
installation hooks and original test bootstraps are unchanged. No project files
were removed and no Git repository was initialized. No live database connection,
content save, cache clear, backup restoration or other live-site operation was
performed. Only authorized project source, fixture/test, documentation and
attribution files changed.
