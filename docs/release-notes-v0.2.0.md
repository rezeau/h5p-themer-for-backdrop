# H5P Themer for Backdrop v0.2.0

SPDX-License-Identifier: GPL-3.0-or-later.

Release notes prepared for stable v0.2.0. Preparing this document does not create
a tag, publish a release or update an installed module. No release date is
asserted here. See [installation and upgrades](installation.md),
[administration](administration.md) and the [configuration contract](configuration.md).

## Main features

- Five immutable built-in palettes: Daylight, Lavender, Mint, Sunset and Dark;
  named schema-1/schema-2 custom themes with exact preset pins where applicable.
- Global Default with **H5P Core defaults (no overrides)**, built-in and custom
  choices. Core is distinct from Daylight; a new installation starts with Core.
- Add initialized from the effective Global Default, including validated
  settings.php overrides. Core/null, invalid, missing or unresolvable defaults
  use an explicitly identified pinned Daylight starting theme. Blank Add fields
  retain captured starting colors; the saved copy is independent of its custom
  source and never changes Global Default.
- Strict hexadecimal overrides, native color-picker companions, saved Theme
  Preview and unsaved Live Preview. Preset expressions remain inherited rather
  than being flattened into saved custom colors. Density is stored but not applied.
- Optional learner theme selection for eligible DIV and validated same-origin
  internal-iframe activities, with tab-session preferences, a two-color picker
  and compact **?** help. Switching preserves the running activity's answers and
  scoring; Themer does not save answers across navigation or reload.
- Explicit **Available to learners** controls and **Save visibility**. New,
  duplicated and recreated custom themes start hidden. Edit preserves visibility.
  Hidden themes can still be Global Default and apply through Site default.

## Changes since v0.1.0

v0.1.0 already provided global colors, immutable built-ins, both custom schemas,
administration, color pickers and saved/live previews. v0.2.0 retains those features
and adds:

- Learner switching, compatibility/readiness checks, browser tab-session storage,
  the two-color dropdown, compact help and the filtered learner catalog.
- Visibility activation, publication controls, restrictive creation/deletion
  safeguards and preservation of established visibility choices.
- Add from the effective Global Default instead of a blank-slate definition,
  independent copying of custom defaults, and explicit Daylight fallback.
- Trusted cross-request snapshots and stale-submission rejection for Add, Edit,
  Duplicate, Preset Duplicate, Global Default, Delete and Save visibility.
- New-theme picker initialization for unresolved starting colors and updated
  compact-help/responsive-layout regression expectations.
- Rejection of native theme-definition/visibility-policy import mutations;
  settings-only and unrelated imports and normal exports remain supported.

The alpha/beta and phase records remain historical evidence. See the
[development roadmap](roadmap.md) and [picker record](phase-2d-learner-icons.md)
for their original acceptance scope and checkpoint-specific totals.

## Security and concurrency safeguards

Administration requires an authenticated account with **Administer H5P themes**,
native Form API CSRF validation and compatible H5P. Original state remains in
session-bound server-side form tempstore; posted snapshot fields cannot replace
it. Missing, expired, malformed or wrong-session context fails closed. Ordinary
validation corrections retain the original snapshot; relevant concurrent changes
require reloading instead of silently rebasing a submitted form.

Validation and write-lock checks protect existing definitions, defaults and
publication state. A missing custom ID referenced by stored or effective Global
Default cannot be activated accidentally through a creation workflow. Stored and
effective default references protect a selected custom theme against deletion.

Restrictive visibility entries are persisted and verified before publishing a
new definition or deleting one. Deletion retains a hidden tombstone and verifies
an environment-local deletion generation before removing the definition. Old
visibility forms cannot republish a concurrently hidden theme or publish an
identical replacement using the same ID. Labels are escaped, custom overrides
remain strict hex, and only trusted pinned expressions enter generated CSS.

These are cooperative locked operations, not database transactions. Failures may
leave restrictive policy or partially applied definition changes. There is no
automatic rollback that restores learner visibility; inspect diagnostics and
stored state before retrying.

## Installation and upgrade guidance

