# H5P Themer for Backdrop

Manage reusable color themes for compatible H5P activities in Backdrop CMS,
with optional native Density for exactly qualified player types.
This independent project does not modify authored H5P content or H5P libraries.

**H5P Themer v0.4.3 — release metadata prepared; not packaged or published**
The tested implementation is committed as `1fd43f00e9832d5f5e5edfcf112806b1f1d91f3d`.
Version metadata is prepared as `0.4.3`; package validation and publication remain
separately authorized steps. Local deployment and acceptance below describe the
tested implementation, not deployment of these release-preparation edits.
Published baseline: v0.4.2 (`91415f7676b9091abd1bb2517fa28e5bd802d411`).
The accepted local MultiChoice milestone is `3427d15dbc12c50d84fbc9e1757343168151eff7`.
This milestone additionally qualifies official **H5P.Blanks 1.14.37** (Fill in
the Blanks). It is deployed on backdrop2. The user reports all six Blanks Firefox
tests passed, including sizing/wrapping, scoring/Retry/solutions, both custom
themes, persistence, keyboard/focus, zoom/responsiveness and three-player behavior.
These observations are recorded separately from automated verification.
The validated selector simplification shows only permitted colour
themes, preserves special states with truthful placeholders, and adds a theme-only
Reset icon. The user reports all six Firefox acceptance tests passed, including
acceptance of the two UI corrections. See the separate
[implementation and acceptance record](docs/v0.4.3-selector.md).
See the [Blanks implementation and test plan](docs/v0.4.3-blanks.md),
[MultiChoice milestone acceptance review](docs/v0.4.3-readiness.md),
[prepared release notes](docs/release-notes-v0.4.3.md) and
[implementation and test plan](docs/v0.4.3-development.md).

The standalone official MarkTheWords extension reuses the native Density controller
and adds no CSS. Embedded activities and PapiJo variants remain unsupported.
It is deployed on backdrop2; the user reports all 19 Firefox acceptance tests
passed, including a 300–400-word passage, QuestionSet exclusion and the existing
TrueFalse/MultiChoice/Blanks regressions. Generous long-passage line spacing is
recorded only as a possible future refinement; no CSS change is requested.
See the [Phase 5 implementation and acceptance record](docs/v0.4.3-markthewords.md).

## Main features

- Five built-in palettes, editable custom colors, Global Default and learner visibility.
- Learner colour-theme picker with independent browser-tab session preferences;
  **Use site default theme** resets only the theme preference, leaving Density unchanged.
- Optional native Density controls: exactly Small, Medium and Large, beside the picker.
- Before an explicit learner choice, each activity retains its original spacing.
  Saved theme Density never supplies a playback profile.
- Saved and unsaved color previews. **Live Density Preview remains deferred.**

H5P answers, scoring, feedback and Retry are not recreated by styling changes.
Themer does not save answers across navigation or reload.

## Requirements and compatibility

- Backdrop CMS 1.x; local verification used **Backdrop 1.35.2**.
- An enabled Backdrop H5P integration exposing **H5P Core API exactly 1.28**.
  Older/newer APIs and arbitrary integration releases are not qualified.
- PHP **7.4.33**, **8.3.18** and **8.4.22** are regression targets.
- Writable local public-file storage for generated theme styles.
- Color changes require an H5P library that consumes the relevant theme variables.

Native Density supports only **qualified top-level H5P.TrueFalse 1.8.24,
H5P.MultiChoice 1.16.27, H5P.Blanks 1.14.37 and H5P.MarkTheWords 1.11.24 iframe players** in standalone activities.
PapiJo variants, other types/versions, embedded children of Column, QuestionSet or
InteractiveBook, nested, DIV, external and ambiguous players retain
their original Density. Color theming and the learner dropdown have their own
compatibility rules. Broader browser/accessibility support is not certified.

## Install and configure

