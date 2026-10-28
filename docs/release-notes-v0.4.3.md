# H5P Themer v0.4.3 — draft release notes

Development build: 0.4.3-dev. Not committed, packaged or published as v0.4.3.
Published baseline: v0.4.2, commit `91415f7676b9091abd1bb2517fa28e5bd802d411`.

## Changes from v0.4.2

Native learner-controlled Density now also qualifies official H5P.MultiChoice
1.16.27, alongside H5P.TrueFalse 1.8.24. Both require H5P Core API exactly 1.28
and uniquely owned, ready, top-level internal iframe playback. Other library
versions, DIV/external/nested players and ambiguous or duplicate content embeddings
remain excluded. Fill in the Blanks is not newly supported for Density.

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

Automated regression: 612 JavaScript tests, plus 4,006 PHP assertions on each of
PHP 7.4.33, 8.3.18 and 8.4.22. Includes configuration/import/visibility, v0.4.2
behavior, saved/live color previews and preserved historical readiness checks.
See the [final review](v0.4.3-readiness.md) for rerun evidence and provenance.

The user reports six MultiChoice Firefox acceptance passes: Density choices;
scoring/feedback/Retry; theme switching/session persistence; keyboard/focus;
200% zoom and 375 px viewport; and no console errors. The user also reports that
the mixed-player page changes TrueFalse and MultiChoice Density, leaves Blanks
Density unaffected, and supports visual themes on all three without unexpected
behavior. These are user observations, not agent browser automation.

Other browsers, screen readers, forced colors and additional library versions
are not qualified by this report. See the final review for additional untested
layout, lifecycle and content-specific cases. Official machine names/version
checks do not authenticate arbitrarily modified library bytes.

## Deployment and rollback

The development build is already installed on local backdrop2. No installation
changes are part of the final review. No schema migration, new configuration key,
library update or authored H5P content change is required by this implementation.
The deployment's full-row H5P fingerprint discrepancy was resolved as population
of `filtered` cache fields for content IDs 8 and 9; authored JSON was unchanged.

For a separately authorized deployment, back up matching module/configuration and
database data, deploy coherent PHP/JS files, refresh relevant module/output/JS
caches and reload pages. Existing open pages retain their loaded code. Continue
using the existing Density setting and learner-selection prerequisite.

Rollback to v0.4.2 restores matching module code and refreshes caches. This change
adds no configuration conversion requiring reversal. Do not uninstall/reinstall
or replace H5P content/libraries. Earlier v0.4.1 rollback rules remain historical
and are not replaced by this narrower v0.4.2 rollback statement.
