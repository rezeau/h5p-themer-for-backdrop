# H5P Themer for Backdrop

Manage reusable color themes for compatible H5P activities in Backdrop CMS,
with optional native Density for a narrowly qualified player type.
This independent project does not modify authored H5P content or H5P libraries.

**H5P Themer v0.4.0**
v0.2.1 is the earlier stable baseline; v0.3.0 was withdrawn.

## Main features

- Five built-in themes: Daylight, Lavender, Mint, Sunset and Dark.
- Named custom themes with editing, duplication and learner visibility controls.
- A Global Default theme and an optional learner theme dropdown.
- Learner theme choices remembered for the current browser tab when session
  storage is available; theme changes preserve current answers and feedback.
- Saved-theme previews and live previews of unsaved color edits.
- Optional native Small, Medium and Large Density using saved custom themes.

Previews remain representative color-only samples. Independent learner Density
buttons are deferred to v0.4.1; Live Density Preview is deferred.
Themer does not save H5P answers across navigation or reload.

## Requirements and compatibility

- Backdrop CMS 1.x; local verification used **Backdrop 1.35.2**.
- An enabled Backdrop H5P integration exposing **H5P Core API exactly 1.28**.
  Older/newer APIs and arbitrary integration releases are not qualified.
- PHP **7.4.33**, **8.3.18** and **8.4.22** have been verified.
- Writable local public-file storage for generated theme styles.
- Color changes require an H5P library that consumes the relevant theme variables.

Native Density supports only **qualified top-level H5P.TrueFalse 1.8.24 iframe
players**. Other types/versions, nested, DIV, external and ambiguous players retain
their original Density. Color theming and the learner dropdown have their own
compatibility rules. Broader browser/accessibility support is not certified.

## Install and configure

1. Install and enable the Backdrop H5P integration first; confirm existing playback.
2. Place the module in a directory named `h5p_themer`, normally
   `<Backdrop-root>/modules/h5p_themer`, and enable it at `admin/modules`.
3. Open `admin/config/content/h5p-themer` with **Administer H5P themes** permission.
4. Select Global Default or create a custom theme. New installations use
   **H5P Core defaults (no overrides)**. Built-ins can be viewed or duplicated.
5. Optionally enable **Allow learners to select their H5P theme**. Publish optional
   choices using **Available to learners** and **Save visibility**; new custom
   themes start hidden. Reload activity pages after configuration changes.

For upgrades, back up the site and follow the
[installation, upgrade and cache instructions](docs/installation.md).
Existing v0.2.1 installations at schema 1003 need no new migration; do not
uninstall/reinstall or replay update hooks. Saved theme schemas remain unchanged.

### Optional native Density

Density is **disabled by default**. Installation-level activation requires:

```php
$settings['h5p_themer_native_density_enabled'] = TRUE;
```

Only boolean `TRUE` enables it. Refresh the intended site's caches and reload
playback as described in the [activation guide](docs/installation.md#v021-to-v040-development-upgrade-and-rollback).
Open pages keep their supplied settings until reload.

On qualified players, custom themes apply saved Density; Core and built-ins
restore the player's original profile. Site default follows effective Global
Default, including a hidden custom default. Small is compact, Medium intermediate
and Large spacious; these profiles do not set container width.

## Documentation

- [Installation, activation, disable and recovery](docs/installation.md)
- [Theme administration and learner visibility](docs/administration.md)
- [Configuration contracts and import rules](docs/configuration.md)
- [v0.4.0 release notes](docs/release-notes-v0.4.0.md) and [changelog](CHANGELOG.md)
- [Readiness, accepted evidence and developer test commands](docs/v0.4.0-readiness.md)
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
