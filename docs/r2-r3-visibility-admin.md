# R2/R3 visibility administration — isolated checkpoint 4

This checkpoint implements the R2 visibility controls in the existing Themes
overview. It is isolated development, not a deployment. R1 compact help is now implemented; it was previously
deferred. The custom import subsystem has been removed; Save visibility remains
the explicit publication action.

## Native administration form

The existing overview and Themes tab now invoke backdrop_get_form() with the
h5p_themer_overview callback. Existing tables, names, ordering, global-default
indicators, operations and invalid-definition rows remain. A final Available to
learners column contains native checkbox elements. Site default has a small
separate row explaining the effective global selection.

Site default, H5P Core defaults and Daylight are checked, disabled and described
as Always available. Optional valid built-in and custom choices are editable.
New/copied custom themes remain unchecked through checkpoint 3's lifecycle
safeguards. Invalid/unresolvable definitions receive diagnostics instead of an
editable publication control. There is no select-all or JavaScript autosave.

There is one explicit Save visibility action. Unchecked themes remain defined,
editable and previewable. A hidden global theme continues applying through Site
default. No global selection, theme definition, palette, inheritance, custom
schema, sessionStorage format or playback hook is rewritten.

## Rendering and accessibility

Checkboxes are real Form API children, with explicit visibility[token] parents.
A table pre-render callback moves their processed render arrays into the matching
cells after native input handling, removing the original children to prevent
duplicate rendering. It does not hand-build HTML inputs or bypass Form API.
It is idempotent and preserves existing table headings and operation columns.

Each checkbox has an escaped theme-specific label, including its typed identity,
using native invisible-label support. Fixed controls retain a visible Always
available description. Native checkbox, label, disabled state, CSRF token and
submit-button behavior are retained. No custom keyboard handler or dependency
is added. Automated tests inspect processed controls and native checkbox markup;
keyboard/screen-reader/layout acceptance still requires authorized browser tests.
No WCAG qualification is claimed.

## Read-only snapshot and mutation checks

Building the form requires the existing administer h5p themes permission.
Mutation also requires API compatibility and completed verified activation:
installed-schema witness, consistent completed journal and valid original and
effective policy. Policy and relevant settings/theme overrides, active config
synchronization and uncertain state disable saving with diagnostics. Reads
never initialize, repair or persist policy/journal data.

The server-side form snapshot includes a fingerprint of policy/lifecycle and
the existing complete catalog capture: original definitions (including invalid
objects), typed identities, resolved colors and effective settings. Rendered
rows must still match their catalog when controls are attached. Changes to
policy, themes, labels, palettes, definitions, settings or activation make the
form stale. A failed initial build cannot silently gain trust on rebuild.

Validation inspects raw submitted tokens and values as well as processed native
checkbox values. Unknown tokens/fields, fixed-choice alteration and malformed
values are rejected. Omitted optional checkboxes mean false; omitted disabled
fixed checkboxes remain true. A small root process guard marks malformed scalar
checkbox groups invalid before Core attempts nested input processing.

Submission acquires and renews h5p_themer_admin_write, reloads all relevant state
and revalidates permission, activation and the original snapshot under lock.
Tests simulate changes after initial validation but before lock acquisition.
The lock is released on all outcomes.

## Policy-only persistence and failure handling

A complete candidate is constructed and validated in memory. False tombstones
and unrelated existing policy entries are retained; unchanged implicit-hidden
entries need not be materialized. Fixed choices never enter the editable map.

A no-op save performs no configuration write or output invalidation. A changed
candidate uses the native public configuration storage API, checks strict true,
then reloads the entire policy and verifies it, the activation lifecycle and
the catalog capture. Definitions and settings are never saved by this form.

Changed learner output is invalidated through the existing no-argument
cache_clear_all() helper. This is page/layout output, not a full cache rebuild,
hook reset or JavaScript aggregation refresh. Partial-write paths also compare
output snapshots and invalidate stale output where possible.

A failure never reports success or automatically restores an earlier policy.
A write may have persisted despite an exception; the form says so and requires
inspection before retrying. Malformed partial policy remains fail-closed through
the existing reader. A valid file persisted before a later error can reflect
the explicitly submitted choices; file storage is not a transaction. Cache
failure requires restricted access and an authorized targeted refresh. Activation
and policy diagnostics remain available on the overview.

## Verification and remaining scope

Focused command:

    php -n tests/visibility-admin.php --backdrop-root=<absolute-Backdrop-source-root>

Run on PHP 7.4.33 and 8.4.22, then the complete PHP and Node.js 22.23.1 suites with
BACKDROP_SOURCE_ROOT set explicitly. Tests use native Form API/Config definitions,
strict in-memory database/state collaborators and actual ConfigFileStorage in
a private temporary directory. They do not bootstrap a site or access a live
database. Temporary native-file fixtures are removed afterward.

Stateless validation and application guards retain native theme/policy import
rejection without import journals or recovery. Arbitrary direct Config writers do not share this administration transaction boundary. The config_sync marker is checked, but
cannot provide atomic coordination with an import starting between operations.
These remain separate required safeguards before full R2/R3 deployment.

The overview menu callbacks changed; any future authorized deployment must
refresh the corresponding Backdrop menu registrations and verify native form
delivery. No menu/cache operation is performed by this checkpoint. There are
no new update hooks, configuration templates or schema changes here.
