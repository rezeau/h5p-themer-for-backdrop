# R2/R3 phase 1: learner visibility foundation

This is isolated development based on published v0.2.0-alpha.2. It has not been
installed on any Backdrop site. R1 help, visibility checkboxes, initialization,
creation/copy lifecycle changes and configuration-import coordination are deferred.
Phase 2B checkpoint 2 now adds [journaled activation](r2-r3-activation.md); the
Phase 1 and checkpoint 1 descriptions below are historical development stages.
No version metadata or accepted palette has changed.

## Policy and reads

The separate object is `h5p_themer.learner_visibility`:

```json
{
  "schema_version": 1,
  "themes": {
    "builtin:mint": true,
    "builtin:dark": false,
    "custom:example": true
  }
}
```

Only integer schema version 1 and actual Boolean map values are accepted.
Tokens are exact ASCII namespaced machine IDs, never labels. Unknown fields,
noncanonical tokens and entries attempting to govern fixed choices are invalid.
Native `_config_name` and Boolean `_config_static` metadata are supported.
The reader honors effective whole-map configuration overrides. It returns absent,
initialized or malformed state, and never saves, repairs or invalidates caches.

- Absent stored policy preserves the full validated legacy catalog. Overrides
  alone cannot activate a missing object.
- Initialized policy requires explicit true for optional themes. False or missing
  entries are hidden. Unknown/deleted IDs never manufacture catalog entries.
- Malformed/unreadable policy hides all optional choices; Site default, Core and
  Daylight remain if the validated catalog is available. Full-catalog failure
  retains the existing no-selector/global-playback fallback.
- Safe, constant diagnostics appear through the existing authenticated overview.
  No policy errors, raw values or private metadata enter learner settings.

The three fixed choices are Site default, H5P Core defaults and builtin:daylight.
They cannot be disabled, and Core remains distinct from Daylight. Permissions
never bypass learner filtering, including administrator playback.

## One public catalog

`h5p_themer_learner_catalog()` filters `h5p_themer_theme_catalog()`.
Options, opaque selection keys, icon pairs and explicit-choice CSS all consume
that filtered view. The original full catalog and existing palette resolver are
unchanged for administration, inheritance, global defaults and authorized preview.
A visible custom may inherit a hidden built-in without exposing that built-in as
an explicit learner choice. No theme definitions or color serialization changed.

A hidden global theme still applies through Site default and its icon. This is
intentional: hiding suppresses explicit selection, not the administrator's global
palette. Visibility is not confidentiality; old public CSS may remain retrievable.

PHP and JavaScript now accept three options and require the fixed identities,
rather than relying only on a minimum count. No sessionStorage key or format
changes. A hidden saved choice is rejected against a fresh server payload and
falls back to Site default. Existing open pages/BFCache are not remotely revoked;
there is no new endpoint, polling, network request or persistence mechanism.
Existing iframe validation, player eligibility, lifecycle handling, compact CSS,
keyboard behavior, ARIA and native-select fallback are unchanged.

## Activation safeguards and unfinished lifecycle work

At Phase 1 there was no shipped policy JSON template, installation/update hook,
configuration import handler or write API. Checkpoint 2 adds explicit lifecycle
hooks and a guarded initializer, while continuing to ship no policy template.
A plain code update on a never-activated installation retains legacy visibility and cannot restrict an upgraded installation
before its existing choices are captured. Do not deploy or manually initialize
this foundation as a complete R2/R3 feature.

The initialization design, implemented by checkpoint 2, captures every existing
valid optional token as true in one explicit authorized operation before enabling new-theme workflows.
Fresh installation defaults must be distinguished from that upgrade snapshot.
Future forms must use supported Config validation, the existing shared write lock,
and bounded Themer cache invalidation after a successful policy change. Import
validation and transaction ordering must be reviewed separately; reads must never
perform initialization. Checkpoint 2 now prevents policy or journal deletion
from restoring legacy visibility after the persisted barrier. Creation, copying,
deletion cleanup and coordinated import publication safeguards remain deferred.

A Boolean map has no creation generation. A stale true entry is ignored while its
custom theme is absent, but would match a subsequently recreated same machine ID.
The later creation, copy and import stage MUST reset/remove a reused token before
publishing its new definition, under the same lock, and test that complete flow.
This phase tests the filtering of stale/deleted IDs and an explicit reset scenario;
it does not claim automatic deletion/recreation protection is already implemented.
Absent policy also cannot distinguish old from newly created themes: initialization
is a required prerequisite to hidden-by-default creation behavior.

## Phase 2B checkpoint 1: malformed-storage correction

The reader now checks storage existence independently, then loads a fresh native
Config object. Backdrop decodes stored JSON false as FALSE, and Config::load()
treats that value as absent; isNew() alone therefore cannot establish absence.
Only an explicit FALSE existence result selects legacy behavior. A present object
that reads as FALSE, an uncertain existence result, read/decode failure, scalar,
array or invalid policy object fails closed for optional choices. A valid override
cannot conceal malformed stored data. Valid stored policies retain effective
whole-map overrides and strict Boolean validation. No ordinary read writes data.

At checkpoint 1 the activation journal was not implemented. Genuine absence
still cannot distinguish a never-initialized legacy site from policy deletion
after activation. That fallback was temporarily unsafe after activation.
Checkpoint 2 now enforces the independent witness and journal. Deployment still
requires the later publication lifecycle and import safeguards.

The focused native-storage regression can be run independently:

    php -n tests/visibility-policy.php --backdrop-root=<absolute-Backdrop-source-root>

It uses the actual Backdrop file storage, JSON decoder and Config loader in a
private temporary directory. Only existence/read-failure cases use fault injection;
no site settings or database are loaded. The full PHP runner includes these checks.

## Verification

Run the complete PHP suites under explicit PHP 7.4.33 and PHP 8.4.22:

```text
php -n tests/run.php --backdrop-root=<absolute-Backdrop-source-root>
```

For Node 22.23.1 set `BACKDROP_SOURCE_ROOT` and `PHP_EXECUTABLE` explicitly,
then run `node --test tests/js/*.test.cjs`. The PHP worker uses native Config
class definitions with in-memory storage; it never loads site settings, bootstraps
Backdrop or connects to its database. Asset tests use private temporary storage.
The worker's visibility scenario environment variable is test-only.

Focused tests cover strict policy states and overrides, fixed choices, invalid,
unknown and deleted IDs, schema-1/2 resolution, unchanged stored bytes, all public
projections, hidden global defaults, hidden inheritance bases, permission-neutral
filtering and safe admin diagnostics. JavaScript contracts use the actual filtered
PHP payload for native fallback, enhanced keyboard operation, session cleanup,
multiple DIV/internal iframe players and unchanged incompatible activities.
DOM doubles do not qualify Firefox layout, assistive technology or other browsers.
Manual acceptance and safe activation remain later checkpoints.
