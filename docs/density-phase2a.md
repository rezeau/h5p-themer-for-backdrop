# v0.3.0 Density — scope and compatibility

This document describes the unreleased `feature/density-v030` implementation on
the published v0.2.1 baseline. The filename is retained for existing links; the
current behavior includes the later phases, not just Phase 2A.

## What Density changes

Custom themes in schemas 1 and 2 support **Small**, **Medium** and **Large**:

| Setting | Supported spacing |
|---|---|
| Small | Compact: 20% less than Medium |
| Medium | Normal: preserves the native playback baseline and qualified preview baseline |
| Large | Spacious: 25% more than Medium |

Density affects representative theme-preview padding, margins and gaps, plus one
experimental playback target: the vertical gap between evaluation rows in guarded
top-level themed **H5P.TrueFalse** content. It does not change button padding or
hit areas, typography, zoom, media dimensions or authored coordinates.

Playback Density works with a **Global Default custom theme**, whether learner
switching is enabled or disabled. When switching is enabled, **Site default**
follows Global Default and an explicit custom-theme choice supplies its own
Density. The existing learner choice remains page-wide across eligible players;
there is no new independent per-player preference.

Core and built-in palette selections preserve native playback spacing.
Unsupported libraries, nested questions, external embeds and ambiguously
identified players retain native spacing. Their existing eligible color styling
is independent of Density. This is not universal Density support across H5P.

Guarded playback Density requires JavaScript. Without it, colors remain available
and playback spacing stays native. Saved theme previews remain available without
JavaScript; unsaved live preview changes require JavaScript. In custom Edit,
subsequent color edits preserve the selected Density. Only Save persists edits.

## Upgrading from v0.2.1

No configuration migration, schema change or new configuration property is
required. Existing schema-1 and schema-2 custom themes, including imported themes,
retain their saved Density values. IDs, sparse color overrides and exact schema-2
preset pins retain their existing meanings. Density belongs to the custom theme;
it does not change its inherited preset colors. Visibility and tab-session theme
preferences keep their existing behavior.

Existing Small or Large themes acquire functional spacing in the supported
contexts after upgrading. In particular, previously stored Large values can now
make previews and supported playback more spacious without editing the theme.
Medium preserves the qualified baseline. Review representative activities on a
disposable copy before deploying. No H5P Core or content-library modification is
required. Module metadata identifies this unpublished candidate as v0.3.0.

## Qualification and limits

Browser evidence covers H5P Core API 1.28, True/False 1.8.24 and Question 1.5.56 in
the in-app Chromium browser. Saved/live Small, Medium and Large previews, including
later color edits, were measured. Global-only playback, learner overrides,
Core/Mint restoration, multiple independent iframes and representative legacy
schema-1 playback were also measured. Evaluated unique DIV playback was exercised
through native Core APIs; the normal Backdrop True/False formatter uses iframes.

At the measured 16px native evaluation-row baseline, Small/Medium/Large produced
12.8px / 16px / 20px. The rule uses the native baseline rather than assuming 1rem.
Necessary spacing changes request a targeted native resize without recreating
the player. The final targeted browser checks recorded zero requests for
same-spacing controller-mode changes and color-only stylesheet replacements,
and one request per affected iframe for genuine Small/Large changes. Tested
answers and scores were retained, with settled iframe heights containing content.

Final-source automated tests cover DIV transitions, ownership checks, cancellation
and document replacement. These are not claims that browser races were exercised.
A fresh native-import-to-playback browser sequence, evaluated DIV handoffs after
the final correction, physical touch, additional browser engines and broader
fullscreen/hidden-player cases remain optional qualification. Existing import
preservation tests and schema-2 playback evidence cover those paths separately.
There is no universal browser, library or accessibility certification.

Broader playback targets, question-button spacing and native H5P Density classes
are deferred enhancements. No global native spacing aliases are overridden.

## Technical reference

The shared runtime emits six module-owned spacing tokens for custom themes:

| Token suffix | Small | Medium | Large |
|---|---:|---:|---:|
| xl | 1.92rem | 2.4rem | 3rem |
| l | 1.28rem | 1.6rem | 2rem |
| m | 0.96rem | 1.2rem | 1.5rem |
| s | 0.64rem | 0.8rem | 1rem |
| xs | 0.416rem | 0.52rem | 0.65rem |
| xxs | 0.32rem | 0.4rem | 0.5rem |

Tokens are named `--h5p-themer-density-space-*`. Preview consumers use s/xs/xxs
multiplied by 0.8 to preserve the inspected unclassified Core preview baseline at
Medium. The playback row-gap uses the native spacing baseline multiplied by
0.8 / 1 / 1.25 instead. Density participates in generated CSS, stylesheet hashes
and cache invalidation. Structured rule generation scopes the consumer; one
shared instance lifecycle serves both global-only and learner-enabled modes.
Unsupported ownership fails closed for geometry while eligible colors continue.

## Historical evidence

Ignored local qualification reports under `artifacts/` preserve the detailed
commands, measurements and restoration records. They are development evidence,
not required installation files or packaged user documentation. Earlier reports
are checkpoint records: Phase 2A had preview consumers only; Phase 2C1 added
structured scoping; Phase 2C4 added guarded native resizing; Phase 2C6 added
Global Default playback without the learner picker. Phase 2C7 corrected and
browser-qualified the two Phase 2C6 request-suppression failures. Earlier claims
that playback Density requires learner switching, or that no playback consumer
exists, are superseded by this document.

Historical v0.2.0/v0.2.1 release notes correctly describe Density as stored but
unapplied in those releases and remain unchanged.