1. Enable a compatible Backdrop H5P integration and confirm existing playback.
2. Install the module as `h5p_themer`, then use its administration overview.
3. Choose visual themes and enable learner theme selection.
4. In Global settings, enable **Enable native Density controls** if desired.
   The checkbox defaults to disabled and still requires learner theme selection.

An explicit PHP `$settings['h5p_themer_native_density_enabled']` overrides the
checkbox: boolean TRUE enables; boolean FALSE disables; any other present value,
including NULL, disables with an administrator diagnostic. Remove the override
to use the checkbox. Environment overrides are never copied into configuration.

Qualified players offer three native buttons. No button is pressed before an
explicit choice, even if the original activity already uses a native profile.
Small/Medium/Large persist through theme switches and same-tab reload/navigation.
There is no visible Density reset button. The separate theme Reset leaves Density
unchanged. The controller retains `resetOverride()` for
clearing the preference and restoring each player's original spacing.
Core's unclassified baseline uses Small profile variables, not Large.

Theme schema-1/2 `density` fields remain valid and round-trip unchanged, but are
legacy metadata. Edit/Add/Duplicate forms and the overview no longer expose them.
New themes retain an internal compatibility value; copies retain their source value.
Upgrading from v0.4.2 introduces no configuration key, schema migration or update
hook; schema remains 1003. Administrator defaults and custom themes are preserved.
Rollback to matching v0.4.2 code needs no new reverse conversion. For the earlier
v0.4.1-to-v0.4.2 upgrade, the optional settings extension is unchanged: after saving
it, v0.4.1 cannot validate the new key, so that older rollback requires restoring
pre-upgrade settings as well as code. Never uninstall/reinstall as an upgrade.

See [v0.4.2 behavior, migration and acceptance](docs/v0.4.2-development.md).
Fresh pages must receive matching PHP metadata and JavaScript (Density transport
version 3). Refresh deployment caches in a separately authorized site context;
existing open pages retain their loaded code. Deployments require site-specific authorization.

## Documentation

- [Installation, activation, disable and recovery](docs/installation.md)
- [Theme administration and learner visibility](docs/administration.md)
- [Configuration contracts and import rules](docs/configuration.md)
- [Prepared v0.4.3 release notes](docs/release-notes-v0.4.3.md) and [changelog](CHANGELOG.md)
- [Release packaging requirements](docs/installation.md#v043-release-packaging-preparation)
- [Historical v0.4.2 release notes](docs/release-notes-v0.4.2.md)
- [Historical v0.4.1 release notes](docs/release-notes-v0.4.1.md)
- [Historical v0.4.0 release notes](docs/release-notes-v0.4.0.md)
- [Planned disposable v0.4.0 upgrade rehearsal](docs/v0.4.1-upgrade-rehearsal.md)
- [v0.4.1 readiness and developer tests](docs/v0.4.1-readiness.md)
- [Historical v0.4.0 readiness and evidence](docs/v0.4.0-readiness.md)
- [Development roadmap](docs/roadmap.md)

Maintained by [rezeau](https://github.com/rezeau).
Report reproducible bugs through the [issue tracker](https://github.com/rezeau/h5p-themer-for-backdrop/issues).

## License

Copyright (C) 2026 the H5P Themer for Backdrop contributors.
The combined module is **GPL-3.0-or-later**; see [LICENSE](LICENSE) and
[GPLv3](LICENSE-GPL-3.0.md). Original files retain their GPL-2.0-or-later grants
and [GPLv2 text](LICENSE.txt). MIT notices apply to the adapted Daylight, Lavender,
Mint and Sunset presets; preserve the [MIT notice](LICENSE-MIT-H5P-THEME-PICKER.txt)
and [full provenance and notices](THIRD-PARTY-NOTICES.md).
The software is provided without warranty. H5P is a separate dependency and is
not redistributed here. No official H5P, Moodle or Backdrop endorsement is implied.
