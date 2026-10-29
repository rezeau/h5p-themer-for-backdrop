# H5P Themer for Backdrop 0.4.3

Release metadata prepared as 0.4.3; not yet packaged or published.
Published stable baseline: v0.4.2, commit
`91415f7676b9091abd1bb2517fa28e5bd802d411`.
The tested implementation is committed locally as
`1fd43f00e9832d5f5e5edfcf112806b1f1d91f3d`. Its controlled local deployment and
user-reported Firefox acceptance precede this metadata/documentation preparation.
These preparation edits have not been deployed. Archive validation, integration,
tagging and publication still require separate authorization.

## Changes from 0.4.2

The learner selector offers only administrator-permitted colour themes. Site
default and Core remain internal states, represented by the disabled placeholders
**Using site settings** and **Using original H5P colours**. Previously saved Core
preferences remain valid. Enhanced and native fallback controls share the same
colour choices; keyboard exploration does not silently replace a hidden state.
The desktop toolbar gives Density its own space and avoids duplicate visible
placeholder/announcement text.

**Use site default theme** clears only the learner colour preference and follows
the current administrator Global Default, including a default hidden from explicit
learner choices. It preserves Density, answers, selections, score and feedback.
Administrator defaults, theme visibility, custom definitions and stored tokens
are not rewritten by selector changes.

Native learner Density now qualifies these exact official libraries:

| Official library | Exact version |
| --- | --- |
| H5P.TrueFalse | 1.8.24 |
| H5P.MultiChoice | 1.16.27 |
| H5P.Blanks (Fill in the Blanks) | 1.14.37 |
| H5P.MarkTheWords | 1.11.24 |

TrueFalse support is retained from 0.4.2; the other three types are added in
0.4.3. Fill in the Blanks uses machine name H5P.Blanks, not H5P.FillInTheBlanks.
All require Core API **exactly 1.28** and ready, uniquely owned, standalone,
top-level internal iframe playback. PapiJo variants, other library versions,
DIV/external/nested players, editors/previews and ambiguous or duplicate content
embeddings remain excluded from Density. Supported children inside Column,
QuestionSet or InteractiveBook do not qualify their compound parent. Separate
colour-theme eligibility may still apply to activities without Density support.

The existing **Small**, **Medium** and **Large** controls, version-3 transport and
independent tab-session preference keys are preserved. Original activity spacing
remains until an explicit Density choice. Theme changes and theme Reset leave
Density unchanged. Where sessionStorage is available, theme and Density choices
independently survive same-tab reload/navigation; neither is saved to a learner
account. There is no visible Density reset button. Small and Medium may share
native font sizes while using different spacing.

Server qualification reads the real main-content and installed-library identity,
versions and library ID. Fixed browser adapters validate the question container,
integration, iframe realm and root instance. The existing coalesced resize remains
owned by that instance. No custom Density CSS, word reconstruction or calls to
answer, scoring, Retry or solution APIs are added by Density styling. Saved/live
colour previews are unchanged; Live Density Preview remains deferred.

## Compatibility, upgrade and rollback

Backdrop CMS 1.x and the existing H5P integration exposing Core API exactly 1.28
remain required. Local implementation verification used Backdrop 1.35.2. Colour
changes depend on libraries consuming the relevant native theme variables.
Existing native Density enablement, learner-selection prerequisite and strict
PHP override precedence are unchanged; Density remains disabled by default.

Upgrading **from 0.4.2 to 0.4.3** introduces no configuration key, schema conversion
or update hook. Installed schema remains 1003. Retain administrator settings,
custom themes, authored content, library files and existing session preferences.
Earlier 0.4.1-to-0.4.2 migration/rollback guidance remains historical and must not
be presented as a new 0.4.3 migration.

For a separately authorized upgrade, back up matching code/configuration/data,
deploy coherent module files, refresh relevant caches and reload pages. Existing
open pages retain their loaded scripts and metadata. Rollback to matching 0.4.2
code needs no new reverse conversion. Do not uninstall/reinstall or replace H5P
content/libraries. A release-archive-based upgrade and rollback rehearsal remains
recommended; the earlier source deployment does not validate a future ZIP.

## Verification evidence

The accepted Phase 5 implementation passed:

| Check | Recorded result |
| --- | --- |
| Complete JavaScript suite, Node 22.23.1 | 733 passed; zero failures, skips or cancellations |
| Complete PHP 7.4.33 suite | 4,072 assertions passed; zero failures |
| Complete PHP 8.3.18 suite | 4,072 assertions passed; zero failures |
| Complete PHP 8.4.22 suite | 4,072 assertions passed; zero failures |
| Phase 5D Firefox acceptance | 19 user-reported passes |

See the [Phase 5 acceptance record](v0.4.3-markthewords.md) for the complete numbered
manual sequence and automated coverage. The 19 passes include standalone display,
all Density settings, selection/state preservation, scoring/feedback, Retry,
Show Solution, Lavender/Mint independence, theme/Density reload persistence,
keyboard, 200% zoom, 375px viewport, a 300–400-word passage, QuestionSet embedded
exclusion and TrueFalse/MultiChoice/Blanks regressions. Earlier
[MultiChoice](v0.4.3-readiness.md), [Blanks](v0.4.3-blanks.md) and
[selector](v0.4.3-selector.md) milestones keep their own historical counts.
Release-preparation verification results are reported separately; these recorded
counts do not certify a future package or publication.

## Known limitations

Long MarkTheWords passages have relatively generous vertical line spacing. This
is a non-blocking observation and possible future refinement, not a confirmed
defect or a request for a CSS change.

The Firefox results are user-reported; Firefox version and individual test dates
were not supplied. Other browsers, screen readers, forced colours and additional
library versions are not certified. QuestionSet exclusion was manually accepted;
no new manual Column/InteractiveBook or PapiJo playback acceptance is claimed.
Exact machine names and versions do not authenticate modified library bytes.
Themer does not save learner answers across reloads, and storage failures cannot
guarantee preference persistence after navigation.

## Packaging and publication status

The intended archive has one `h5p_themer/` root containing approved production
files, metadata, user documentation and all five license/notice files. Exclude
tests, fixtures, developer tooling, Git metadata and local/private artifacts.
See [packaging requirements](installation.md#v043-release-packaging-preparation).
No runtime compilation or dependency-install step is required.

Phase 6C1 adds the explicit 72-file 0.4.3 allowlist and updates the existing
reviewer to validate it while retaining historical version profiles. Source review
records actual path/category/size/hash data and working-tree identity; it does not
create or certify an archive. Freezing the approved source manifest and validating
a separately authorized candidate archive remain required. No archive, tag,
GitHub Release or publication is created by metadata or source-review preparation.
