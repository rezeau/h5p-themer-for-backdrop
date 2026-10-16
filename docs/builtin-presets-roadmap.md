# Built-in preset requirements and proposed roadmap

SPDX-License-Identifier: GPL-2.0-or-later.

## Status

This historical requirements/source record was created for Phase 1C. The catalog,
native administration, schema-2 inheritance, pickers and field groups are now
implemented. Phase 2A global colors and Phase 2B saved/live previews are also
implemented. Use the [current roadmap](roadmap.md), [installation guide](installation.md)
and [administration guide](administration.md) for present status and behavior.
The original requirements and pinned source inspection below remain relevant.

## Agreed product requirements

- Offer five built-in presets without requiring manual theme creation:
  **Daylight (default preset), Lavender, Mint, Sunset, and Dark**.
- Retain **H5P Core defaults**, meaning the original unmodified styling, as an
  explicit separate option. Daylight must not be treated as another name for it.
- Retain multiple independently named administrator-created custom themes.
- Built-ins cannot be edited directly. Administrators may duplicate a built-in
  into a new, independently editable custom theme.
- Do not overwrite, rename, reserve away, or migrate existing custom themes
  without an explicit migration design. A custom ID such as `daylight` must
  remain distinct from the built-in with that name.
- Exact preset definitions must come from inspected upstream source. Do not
  invent approximate colors or silently omit unsupported properties/values.
- Provide visual color pickers alongside hexadecimal inputs in the native
  administration interface. Keep the two input methods consistent with the
  approved validation and value representation; blank/unset remains distinct
  from an explicit black color. No color picker is implemented in Phase 1B.
- Color-setting groups are collapsed by default. General settings are expanded
  by default. After validation, automatically expand every group containing an
  error so that the affected controls and messages are visible.

Daylight is the default among built-in presets. That requirement does not
authorize changing an existing null/custom global selection. The fresh-install
selection policy and any storage transition must be explicit in the Phase 1C
design. Existing null selections continue to mean Core defaults until an
authorized selection change or separately approved migration.

## Upstream source inspection