Install in a directory named `h5p_themer`, after enabling a compatible H5P
integration. Fresh installation initializes visibility through the normal install
hook. Learner selection starts disabled; optional built-ins start available and
custom themes start hidden.

For upgrades, back up code, database state, configuration and files; pause theme
administration/configuration synchronization; replace code without uninstalling;
run Backdrop's normal `update.php` workflow for all pending updates. Refresh menu
registrations, follow the [deployment cache procedure](installation.md#deployment-cache-refresh),
reload old forms and verify the resulting catalog and actual delivered assets.

Updates 1000/1001 implement first-time visibility activation and initialize
existing resolvable optional choices as available without enabling learner
selection. Completed activation preserves existing policy, including hidden
entries. Updates 1002/1003 are retained no-ops. Sites already at level 1003 have
no later module update to apply; do not reset schema numbers or replay activation.
Theme schemas, existing preset pins and Global Default need no migration.

Incomplete/damaged activation or policy after the persisted barrier hides
optional choices and blocks publication. Reads do not repair it. Preserve
activation/deletion state alongside configuration in backups; upgrading from an
experimental import build requires checking unfinished operations first. Code
rollback to a version without visibility enforcement can expose hidden choices.
See the [full upgrade procedure](installation.md#upgrading-an-existing-installation).

## Compatibility and verification

| Component | Verified scope |
| --- | --- |
| Backdrop | 1.35.2 source inspection and local verification; metadata declares 1.x, not universal tested compatibility |
| H5P | Enabled Backdrop integration exposing Core API exactly 1.28; other APIs are rejected |
| PHP | 7.4.33, 8.3.18 and 8.4.22: 3,519 assertions passed, zero failed on each |
| JavaScript tests | Node.js 22.23.1: 387 passed, zero failed, zero skipped |

PHP coverage includes 325 separate-request administration lifecycle assertions
with actual Backdrop Form API functions and isolated storage collaborators.
These suites do not bootstrap a live site or verify database-backed production
upgrades. The previous three obsolete JavaScript assertions have been corrected.

Recorded Firefox acceptance covers administrator previews/pickers, learner
switching and tab-session behavior, the two-color dropdown, and Add starting
instructions, saved colors, hidden creation, custom-source independence and Core
fallback. It does not establish that every concurrency, accessibility or browser
check has passed. Documentation preparation adds no new browser or test results.

## Configuration-import restrictions

Native create/update/delete imports of custom definitions and learner visibility
policy are rejected during validation and application, including mixed plans
that mutate those objects. There is no custom importer or privileged bypass.
Use administration for theme and visibility changes.

Validated settings-only and unrelated imports remain supported. Custom defaults
must resolve, and existing deletion protections remain. Ordinary configuration
export is available; an exported theme/policy is not an authorized import-based
restore procedure. Malformed definitions/policy require separately planned
recovery. Direct filesystem edits or low-level saves bypass native import guards
and are outside the supported publication workflow.

## Known limitations

- Density, typography, spacing and per-content assignment are not applied.
  Only supported theme color properties consumed by a library affect playback.
- Preview is representative HTML, not a running H5P activity or accessibility
  certification. One extra catalog property has no established Core 1.28 runtime
  support and is not emitted.
- Learner UI requires eligible native playback and matching stylesheet readiness.
  Editors, previews, external/cross-origin embeds and unsupported nested/form
  contexts are excluded. This is not universal H5P library support.
- Session preferences are browser-tab scoped, not account-based or cross-device.
  Storage failure leaves page-only switching. No H5P answer persistence is added.
- Open pages retain their supplied catalog until refresh/settings reattachment;
  administrative changes are not pushed or polled. Visibility controls choice
  availability, not content access or palette secrecy.
- Native theme/policy import-based recovery and atomic multi-object rollback are
  unavailable. Submit failures can require reloading a form before retrying.
- Local writable public storage is required for generated CSS. JavaScript
  aggregate freshness must be verified after deployment.
- Broader browsers/libraries, narrow learner layouts, keyboard/screen-reader
  acceptance, touch, forced colors, RTL and high zoom remain incompletely
  qualified. No WCAG certification or compatibility beyond verified versions is
  claimed. Administration/preview at 375px was usable but cramped in Firefox.
