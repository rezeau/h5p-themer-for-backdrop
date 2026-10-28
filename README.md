# H5P Themer for Backdrop

Manage reusable color themes for compatible H5P activities in Backdrop CMS,
with optional native Density for exactly qualified player types.
This independent project does not modify authored H5P content or H5P libraries.

**H5P Themer v0.4.3 development — locally deployed, not published**
Published baseline: v0.4.2 (`91415f7676b9091abd1bb2517fa28e5bd802d411`).
The accepted local MultiChoice milestone is `3427d15dbc12c50d84fbc9e1757343168151eff7`.
This milestone additionally qualifies official **H5P.Blanks 1.14.37** (Fill in
the Blanks). It is deployed on backdrop2. The user reports all six Blanks Firefox
tests passed, including sizing/wrapping, scoring/Retry/solutions, both custom
themes, persistence, keyboard/focus, zoom/responsiveness and three-player behavior.
These observations are recorded separately from automated verification.
See the [Blanks implementation and test plan](docs/v0.4.3-blanks.md),
[MultiChoice milestone acceptance review](docs/v0.4.3-readiness.md),
[draft release notes](docs/release-notes-v0.4.3.md) and
[implementation and test plan](docs/v0.4.3-development.md).

## Main features

- Five built-in palettes, editable custom colors, Global Default and learner visibility.
- Learner theme picker with independent browser-tab session preferences.
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
H5P.MultiChoice 1.16.27 and H5P.Blanks 1.14.37 iframe players** in this working tree.
Other types/versions, nested, DIV, external and ambiguous players retain
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
There is no visible reset button. The controller retains `resetOverride()` for
clearing the preference and restoring each player's original spacing.
Core's unclassified baseline uses Small profile variables, not Large.

Theme schema-1/2 `density` fields remain valid and round-trip unchanged, but are
legacy metadata. Edit/Add/Duplicate forms and the overview no longer expose them.
New themes retain an internal compatibility value; copies retain their source value.
The settings extension is optional, so existing configurations need no migration.
After saving it, v0.4.1 code cannot validate the new key: rollback requires restoring
pre-upgrade settings as well as code. Never uninstall/reinstall as an upgrade.

See [v0.4.2 behavior, migration and acceptance](docs/v0.4.2-development.md).
Fresh pages must receive matching PHP metadata and JavaScript (Density transport
version 3). Refresh deployment caches in a separately authorized site context;
existing open pages retain their loaded code. Deployments require site-specific authorization.

## Documentation

- [Installation, activation, disable and recovery](docs/installation.md)
- [Theme administration and learner visibility](docs/administration.md)
- [Configuration contracts and import rules](docs/configuration.md)
- [v0.4.2 release notes](docs/release-notes-v0.4.2.md) and [changelog](CHANGELOG.md)
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
