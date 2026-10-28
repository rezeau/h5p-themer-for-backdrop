# H5P Themer v0.4.3 — draft release notes

Development build: 0.4.3-dev. The MultiChoice and Blanks milestones are locally
validated; the Blanks build is deployed on backdrop2 and user-reported Firefox
acceptance passed. This remains a development milestone rather than a published release.
No v0.4.3 release is packaged or published.
Published baseline: v0.4.2, commit `91415f7676b9091abd1bb2517fa28e5bd802d411`.

## Changes from v0.4.2

Native learner-controlled Density in this working tree qualifies official
H5P.MultiChoice 1.16.27 and H5P.Blanks 1.14.37, alongside H5P.TrueFalse 1.8.24.
All three require H5P Core API exactly 1.28
and uniquely owned, ready, top-level internal iframe playback. Other library
versions, DIV/external/nested players and ambiguous or duplicate content embeddings
remain excluded. Fill in the Blanks uses machine name H5P.Blanks, not H5P.FillInTheBlanks.

The existing Small, Medium and Large controls, color theme selection, tab-session
preference and version-3 transport remain unchanged. Before an explicit learner
choice, original activity spacing is preserved. Visual theme changes do not supply
or reset Density. Multiple distinct qualified players share the learner preference;
unsupported players retain their own spacing while retaining separate color support.

Server qualification verifies the installed exact library version and content
identity. Browser adapters additionally verify the corresponding themed question
container, integration identity, iframe realm and root instance. Resize remains
coalesced and targeted to the verified instance. No Density CSS or calls to answer,
score, reset or solution APIs were added. Color previews are unchanged; Live Density
Preview is not implemented.

## Verification and limitations

The current three-library implementation passes 646 JavaScript tests and 4,038
PHP assertions on each of PHP 7.4.33, 8.3.18 and 8.4.22. This includes controller,
exact-version and native input-sizing/keyboard tests; see the
[Blanks verification record](v0.4.3-blanks.md) for results and limitations.
Complete suites include configuration/import/visibility, v0.4.2 behavior,
saved/live color previews and preserved historical readiness checks.

The user reports six MultiChoice Firefox acceptance passes: Density choices;
scoring/feedback/Retry; theme switching/session persistence; keyboard/focus;
200% zoom and 375 px viewport; and no console errors. The user also reports that
at the earlier MultiChoice milestone the mixed-player page changed TrueFalse and MultiChoice Density, left Blanks
Density unaffected, and supported visual themes on all three without unexpected
behavior. Those observations describe the earlier MultiChoice milestone.

For the subsequent Blanks build, the user reports all six Firefox tests passed:
input widths/wrapping/layout at all Density settings; scoring/feedback/Retry/Show
Solution; Colorblind Friendly and High Contrast Light theme switching, persistence,
reload/navigation; Tab/Shift+Tab, caret/focus, answer preservation/native blur;
200% zoom and 375 px viewport; and correct Density response from all three players
with no console errors. These are user observations, not agent browser automation.

Other browsers, screen readers, forced colors and additional library versions
are not qualified by this report. See the final review for additional untested
layout, lifecycle and content-specific cases. Official machine names/version
checks do not authenticate arbitrarily modified library bytes.

## Deployment and rollback

The accepted Blanks build is installed on local backdrop2. No installation changes
are part of the final review/local commit. No schema migration, new configuration key,
library update or authored H5P content change is required by this implementation.
The deployment's full-row H5P fingerprint discrepancy was resolved as population
of `filtered` cache fields for content IDs 8 and 9; authored JSON was unchanged.
The subsequent Blanks deployment used consistent before/after row-level snapshots
and verified unchanged authored/derived H5P data, configuration and custom themes.

For a separately authorized deployment, back up matching module/configuration and
database data, deploy coherent PHP/JS files, refresh relevant module/output/JS
caches and reload pages. Existing open pages retain their loaded code. Continue
using the existing Density setting and learner-selection prerequisite.

Rollback to v0.4.2 restores matching module code and refreshes caches. This change
adds no configuration conversion requiring reversal. Do not uninstall/reinstall
or replace H5P content/libraries. Earlier v0.4.1 rollback rules remain historical
and are not replaced by this narrower v0.4.2 rollback statement.
