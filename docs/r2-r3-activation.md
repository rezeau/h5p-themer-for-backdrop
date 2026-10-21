# R2/R3 activation journal — isolated checkpoint 2

This implementation is not deployed. Checkpoint 3 now adds
[creation/copy/edit/delete publication safeguards](r2-r3-publication.md).
Checkpoint 4 now adds [visibility controls](r2-r3-visibility-admin.md).
Stateless guards reject native theme/policy imports; custom import coordination
and its separate journal lifecycle have been removed. R1 help is unchanged.
There is no shipped visibility-policy default configuration file.

## Native lifecycle witness

The independent witness is the installed H5P Themer schema version in Backdrop's
system table, read through backdrop_get_installed_schema_version() with its
request cache reset. Normal requests load the Core install API definitions if
needed; they do not run an installer, updater, service bootstrap or writer.
Known pre-barrier values are 0 (the accepted release) and 999 (Core's queued
update start value). Missing rows, negative/unknown values and read failures are
uncertain, never evidence of a legacy installation. Version 1000 or later
requires verified activation.

Core update_do_one() writes the completed schema version after a successful hook.
The first update, h5p_themer_update_1000(), only prepares and verifies a frozen
journal; it never writes policy. Core then persists 1000 before invoking update
1001. That second hook calls the guarded initializer. A failed initialization
leaves the barrier in place. Fresh installation records the latest update number,
1001 at checkpoint 2, before h5p_themer_install() prepares and initializes its
policy. Checkpoint 5B adds 1002/1003 import-security hooks; genuine fresh installs
now record 1003 and initialize both separate journals.

Preparing the snapshot in the first hook is essential: after the barrier,
journal loss must not be confused with permission to capture a fresh catalog.
Preparation before Core records the barrier is not a completed activation.
Once the barrier has been recorded, every missing or uncertain record is closed.
No update number is written manually by Themer.

The normal module discovery code preserves installed schema metadata; cache
rebuilds do not remove its positive installed version. Configuration sync does
not export or reset this system-table witness. Deliberate uninstall, manually
forcing historical update versions, tampering with metadata or restoring an
older database are outside this guarantee and require a separately reviewed
recovery plan. No finite record scheme can detect erasure of all its history.

## Journal

The non-exported state key is h5p_themer.visibility_activation. Schema version 1
has these exact fields:

- schema_version: integer 1.
- status: prepared, pending, complete or failed.
- mode: upgrade or fresh.
- operation: 32 lowercase hexadecimal characters.
- snapshot: the complete frozen policy (schema_version and themes).
- snapshot_hash: SHA-256 of the serialized frozen policy.
- catalog_hash: SHA-256 of stable references, original custom definitions,
  effective resolved colors and effective Themer settings.
- cache_pending: strict Boolean.
- failure: an empty string or the fixed code interrupted.

There are no credentials, rendered CSS, private descriptions or exception
messages in public settings. The journal stores tokens/Booleans and digests,
not copies of custom themes or palettes. Hashes detect inconsistent snapshots;
they are not authentication against a malicious database writer.

The actual state_initialize() API is used to preserve array-key existence,
including a present NULL journal. state_get() with a default cannot distinguish
that case from absence. Core may warm its normal state cache; Themer's ordinary
read paths never persist a policy, journal, witness or activation repair.

## Initialization and recovery

Both preparation and initialization acquire h5p_themer_admin_write. They refresh
the lease before persistence, reload Themer Config objects, and reject an active
configuration synchronization or conflicting policy overrides. Raw and effective
policies use the existing strict validator. Schema-1/2 custom definitions and
preset data are never rewritten.

Upgrade preparation captures every existing valid optional choice as true.
Fresh preparation makes optional built-ins true and any pre-existing orphan
custom definitions false. Fixed choices never enter the editable policy map.

Initialization requires a valid frozen journal and the persisted barrier. It
checks the current catalog against the frozen fingerprint, writes pending state,
saves policy only if absent, reloads/verifies the result, then records completion
with cache_pending true. It invalidates native page/layout output using
cache_clear_all() with no arguments and verifies cache_pending false afterward.
A complete record with cache_pending true remains closed and retries only the
cache/completion phase. It never rewrites a valid policy, even if its choices
have since been hidden.

A completed valid initialization is a no-op. Pending/failed recovery may resume
only when the catalog still matches and policy is absent or exactly equals the
frozen snapshot. Missing/malformed journals after the barrier are never
recaptured. A changed, malformed or deleted-after-completion policy is never
automatically replaced by the old visible map. Such cases require explicit
recovery review. Concurrent additions or palette changes stop recovery.

Failures return constant diagnostics, release the lock and retain a prepared,
pending, failed or cache-incomplete record where possible. A failure record is
best-effort; the independent barrier still fails closed if state persistence
also fails. There is no automatic rollback that could republish optional themes.
A successful completion marker is never downgraded on an error.

Site default, Core and Daylight remain available whenever the validated catalog
is usable. A hidden global theme still applies through Site default. Normal
legacy behavior is permitted only with known pre-barrier metadata and no journal.
Existing open pages and cached browser documents are not remotely revoked.

Run activation under a quiesced, explicitly authorized maintenance window.
Native update.php itself has broader cache/maintenance behavior; this document
does not authorize running it on the local site. Re-running install callbacks
by hand is not a recovery API: fresh preparation is reserved for Core's genuine
fresh-install lifecycle. Use the frozen initializer for supported retries.

## Qualification

Focused command:

    php -n tests/visibility-activation.php --backdrop-root=<absolute-Backdrop-source-root>

Run under PHP 7.4.33 and 8.4.22, followed by the full PHP and Node suites.
The harness executes actual installed-schema getter/setter, state API and
update_do_one() function bodies extracted from the installed Core sources.
Strict memory database/cache collaborators handle only their expected tables
and queries. No site settings, database connection or live bootstrap is used.
Full module_enable(), HTTP requests and live update.php are not executed.

Tests cover persistence ordering, fresh installation ordering, missing/invalid
witnesses, NULL/malformed journals, all initializer persistence boundaries,
post-write exceptions, safe retries, lost journals, hidden choices, hidden
global defaults, catalog drift, overrides, sync conflicts, lock contention,
lease loss, exact custom-definition preservation and cache completion.
These are automated isolation tests, not Firefox or accessibility acceptance.

The policy is registered for native validation. Stateless guards reject native
theme/policy imports at validation and application. The custom import coordinator
and its separate journal lifecycle have been removed. Updates 1000/1001 retain
the frozen visibility activation; 1002/1003 are compatibility no-ops. Fresh
installation initializes visibility only. Existing 1003 installations do not
replay historical hooks, and obsolete import journal data is left untouched.
Before deploying over the former coordinator, resolve unfinished/damaged
operations and pending cache refreshes; removing its runtime gate is not recovery.
Arbitrary direct Config saves remain outside the supported publication contract.
Checkpoint 3 resets stale true entries before machine-ID reuse in the shared
administration handlers. Rolling back to alpha.2 would ignore
visibility controls and can expose every valid theme again; preserve metadata
and use a separately authorized rollback procedure.
