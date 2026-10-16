# Phase 1C Step 2B: preset administration and selection

**Historical development record.** This describes the evidence and scope at the original checkpoint, not the current deployment state. See [installation](installation.md), [administration](administration.md) and the [current roadmap](roadmap.md) for present guidance.

Implemented and tested in isolation on 2026-10-16.
SPDX-License-Identifier: GPL-2.0-or-later.

## Preflight

Step 2A was present and accepted. Both explicit executables passed the existing
supported runner before coding: PHP 7.4.33 and PHP 8.4.22 each reported
671 passed, 0 failed, exit 0. Project file hashes matched the accepted Step 2A
state except its expected final documentation safety appendix. No unexpected
source changes or unsafe dependency were found. The live module path remains
a junction to this project. No live request was performed.

## Implemented behavior

- Three overview sections: H5P Core defaults, five built-in themes, and custom
  themes of either schema. Stored and effective global defaults are distinct
  indicators; overrides and invalid data produce diagnostics.
- Built-in View pages show all 34 exact escaped values, explicit catalog
  identity/version, source/license information and the compatibility note for
  contrast-cta-dark. No preview, Edit, Delete, or mutable preset config exists.
- Two protected native menu callbacks add versioned built-in View and Duplicate
  paths. Existing custom routes retain their meaning, including colliding names.
- Preset duplication creates one independent schema-2 custom, with editable
  metadata, density initially large, exact pinned base and empty overrides.
  Initial color overrides are rejected; Edit after saving to customize colors.
- Custom Edit/Duplicate preserve their original schema. Schema-2 forms show the
  exact read-only base, inherited values and saved-override status, with only
  the existing 33 strict hex textfields. Clearing overrides restores the exact
  inherited value. No effective-palette flattening or hex normalization occurs.
- Global selection uses current allowed form tokens (core, builtin:<id>,
  custom:<id>) decoded to validated storage references. Legacy settings remain
  schema 1 for Core/custom choices. Only explicitly choosing a built-in requires
  upgrading schema-1 settings to schema 2. Schema-2 settings remain schema 2.
  Core stays null, and Daylight is never selected automatically.
- Deletion checks protect both stored and effective legacy/typed custom
  references, including settings.php overrides added after form construction.
  Malformed effective settings conservatively block deletion. A built-in
  selected default does not prevent deletion of an independent same-name custom.
- Permission, native CSRF, API 1.28 compatibility, original/override checks,
  stale snapshots, collision checks, locks, shared schema validation, import
  reference checks and escaped output remain active. Every mutation validates
  again under the existing write lock. Invalid source data never becomes a
  silently repaired/saved definition.

See [the administration guide](administration.md) for routes, storage rules,
failure behavior and future manual verification. The Step 2A catalog,
configuration reader/resolver, shipped settings and install hooks are unchanged.

## Files

Production changes: `h5p_themer.admin.inc` and `h5p_themer.module`.

Tests: new `tests/preset-admin.php`; updated `tests/run.php`,
`tests/admin-bootstrap.php`, `tests/admin.php`, and
`tests/config-compatibility.php`. Existing test scenarios are retained, with
selectors adapted to explicit tokens and route assertions including the two
new protected callbacks.

Documentation: `README.md`, `docs/administration.md`, `docs/configuration.md`,
`docs/builtin-presets-roadmap.md`, the historical Step 2A document's superseding
scope note, and this record. Step 2C now explicitly owns pickers/group changes.

## Automated results

Commands run from the project directory:

```powershell
& '<php-7.4-executable>' -n tests/run.php --backdrop-root=<Backdrop-source-root>
& '<php-8.4-executable>' -n tests/run.php --backdrop-root=<Backdrop-source-root>
```

| PHP | Passed | Failed | Exit |
| --- | --- | --- | --- |
| 7.4.33 | 894 | 0 | 0 |
| 8.4.22 | 894 | 0 | 0 |

The full suite retains foundation, Phase 1B and Step 2A coverage, with 223
additional assertions compared with preflight. Warnings/deprecations fail the
run. Native Config/import and Form API execute with in-memory storage and
narrow session/permission/lock collaborators; neither live storage nor a site
bootstrap/database connection is used. Tests cover all five copies, exact
inspection, both schemas, sparse overrides, inheritance clearing, same-name
identities, all default choices, unsafe/unlisted input, stale states,
overrides, deletion, CSRF, compatibility, lock and storage failures.

## Boundaries and readiness

Source changes are visible through the junction, but no live Backdrop
configuration, database record, H5P content or cache was changed by this step.
No live request, module operation, import, cache clear, backup restoration,
Git initialization, commit, push, tag or release was performed. No H5P
integration/Core/Editor file was changed. There are no new runtime asset hooks,
theme CSS/JS injection, previews, color pickers, changed collapsible groups,
content assignments, learner controls or Step 2C implementation.

Final SHA-256 inventories matched preflight for all 102 active configuration
files and all 340 files in the separate H5P integration tree. The preset
catalog, configuration/resolver source, shipped settings and install hooks
also retain their preflight hashes. Thirteen project files changed (11
modified, two added), with no removals; no Git repository exists.

GO for separately authorized controlled live administration verification,
subject to user review. It has not begun. Remaining qualification includes
native menu discovery (an explicitly authorized menu/cache rebuild may be
needed), real sessions/roles/tokens and form caches, complete browser rendering
and keyboard behavior, filesystem-backed persistence and concurrency. The
module lock does not coordinate native imports or manual file edits, and
schema-2 data cannot be managed by rolled-back Phase 1B code.

Future live checks should use disposable themes and an approved recovery
baseline. In an authenticated Firefox session, verify category/default
indicators; inspect each exact preset; duplicate/edit/clear/duplicate/delete
schema-2 copies; preserve a schema-1 custom; test same-name identities and
Core/built-in/custom selections; verify selected-custom deletion protection;
and clean up explicitly. Do not bypass administrator authentication. These
instructions describe later verification, not actions performed in Step 2B.

Stop for user review. Step 2C and live verification require new authorization.
