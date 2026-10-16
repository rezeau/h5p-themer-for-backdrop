# Phase 1C Step 2C — isolated implementation

**Historical development record.** This describes the evidence and scope at the original checkpoint, not the current deployment state. See [installation](installation.md), [administration](administration.md) and the [current roadmap](roadmap.md) for present guidance.

Implemented and tested on 2026-10-16. SPDX-License-Identifier: GPL-2.0-or-later.
This record describes the isolated copy, not a live deployment or browser PASS.

## Isolation and baseline

A timestamped ordinary directory outside the live-linked project was used.
All 26 source files initially matched the recorded SHA-256 baseline. The copy
contained no junctions, symlinks or Git metadata; the active module junction
continued to target the untouched original. Baseline and verification records
were retained separately outside the module inventory.

Before editing the copy, both explicit PHP executables reported 894 passed,
0 failed, exit 0. Tests include native Backdrop Config/import/Form API code with
in-memory storage. They never include site settings or bootstrap the live site;
the harness throws if database access is attempted.

## Implementation

- General uses native collapsible fieldset markup, initially expanded.
- Five existing color groups are initially collapsed. Existing explicit color
  parents are preserved; General controls now also have explicit flat parents.
- The compatibility/stale-source guard disables General and colors together.
- Native Backdrop error classes and collapse.js open groups with invalid fields.
  No custom accordion or modified Core assets are used.
- One scoped administration script attaches through Backdrop.behaviors and the
  installed jQuery once API. It runs only on forms declaring editable colors.
- Companions have native color inputs, unique IDs and labels, description/control
  associations, and no submitted name. Existing Backdrop classes supply basic
  styling; no custom CSS or third-party dependency was added.
- Initialization, shorthand expansion, invalid text handling and reattachment
  never write into the authoritative textfield. Only deliberate picker events
  write a six-digit explicit hex value and emit a textfield change event.
- Blank schema-2 inputs retain exact pinned inheritance. Inherited hex is shown
  without adding overrides; non-hex expressions stay exact explanatory data.
- Where inherited hex is unavailable, blank/invalid text disables the picker.
  Entering valid hex enables it. Disabled swatches are not labeled as previews.
- Schema-1 blank stays Core/no override, with no invented preset/Core palette.
- Clearing is text deletion, without an extra button. Native form reset merely
  resynchronizes the companion after the browser restores text defaults.
- Invalid input with available inherited hex is retained while the picker shows
  the inherited color, with an explicit explanation of the distinction.
- Disabled/read-only controls cannot be changed through companions.
- No-JS and unsupported-picker fallback retains existing hex textfields. Native
  no-JS fieldset CSS keeps controls visible.
- Initial built-in duplication still has no editable colors or picker asset.

No configuration schema, catalog, storage validator, submit handler, menu route,
installation hook, H5P integration or runtime asset hook was changed. Sparse
colors, exact typed hex spelling, locks, CSRF/permission/staleness checks,
forged-field rejection, default selection and deletion protection are preserved.

## Exact file delta

Modified:
- h5p_themer.admin.inc
- tests/admin.php
- tests/bootstrap.php (isolated module-path collaborator)
- tests/preset-admin.php
- tests/run.php
- docs/administration.md

Added:
- js/h5p_themer.admin.js
- tests/form-enhancements.php
- tests/js/admin-colors.test.cjs
- tests/js/dom-fixture.cjs
- docs/phase-1c-step-2c.md

## Automated results

Historical commands ran from the isolated directory. Replace executable/source
placeholders with your environment and set BACKDROP_SOURCE_ROOT as documented
in the README before running the current Node suite:

    & '<php-7.4-executable>' -n tests\run.php --backdrop-root=<Backdrop-source-root> --h5p-root=<H5P-module-source-root>
    & '<php-8.4-executable>' -n tests\run.php --backdrop-root=<Backdrop-source-root> --h5p-root=<H5P-module-source-root>
    node --test --test-reporter=spec tests\js\admin-colors.test.cjs
    node --check js\h5p_themer.admin.js

| Runtime | Result |
| --- | --- |
| PHP 7.4.33 | 1,141 passed, 0 failed, exit 0 |
| PHP 8.4.22 | 1,141 passed, 0 failed, exit 0 |
| Node 22.23.1 | 35 passed, 0 failed, exit 0 |
| JavaScript syntax | Passed, exit 0 |

PHP coverage checks grouping, real native input names/error classes, collapse
library attachment, both schemas, inherited metadata, unchanged text fallback,
retained input during callback/builder reconstruction, guarded containers,
read-only duplication, forged picker/group rejection and zero unintended writes.
Existing tests continue covering schemas, sparse saves, locks, CSRF, stale forms,
default selection/deletion protection and exact unnormalized stored hex.

Node runs the production script with minimal DOM/event/jQuery collection doubles.
The actual installed jquery.once.js and collapse.js are evaluated, including
multi-group error expansion. The first run identified an absent hasClass method
in the test double; completing that collaborator made the native-collapse check
pass. No production workaround was introduced for that fixture limitation.
Current test commands require BACKDROP_SOURCE_ROOT for read-only Core-source
dependencies; see the README. No npm packages or browser were used at this
checkpoint.

## Browser verification pending at this historical checkpoint

No live-site requests, browser sessions, theme operations, database/configuration
changes, cache/menu operations, transfers, H5P changes or Git operations occurred.
The original project's complete 26-file inventory and hashes are rechecked
against the baseline after implementation. A final checksum/delta manifest is
kept beside the working copy, not inside the live-linked project.

After separately authorized controlled transfer and Firefox verification:

1. Confirm General expanded and five groups collapsed; check keyboard operation.
2. Edit a disposable schema-2 preset copy; confirm inherited hex picker display
   and blank authoritative text, then Save unchanged and check sparse storage.
3. Type mixed-case shorthand and six-digit colors; Save/reopen and check exact
   spelling. Choose visually and check the resulting explicit six-digit value.
4. Clear an override and Save; confirm exact pinned inheritance returns.
5. Check light-preset color-mix and Dark rgba inheritance: blank/invalid text
   disables the picker, valid typed hex enables it, clearing restores inheritance.
6. Submit invalid colors in multiple groups and an invalid General field; verify
   all error groups open and manually entered text survives the real form cycle.
7. Check disabled/guarded forms, picker cancellation, focus, accessible labels,
   visible explanatory status and responsive layout in the actual admin theme.
8. With JavaScript disabled, verify visible textfields, validation and saving.
9. Recheck schema-1 editing, custom duplication, read-only initial preset copying,
   preset inspection, global selection and selected-theme deletion protection.
10. Return to Core defaults and delete only disposable themes through normal UI.

Real native dialogs, accessibility, layout, actual jQuery DOM behavior and the
full session/form-cache lifecycle are not proven by Node doubles. Picker choice
of an unchanged displayed inherited color may not emit a native change event;
verify it in Firefox and use the authoritative hex field when an explicit
same-color override is wanted.

## Recommendation and stop boundary

GO for a separately authorized controlled transfer followed by Firefox checks.
This is not approval to transfer. Preserve the original source baseline and
recheck it immediately before transfer; reconcile any intervening changes first.
No menu/schema update is introduced. No live deployment or Phase 2 implementation
has been performed. Stop pending explicit transfer authorization.
