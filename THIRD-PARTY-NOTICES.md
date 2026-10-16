# Preset provenance and licenses

The Step 2A catalog and test fixture contain extracted literal preset data,
not upstream JavaScript, runtime scripts, UI components or dependencies.
Extraction/adaptation date: 2026-10-16.

## Daylight, Lavender, Mint and Sunset

- Copyright (c) 2026 Oliver Tacke.
- License: MIT; full copyright, permission and warranty notice is retained in
  [LICENSE-MIT-H5P-THEME-PICKER.txt](LICENSE-MIT-H5P-THEME-PICKER.txt).
- Source: `themes` in `js/h5p-theme-picker.js`, line 1, in
  [Moodle revision 478fed30d2a4af58638f2ddbea3603db1d4d8c5f](https://github.com/Matheu46/moodle-local_h5pthemer/blob/478fed30d2a4af58638f2ddbea3603db1d4d8c5f/js/h5p-theme-picker.js#L1).
- Verified Git blob: `0cd5829b33050f1ee3314fe603911c3644134f2e`.
- Moodle's pinned `thirdpartylibs.xml` identifies the bundled H5P Theme Picker
  as version 0.0.13, MIT, Oliver Tacke. The bundle itself contains no license
  header; that pinned declaration establishes the bundled component's license.
- The full MIT notice was also verified at immutable upstream picker revision
  [2980767a72dc581ae89104d1ff0c837d5d3f210c](https://github.com/otacke/h5p-theme-picker/blob/2980767a72dc581ae89104d1ff0c837d5d3f210c/LICENSE).
  That revision is provenance for the license text, not a claim that Moodle's
  entire bundle was built from that later revision.

## Dark

- Copyright 2026 Matheus Mathias.
- License: GNU GPL version 3 or, at your option, any later version.
- Source: `getBuiltInPresets().dark`, `amd/src/presets.js`, lines 33-71,
  [same pinned Moodle revision](https://github.com/Matheu46/moodle-local_h5pthemer/blob/478fed30d2a4af58638f2ddbea3603db1d4d8c5f/amd/src/presets.js#L33).
- Verified Git blob: `2529338c1ffdec331d84045772fa67e3bdf3c11a`.
- The source copyright and GPL-3.0-or-later attribution are retained in the
  catalog header and each preset's provenance. The full GPLv3 text is copied
  verbatim from the pinned Moodle `LICENSE.md` into
  [LICENSE-GPL-3.0.md](LICENSE-GPL-3.0.md).

## Combined module and adaptations

The new catalog and extracted fixture are covered by GPL-3.0-or-later, with the
MIT notices retained for their MIT portions. Existing project files retain
their GPL-2.0-or-later grants and the original GPLv2 text in `LICENSE.txt`.
The combined module containing the GPLv3 Dark adaptation must be conveyed
under GPL-3.0-or-later; this does not relicense the original MIT portions or
remove the existing files' original permissions.

This combination is permitted by the existing "or later" grants. See the
[GNU compatibility explanation](https://www.gnu.org/licenses/license-compatibility.en.html)
for GPL-2.0-or-later plus GPL-3.0-or-later, and the
[GNU compatible-license list](https://www.gnu.org/licenses/license-list.html#Expat)
for the MIT/Expat license.

Adaptations consist of a PHP array catalog, versioned lookup helpers, separate
presentation/provenance metadata, and an independent JSON test fixture. All
34 palette properties, exact value strings and case are retained. A preset's
upstream accessibility subtitle is presentation metadata, not a certification
of this module or of arbitrary H5P content. No upstream runtime/UI code is
included or executed.