Reference: [Matheu46/moodle-local_h5pthemer](https://github.com/Matheu46/moodle-local_h5pthemer).
The source was inspected at commit
[`478fed30d2a4af58638f2ddbea3603db1d4d8c5f`](https://github.com/Matheu46/moodle-local_h5pthemer/commit/478fed30d2a4af58638f2ddbea3603db1d4d8c5f).
Use immutable file references when preparing the eventual catalog; recheck
upstream before implementation and record any deliberate version change.

| Required preset | Definition source at the inspected commit |
| --- | --- |
| Daylight | `themes.daylight` in the picker bundle linked below |
| Lavender | `themes.lavender` in the picker bundle |
| Mint | `themes.mint` in the picker bundle |
| Sunset | `themes.sunset` in the picker bundle |
| Dark | `getBuiltInPresets().dark` in the Moodle preset module linked below |

Inspected files:

- [Picker bundle: `js/h5p-theme-picker.js`](https://github.com/Matheu46/moodle-local_h5pthemer/blob/478fed30d2a4af58638f2ddbea3603db1d4d8c5f/js/h5p-theme-picker.js)
  contains the first four complete palette maps, distinct from preview swatches
  and density definitions. Do not recreate palettes from just four base colors.
- [Moodle additions: `amd/src/presets.js`](https://github.com/Matheu46/moodle-local_h5pthemer/blob/478fed30d2a4af58638f2ddbea3603db1d4d8c5f/amd/src/presets.js)
  supplies Dark as a complete palette.
- [Composition: `amd/src/settings.js`](https://github.com/Matheu46/moodle-local_h5pthemer/blob/478fed30d2a4af58638f2ddbea3603db1d4d8c5f/amd/src/settings.js)
  passes the Moodle additions and saved user presets to the picker.
- [Third-party metadata](https://github.com/Matheu46/moodle-local_h5pthemer/blob/478fed30d2a4af58638f2ddbea3603db1d4d8c5f/thirdpartylibs.xml)
  identifies picker version `0.0.13`, Oliver Tacke, MIT. The Moodle preset
  module identifies GPL v3 or later. Preserve the applicable provenance/notices
  when implementation is authorized; no upstream implementation is copied here.

### Compatibility findings and approved storage decision

The existing Backdrop schema allows 33 named properties and opaque `#RGB` or
`#RRGGBB` values only. The inspected palettes include:

- A `color-mix(...)` value in the four picker palettes and an `rgba(...)` value
  in Dark for the light CTA contrast property.
- `--h5p-theme-contrast-cta-dark`, which is outside the current allowlist.

Consequently, these palettes cannot be inserted unchanged as current schema 1
custom definitions. Step 2A preserves all exact values in the immutable catalog
and uses pinned bases plus strict hex overrides for schema-2 copies. Runtime
token support remains a separate Phase 2 qualification.
Do not approximate transparency with an opaque color, drop tokens silently,
or relax administrator-entered CSS validation merely to make the import pass.
Picker accessibility labels are upstream metadata, not proof that the Backdrop
integration or every H5P content type meets an accessibility standard.

## Proposed Phase 1C architecture

1. **Immutable catalog:** ship reviewed, versioned built-in data separately
   from active custom-theme configuration. Record each preset's ID, label,
   exact values, source commit, and definition version. Make presets available
   by catalog lookup, with no install-time creation of ordinary custom themes.
2. **Distinct identities:** resolve a logical selection by kind (`core`,
   `builtin`, or `custom`) and stable ID. Step 2A implements the read/config
   contract, and Step 2B implements native form handling. Display Core defaults, Built-in presets, and
   Custom themes as distinct choices. Never infer a built-in from a legacy
   custom ID or from a label.
3. **Preservation and storage design:** specify versioned read/import/export
   behavior before changing schema. Preserve legacy null as Core defaults and
   legacy selected IDs as custom references; preserve all custom labels,
   colors, densities, and IDs. No automatic reseeding, overwrites, or mass
   conversion. A migration design and approval must precede any migration.
4. **Native administration:** show built-ins as read-only and enforce that
   restriction in submit handlers, not only the controls. Offer Duplicate into
   a custom theme with a new independent ID. Built-ins are also not ordinary
   deletable custom records. Preserve existing custom CRUD behavior. Add visual
   color pickers beside hex inputs; default color groups to collapsed and
   general settings to expanded. Validation must reopen groups containing
   errors. These are future UI requirements, not current implementation claims.
5. **Faithful duplication:** decide how exact trusted preset values survive
   conversion to an editable custom definition. Options include an approved
   richer custom schema or a versioned preset base plus editable safe overrides.
   The approved Step 2A choice is a versioned pinned base plus strict hex
   overrides. Step 2B implements preset duplication into an initially empty
   override map. A copy retains its exact base version across later catalog updates.
6. **Verification:** test all five catalog entries, exact source parity,
   identity collisions, immutable mutation guards, independent duplication,
   reference validation, legacy schema/import preservation, and default
   selection/clearing. Also test picker/hex consistency, initial group expansion,
   and automatic expansion on validation errors. Phase 1C must not inject
   player assets or modify content.

## Development sequence

| Phase | Scope | Current state |
| --- | --- | --- |
| 1B | Native administration and safeguards. | Implemented and verified; historical generated-data exception documented. |
| 1C | Exact immutable presets, named customs, inheritance, pickers and field groups. | Implemented. |
| 2A | Global color application through existing API-1.28 assets. | Implemented; density remains unapplied and library/embed coverage is bounded. |
| 2B | Saved-theme and unsaved custom-color previews. | Implemented; user-confirmed local Firefox results are in the README. |
| 2C | Learner-controlled site-wide selection. | PLANNED, not implemented. |
| 2D | Accessibility-oriented themes. | PLANNED, not implemented. |

See [the current roadmap](roadmap.md) for requirements and unresolved investigation.
The earlier [integration bridge proposal](integration-bridge-proposal.md) remains
a historical, unapplied technical reference. Current global colors use the
existing H5P styles hook and do not require that bridge or H5P source changes.
