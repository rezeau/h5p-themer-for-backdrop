# H5P Themer for Backdrop

An independent Backdrop CMS module that applies administrator-selected colors to
compatible H5P content. The implemented administration interface includes five
immutable built-in themes, named custom themes, saved-theme previews, and live
previews of unsaved color edits.

The stable baseline is **v0.2.0**. This development branch adds provisional
**v0.2.1** [native single-theme creation](docs/configuration.md#provisional-v021-single-schema-2-theme-creation);
native import, JSON round trip, duplicate rejection, Edit colors and hidden
visibility have passed manual testing on a disposable Backdrop site. Broader
native safety checks remain outstanding. See the
[release notes](docs/release-notes-v0.2.0.md) for changes since v0.1.0 and the
[installation and upgrade guide](docs/installation.md) before deployment.

## Requirements

- Backdrop CMS **1.x** is declared in module metadata. Actual inspection and local
  verification used **Backdrop 1.35.2**; this is not a claim that every 1.x release
  has been tested.
- An enabled Backdrop H5P integration exposing **H5P Core API exactly 1.28**.
  Older and newer APIs are rejected; an arbitrary H5P module release is not
  automatically compatible.
- Writable local public storage with a browser-safe relative directory path for
  generated global-theme CSS.
- PHP **7.4.33**, **8.3.18** and **8.4.22** have passed the regression suite.
- Visible recoloring requires an H5P content library/version that consumes the
  relevant theme CSS custom properties.

No H5P Core, Editor or integration source changes are needed. The module adds no
external picker dependency. Administrator-enabled learner selection changes
eligible activities' styling without restarting them. A validated preference can
follow the current browser tab session using sessionStorage; it is not an account
preference and does not save H5P answers. Recorded Firefox functional acceptance
and isolated automated tests support the implemented behavior; browser,
accessibility and database-backed deployment qualification remain limited.

## Install and use

Follow the [installation guide](docs/installation.md) to install and enable the
existing H5P integration first, verify its API, then install this module in a
directory named `h5p_themer`.

Open `admin/config/content/h5p-themer` with the restricted **Administer H5P themes**
permission. The overview separates:

- **H5P Core defaults (no overrides):** the Core choice in Global Default,
  represented by a null selection; the overview/learner UI abbreviates it to
  **H5P Core defaults**.
- **Built-in themes:** Daylight, Lavender, Mint, Sunset and Dark. Daylight is the
  default preset's name; it is distinct from Core. Add uses it as a fallback
  starting theme without changing Global Default.
  Built-ins offer View and Duplicate, not Edit or Delete.
- **Custom themes:** multiple named definitions with editing, duplication and
  protected deletion. Duplicate a built-in to create a custom theme pinned to
  that exact preset version.

New installation defaults to Core/null. Use Global Default to select Core, a
built-in, or a custom theme. Reload activity pages after changing the global
selection. Authored H5P content is not modified.

**Add theme** starts from the effective **Global Default**. Core/null, invalid,
missing or unresolvable defaults use a clearly identified Daylight fallback
pinned to `moodle-478fed30d2a4`. Blank Add fields retain captured starting colors;
enter hexadecimal overrides or use an available picker to replace them. Save
creates an independent custom theme, hidden from learners, without changing
the source or global selection. Changed sources/defaults require reloading Add.
Missing or expired original form state also requires reloading Add. Creation
cannot reuse a missing custom ID referenced by the stored or effective default;
repair that reference separately or choose another ID.
Custom defaults retain their schema, effective sparse overrides and density;
schema-2 copies retain their exact preset pin without a custom-parent dependency.

Schema-1 customs retain sparse hexadecimal overrides without migration.
Schema-2 customs retain an immutable pinned base plus sparse overrides.
Editable colors accept only `#RGB` or `#RRGGBB`; typed case and shorthand are
preserved. On Edit/Duplicate, blank means inherit the pinned base, or use Core's
cascade for schema 1. Edit identifies saved overrides and the pinned preset when
present; earlier custom-copy provenance is not recorded or inferred.
Native color pickers enhance the textfields without becoming separate
submitted values. General settings start expanded, color groups collapsed, and
groups containing validation errors open automatically.

Add, Edit and the two Duplicate workflows are compared in the
[administration guide](docs/administration.md#creating-and-editing-custom-themes).
Theme, Global Default and visibility forms retain trusted snapshots across
requests; stale or expired forms require a reload. Ordinary validation corrections
retain the original context instead of silently rebasing onto new data.

## Theme Preview and Live Preview

Built-in View and custom Edit pages show representative samples in a script-free
sandboxed iframe, with a separate H5P Core-defaults comparison.

A custom Edit page starts from its saved theme. Valid unsaved hexadecimal edits
update the sample immediately through parent-side JavaScript. Multiple overrides
can coexist. Blank restores inheritance; invalid or incomplete input retains the
last valid preview color and displays a message. Reset/reverting restores the
corresponding form colors. **Previewing never saves:** use the normal Save button
to persist valid edits. Server validation remains authoritative.

With JavaScript disabled, the saved preview, editable textfields and PHP
validation remain available; unsaved changes are previewed only when JavaScript
is enabled. See [administration](docs/administration.md) for the exact behavior.

## Global color runtime and limitations

The existing H5P styles hook delivers global colors to div, iframe and external
playback modes. With learner selection disabled, Core/null attaches no Themer
CSS and does not inspect public storage. Enabled DIV/internal-iframe playback
uses a separate immutable learner bundle, including when the global choice is Core. Themes resolve through validated effective configuration, and a fixed
`.h5p-content` selector emits the 33 supported color properties. Exact trusted
preset expressions are allowed; custom overrides remain strict hex. The catalog's
additional `--h5p-theme-contrast-cta-dark` property is retained as data but is not
emitted because Core 1.28 runtime support has not been established.

Immutable `colors-v1-<sha256>.css` global assets and enabled
`learner-v1-<sha256>.css` bundles live under `public://h5p_themer/`.
Verified bytes, a filesystem lock and rename coordinate publication; existing
unexpected files are preserved and not attached. Resolution, compatibility or
storage failures preserve playback. A failed learner bundle falls back to the
legacy global path when available; unresolved global colors add no overrides.

Successful global-reference changes or selected-custom color changes invalidate
native page/layout caches. The optional learner UI also invalidates these caches
when its enable flag, displayed options or any selectable palette changes. One combined invalidation
avoids duplicate flushes. Disabled UI retains the earlier unselected/metadata-only
behavior; density-only, no-op and rejected saves do not invalidate. Raw
programmatic configuration changes need caller-managed cache invalidation.

Current limits:

- **Density is stored but not applied**, in playback or preview.
- Preview is representative HTML, **not a running H5P activity**. It does not
  guarantee a library's exact appearance, layout, feedback or interaction.
- Not all H5P libraries consume these CSS variables. Authored or hardcoded styles
  can remain unchanged; this is not universal recoloring.
- External embeds receive the global theme only when their H5P assets are served
  through the compatible integration on the Themer-enabled site. A host page
  cannot recolor an unrelated provider's activity. Nested/cross-origin contexts,
  multiple players, fullscreen and caches need context-specific qualification.
- Already open players require reloading after a global administrative change.
  Learner switching uses the current page catalog; it does not subscribe to
  administrative changes. Per-content assignment is not implemented.
- There is no comprehensive cross-browser or accessibility certification.
  Upstream WCAG subtitles are metadata, not certification of this module or content.
- The administration/preview interface was manually usable at 375px in Firefox
  but cramped. The learner selector still needs narrow/mobile viewport testing.

## Verification and developer tests

The latest verified automated results for the release code are:

| Runtime | Passed | Failed |
| --- | ---: | ---: |
| PHP 7.4.33 | 3,519 | 0 |
| PHP 8.3.18 | 3,519 | 0 |
| PHP 8.4.22 | 3,519 | 0 |
| Node.js 22.23.1 | 387 | 0 |

PHP totals include 325 separate-request administration lifecycle assertions.
The JavaScript total includes the corrected compact-help/status and responsive
help-target expectations; the three obsolete assertion failures are resolved.
Earlier phase totals remain in their historical records. Documentation preparation
does not claim a new test run or additional browser acceptance.

Use an existing Backdrop source tree with API-1.28 H5P. Replace the placeholders
below with your own absolute source paths and select the required PHP executable:

```text
php -n tests/run.php --backdrop-root="<absolute-Backdrop-source-root>" --h5p-root="<absolute-H5P-module-source-root>"
```

The Node tests require **BACKDROP_SOURCE_ROOT**. It points to the Backdrop source
tree containing `core/misc/jquery.once.js` and `core/misc/collapse.js`; there is
no machine-specific fallback. Learner contract tests also require
**PHP_EXECUTABLE** and run an isolated CLI worker with in-memory configuration. In PowerShell:

```powershell
$env:BACKDROP_SOURCE_ROOT = '<absolute-Backdrop-source-root>'
$env:PHP_EXECUTABLE = '<absolute-PHP-CLI-executable>'
node --test --test-reporter=spec (Get-ChildItem tests/js/*.test.cjs).FullName
```

Or in a POSIX shell:

```sh
BACKDROP_SOURCE_ROOT="<absolute-Backdrop-source-root>" PHP_EXECUTABLE="<absolute-PHP-CLI-executable>" node --test --test-reporter=spec tests/js/*.test.cjs
```

No npm packages or live site bootstrap are used. PHP tests load native definitions
with memory-only configuration, permissions, sessions, locks and cache
collaborators; database access throws. Runtime tests map public storage exclusively
to private temporary fixtures and clean them afterwards. Normal temporary-file
access is required, including subprocesses, locks and rename. Node uses production
behaviors with DOM/event doubles and installed Backdrop once/collapse scripts;
these automated checks are not browser or assistive-technology certification.

The user separately confirmed the following administration/preview results in
Firefox after deployment; publication corrections do not rerun browser tests:

| Manual check | User-confirmed result |
| --- | --- |
| Saved previews | PASS |
| Live unsaved edits | PASS |
| Invalid input handling | PASS |
| Inheritance and reset | PASS |
| Multiple overrides | PASS |
| Native picker synchronization | PASS |
| JavaScript-disabled fallback | PASS |
| 375px viewport | Usable, but cramped |

Phase 2C learner acceptance was separately confirmed by the user: **Step 5E:
10/10 PASS; Step 6E: 9/9 PASS**. Step 6E covered the True/False selector and theme
switching, hiding the selector for legacy Image Choice Rounds, session persistence,
multiple activities, answer continuity, Core versus Site default, Copy of Mint,
and no new Firefox Console errors. These functional results do not establish
keyboard-only or screen-reader acceptance, focus behavior on control removal,
narrow/mobile layout, RTL/long translated labels, Chromium, Safari,
restricted-storage behavior, or broad H5P library compatibility. Source/tests
check the native select and its associated label; no WCAG certification is claimed.

Phase 2D passed **12/12 user-confirmed initial Firefox functional checks** in the
disposable test environment. The compact layout was subsequently accepted in
Firefox: all nine themes were visible without scrolling at normal zoom, with
correct two-color icons and spacing. Desktop option rows have a 32px minimum;
coarse-pointer option rows retain a 44px minimum. NVDA, touch, forced-colors, RTL,
high zoom and other-browser verification remain limited. No WCAG compliance or
universal browser support is claimed.

Historical phase records in `docs/` describe the evidence available at each
earlier checkpoint, not the current deployment state or installation procedure.

## Learner selection and recovery

In **Global Default**, enable **Allow learners to select their H5P theme** to
offer one **H5P theme** selector above the first eligible activity. The
setting is false by default. Choices are:

- **Site default:** follow the administrator's configured global theme.
- **H5P Core defaults:** explicitly use the native Core palette, independently
  of the administrator's global selection. This is distinct from Daylight.
- **Daylight:** always available when the validated learner catalog is usable.
- **Lavender, Mint, Sunset and Dark:** available when published for learners.
- **Saved custom themes:** valid themes explicitly published for learners, using sparse
  Core inheritance for schema 1 or its pinned base for schema 2.

Use **Available to learners** and **Save visibility** on the Themes overview.
Site default, Core and Daylight are fixed choices. Add, custom Duplicate and
Preset Duplicate create hidden custom themes; recreated IDs start hidden too.
Hiding a theme removes its explicit learner option without changing its definition
or Global Default. A hidden global theme still applies through Site default.
See [visibility administration](docs/administration.md#learner-visibility-and-publication).

Compatibility requires the library-owned `.h5p-theme` marker on the player's own
direct `.h5p-container` and matching stylesheet readiness. Unsupported or unmarked
activities do not trigger a selector. One control changes eligible DIVs and
validated same-origin internal iframes on a multi-activity page; ineligible players
retain administrator-global styling. Nested markers alone do not qualify a
player. This conservative convention can miss compatible third-party libraries.
Editors, administrator previews and external/cross-origin embeds are excluded.

The selector restores an available session preference, otherwise Site default.
When sessionStorage is usable, a stable catalog token follows same-tab navigation
and reloads. Site default removes it; explicit Core remains a saved session choice.
Incompatible-only pages do not access or clear the preference. Disabled selection
ignores an existing preference. Storage failures leave page-only switching usable.
Opener-created tabs can inherit a copy, and browser session restoration can retain
it; this is not permanent, account-based or cross-device persistence. Switching
does not navigate/reinitialize H5P or change answers/scoring. Themer does not
preserve answers across navigation or reload. Without learner JavaScript, global
styling remains active; H5P still requires its own scripts.

v0.2.0 combines learner switching, tab-session preferences, the two-color picker,
compact help, explicit visibility publication and the redesigned Add workflow.
The alpha/beta milestones remain documented in the [historical roadmap](docs/roadmap.md)
and [picker implementation record](docs/phase-2d-learner-icons.md). Their statements
about proposed releases or deployment describe those checkpoints, not current
installation instructions. Additional accessibility-oriented presets remain deferred.

To disable Themer color overrides through normal administration, select
**H5P Core defaults (no overrides)** in **Global Default** and disable learner
theme selection, then save and reload activity pages. Global Core alone can still load learner assets
when selection is enabled. Choosing Core in the learner selector affects only
that learner's styling/session preference; it does not disable the feature.

For code deployment, follow the [targeted cache and delivery procedure](docs/installation.md#deployment-cache-refresh).
Backdrop can otherwise combine new learner CSS with an old JavaScript aggregate.
Code recovery should use a verified project backup and normal native cache
procedures, preserving configuration and authored content. Existing immutable CSS
can remain unreferenced until planned cleanup; do not delete it to refresh JS.
See [runtime history](docs/phase-2a-runtime.md) for earlier recovery context.

## Visibility activation, imports and development history

The [visibility foundation](docs/r2-r3-phase-1.md) adds a separate read-only policy
and a shared filtered learner catalog. Missing policy preserves legacy visibility
only before the recorded activation barrier. Fixed choices are always Site default,
Core and Daylight when the validated catalog is usable. The
[persistent activation journal](docs/r2-r3-activation.md) and two-step initialization
are implemented. [Custom publication safeguards](docs/r2-r3-publication.md)
now make new/copied themes hidden, preserve editing visibility and retain deletion
tombstones. [Native visibility controls](docs/r2-r3-visibility-admin.md) now provide
an explicit Save visibility action on the Themes overview. Custom import forms,
coordination and recovery have been removed. Provisional single schema-2 theme
creation uses the existing native import form. Generic theme synchronization and
visibility-policy imports remain rejected by stateless guards; native configuration
exports, settings imports and unrelated configuration retain their existing
behavior. No custom exporter is provided. New/recreated IDs remain hidden.

Update hooks 1000/1001 retain visibility activation. Hooks 1002/1003 are retained
as documented no-ops; installations already at 1003 need no replay or new hook.
Existing import journal data is left untouched and no longer gates runtime or
administration. Before any separately authorized deployment over the old import
implementation, review and resolve unfinished/damaged operations and pending
output-cache refreshes. Removing the gate cannot repair partially applied data.
Follow the [upgrade procedure](docs/installation.md#upgrading-an-existing-installation)
for database updates, activation preservation, menu registrations and cache delivery.
The compact learner '?' help button is implemented: it reveals or hides the
explanation without changing theme selection. Further administrator UI and
preview improvements remain separate, deferred work.
The checkpoint documents preserve historical deployment observations; this README
does not assert installation or release publication merely from source changes.

Repository-local boundary checks (no Backdrop/H5P sources or site bootstrap):

```text
php -n tests/config-protection.php
```

The visibility activation/publication/policy/admin, administration and
configuration compatibility tests still require explicit Backdrop/H5P source
paths. The local boundary check does not replace those native API tests.

## Maintainer and support

Maintained by [rezeau](https://github.com/rezeau). Use the
[project issue tracker](https://github.com/rezeau/h5p-themer-for-backdrop/issues)
for reproducible bugs and feature requests. This is an independent project, not
an official H5P, Moodle or Backdrop endorsement.

## License and attribution

Copyright (C) 2026 the H5P Themer for Backdrop contributors.

The combined module including the adapted Dark preset is free software under
GNU GPL version 3 or, at your option, any later version: **GPL-3.0-or-later**.
See the root [LICENSE](LICENSE), also retained as [GPLv3](LICENSE-GPL-3.0.md).
Original files keep their GPL-2.0-or-later grants and the [GPLv2 text](LICENSE.txt).
The MIT notices for Daylight, Lavender, Mint and Sunset remain applicable.
The software is distributed without warranty. Preserve the
[full provenance and notices](THIRD-PARTY-NOTICES.md) and
[MIT notice](LICENSE-MIT-H5P-THEME-PICKER.txt), including for extracted fixtures.

H5P is a separate dependency; its sources/assets are not redistributed here.
The property names were checked against H5P Core 1.28.0's
`library/styles/h5p-theme-variables.css`, upstream commit
`2aeb0b83fa603e331381b3a6b8bf42c3773ba140`. Only literal preset data and notices
were adapted from the pinned Moodle sources; no upstream executable runtime,
picker components or WordPress implementation were copied.
