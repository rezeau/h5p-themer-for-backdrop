# Development roadmap

SPDX-License-Identifier: GPL-2.0-or-later.

## Implemented baseline

Phase 1B administration, Phase 1C built-in presets and custom inheritance,
Phase 2A global color runtime, and Phase 2B saved and unsaved-color previews are
implemented. The current user guide is [installation](installation.md) and
[administration](administration.md). Earlier phase records retain historical
test totals and investigation findings; they do not override current guidance.

The five existing built-ins remain Daylight, Lavender, Mint, Sunset and Dark.
Core defaults remain a separate option. No roadmap item changes existing schemas,
preset catalog values, custom definitions or the current global selection.

## Phase 2C — PLANNED: learner-controlled site-wide theme selection

This feature is not implemented. Its requirements are:

- Optional administrator enable/disable, **disabled by default**.
- Administrators approve which themes learners may select.
- One selector controls all H5P activities on the page.
- A preference is remembered across pages.
- Theme changes must not reload an H5P activity or lose learner progress.
- Multi-activity and iframe support require architecture and browser investigation.

Preference storage, scope, access policy, defaults and precedence still require
design. So do delivery to nested or cross-origin frames, dynamically created
activities and independent embedded players. Site-wide consistency and preserving
in-progress activity state are acceptance requirements, not established capability.
No persistence mechanism, messaging bridge or hot-switching script is introduced
by this roadmap.

## Phase 2D — PLANNED: accessibility-oriented themes

These proposed themes are not implemented:

- High Contrast Light.
- High Contrast Dark.
- Color-Vision Friendly.
- Low Glare.

Their colors and behavior must be designed and tested before addition. Consider
text, focus, borders, feedback and non-color cues across representative libraries
and states, with keyboard and assistive-technology testing. A theme name or an
upstream WCAG subtitle does not certify the entire module or arbitrary authored
H5P content. No approximate palette or accessibility guarantee is introduced here.

## Existing limitations and further investigation

Density application, per-content assignments, comprehensive external/nested embed
qualification and broad library/browser coverage are not part of the implemented
color runtime. Current previews are representative samples rather than running
activities. A 375px Firefox viewport was usable but cramped; responsive-layout
improvements remain a future task.
