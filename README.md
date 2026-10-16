# H5P Themer for Backdrop

An independent Backdrop CMS module that applies administrator-selected colors to
compatible H5P content. The implemented administration interface includes five
immutable built-in themes, named custom themes, saved-theme previews, and live
previews of unsaved color edits.

## Requirements

- Backdrop CMS **1.x** is declared in module metadata. Actual inspection and local
  verification used **Backdrop 1.35.2**; this is not a claim that every 1.x release
  has been tested.
- An enabled Backdrop H5P integration exposing **H5P Core API exactly 1.28**.
  Older and newer APIs are rejected; an arbitrary H5P module release is not
  automatically compatible.
- Writable local public storage with a browser-safe relative directory path for
  generated global-theme CSS.
- PHP **7.4.33** and **8.4.22** have passed the regression suite.
- Visible recoloring requires an H5P content library/version that consumes the
  relevant theme CSS custom properties.

No H5P Core, Editor or integration source changes are needed. The module adds no
external picker dependency or learner-facing runtime JavaScript.

## Install and use

Follow the [installation guide](docs/installation.md) to install and enable the
existing H5P integration first, verify its API, then install this module in a
directory named `h5p_themer`.

Open `admin/config/content/h5p-themer` with the restricted **Administer H5P themes**
permission. The overview separates:

- **H5P Core defaults:** original styling, represented by a null global selection.
- **Built-in themes:** Daylight, Lavender, Mint, Sunset and Dark. Daylight is the
  default preset's name; it is not Core styling and is not automatically selected.
  Built-ins offer View and Duplicate, not Edit or Delete.
- **Custom themes:** multiple named definitions with editing, duplication and
  protected deletion. Duplicate a built-in to create a custom theme pinned to
  that exact preset version.

New installation defaults to Core/null. Use Global Default to select Core, a
built-in, or a custom theme. Reload activity pages after changing the global
selection. Authored H5P content is not modified.

Schema-1 customs retain sparse hexadecimal overrides without migration.
Schema-2 customs retain an immutable pinned base plus sparse overrides.
Editable colors accept only `#RGB` or `#RRGGBB`; typed case and shorthand are
preserved. Blank means inherit the pinned base, or use Core's cascade for
schema 1. Native color pickers enhance the textfields without becoming separate
submitted values. General settings start expanded, color groups collapsed, and
groups containing validation errors open automatically.

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
playback modes. Core/null attaches no Themer CSS and does not inspect public
storage. Themes resolve through validated effective configuration, and a fixed
`.h5p-content` selector emits the 33 supported color properties. Exact trusted
preset expressions are allowed; custom overrides remain strict hex. The catalog's
additional `--h5p-theme-contrast-cta-dark` property is retained as data but is not
emitted because Core 1.28 runtime support has not been established.

Immutable `colors-v1-<sha256>.css` assets live under `public://h5p_themer/`.
Verified bytes, a filesystem lock and rename coordinate publication; existing
unexpected files are preserved and not attached. Resolution, compatibility or
storage failures leave normal H5P playback without Themer overrides.

Successful global-reference changes or selected-custom color changes invalidate
native page/layout caches. No-op, unselected, label-only, density-only and rejected
saves do not. Raw programmatic configuration changes need caller-managed cache
invalidation; generated CSS is derived cache data.

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
  No learner-controlled hot switching or per-content assignment is implemented.
- There is no comprehensive cross-browser or accessibility certification.
  Upstream WCAG subtitles are metadata, not certification of this module or content.
- A 375px viewport was manually usable in Firefox but cramped; further layout
  work remains appropriate.

## Verification and developer tests

The complete suites passed on 2026-10-16:

| Runtime | Passed | Failed |
| --- | ---: | ---: |
| PHP 7.4.33 | 1,554 | 0 |
| PHP 8.4.22 | 1,554 | 0 |
| Node.js 22.23.1 | 80 | 0 |

Use an existing Backdrop source tree with API-1.28 H5P. Replace the placeholders
below with your own absolute source paths and select the required PHP executable:

```text
php -n tests/run.php --backdrop-root="<absolute-Backdrop-source-root>" --h5p-root="<absolute-H5P-module-source-root>"
```

The Node tests require **BACKDROP_SOURCE_ROOT**. It points to the Backdrop source
tree containing `core/misc/jquery.once.js` and `core/misc/collapse.js`; there is
no machine-specific fallback. In PowerShell:

```powershell
$env:BACKDROP_SOURCE_ROOT = '<absolute-Backdrop-source-root>'
node --test --test-reporter=spec tests/js/admin-colors.test.cjs tests/js/preview-live.test.cjs
```

Or in a POSIX shell:

```sh
BACKDROP_SOURCE_ROOT="<absolute-Backdrop-source-root>" node --test --test-reporter=spec tests/js/admin-colors.test.cjs tests/js/preview-live.test.cjs
```

No npm packages or live site bootstrap are used. PHP tests load native definitions
with memory-only configuration, permissions, sessions, locks and cache
collaborators; database access throws. Runtime tests map public storage exclusively
to private temporary fixtures and clean them afterwards. Normal temporary-file
access is required, including subprocesses, locks and rename. Node uses production
behaviors with DOM/event doubles and installed Backdrop once/collapse scripts;
these automated checks are not browser or assistive-technology certification.

The user separately confirmed the following manual Firefox results after
deployment; this publication cleanup did not rerun browser tests:

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

Historical phase records in `docs/` describe the evidence available at each
earlier checkpoint, not the current deployment state or installation procedure.

## Roadmap and recovery

[Phase 2C and Phase 2D are PLANNED](docs/roadmap.md): optional learner-controlled
site-wide selection, then accessibility-oriented themes. Neither is implemented.

Select Core through normal administration and reload activity pages to stop
future Themer attachments. Code recovery should use a verified project backup
and the site's normal cache procedures; preserve configuration and authored
content. Old immutable CSS can remain unreferenced until planned cleanup.
See [runtime history and recovery details](docs/phase-2a-runtime.md).

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
