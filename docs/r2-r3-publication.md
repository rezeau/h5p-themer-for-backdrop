# R2/R3 publication lifecycle

The current implementation provides custom creation, copying, editing and deletion
safeguards in the existing shared native administration handlers. It is not
deployed. Checkpoint 4 now adds [visibility checkboxes](r2-r3-visibility-admin.md).
Custom import coordination has been removed; native theme/policy imports remain
rejected by stateless guards. Publication uses the existing theme schemas,
palettes, runtime selector, session preference format and theme resolver.

## Preconditions and ordinary reads

A form captures a fingerprint of the installed-schema witness, completed visibility
activation journal and valid original visibility policy in server-side Form API state. Builds and
validation never initialize policy or persist publication state. Failed initial
verification cannot become trusted just by rebuilding the same form.

Submission acquires h5p_themer_admin_write, reruns native permission/API,
stale-definition and publication validation, and renews the lease before writes.
A missing/unreadable witness, legacy state, incomplete/malformed activation journal or
policy, active policy override, or active config_sync marker blocks mutation.
Even an identical policy override is rejected because stored publication cannot
then be guaranteed. A stale policy or activation fingerprint requires a fresh
form. Fixed-choice guards and the filtered public catalog remain unchanged.

Completion is validated against the non-exported
`h5p_themer.visibility_activation` journal and independent installed-schema witness.
Activation must be complete with no pending cache refresh or failure record.
The activation snapshot is historical: valid subsequent visibility changes need not
equal the original upgrade snapshot. Comparing current policy to that snapshot
would reject deliberate hiding. A contradictory completion/failure record is
not accepted by the mutation guard. Update hooks 1000/1001 retain frozen visibility
activation. Hooks 1002/1003 are documented compatibility no-ops for the removed
import subsystem; installations already at 1003 do not replay earlier hooks.

## Restrictive-first persistence

All scratch creations, custom copies (schema 1 or pinned schema 2), and built-in
copies use the existing shared form:

1. Validate the candidate definition without writing it.
2. Write custom:destination_id = false to the existing policy.
3. Reload the entire policy and verify that only the intended map change occurred,
   with the same activation lifecycle.
4. Recheck destination absence and relevant source protections under the lease.
5. Save and reload the definition; require the exact validated data.
6. Verify publication state again, invalidate changed output, then report success.

The policy writer validates a native Config object and calls its public
ConfigStorageInterface::write() API, checking a strict true result. Native
Config::save() ignores a backend false return. Explicitly checking storage also
rejects failed writes when an existing tombstone was already false. The guard
refreshes request-local Config objects before read-back; it clears no site cache.

A copied definition keeps its palette/schema and pinned-base semantics. Source
visibility is never copied. A stale true destination entry is overwritten with
false, including when an old machine ID is reused. No optional theme is implicitly
published by theme creation or copying.

Editing requires the same safety preconditions but never writes policy. It
preserves true, false or implicit-hidden visibility exactly, including the
original policy bytes. Existing color, identity and override checks still apply.

Deletion rechecks confirmation, stale definition and both stored/effective
global-default protections. It writes and verifies false first, rechecks deletion
protections, deletes the definition, and verifies storage absence. The false
tombstone remains. A hidden theme may still be the global default and remains
protected from deletion; its colors remain available through Site default.

## Failures and output caches

Config files are not transactional. A restrictive-policy failure stops the
definition write/delete. An unreadable or malformed partial policy fails closed
through the existing reader. Definition failures leave the destination hidden
and never automatically restore true. Post-persistence exceptions and ignored
backend false returns are tested. Error messages acknowledge possible partial
writes and require inspection before a deliberate retry.

The output snapshot is captured before persistence. Changed public output is
invalidated on success or partial failure through the existing scoped
cache_clear_all() no-argument helper. Hidden, unselected additions do not change
public output and need no invalidation. Deleting/hiding a formerly public theme
does invalidate it, even if deletion then fails. Cache failures prevent a success
redirect and require an authorized targeted refresh/restricted learner access.
Existing open browser documents are not remotely revoked.

The shared lock is always released. This is cooperative locking, not a database
transaction or fencing guarantee against arbitrary out-of-band writers.

## Regression evidence

Focused command (with the exact PHP executable selected by the caller):

    php -n tests/visibility-publication.php --backdrop-root=<absolute-Backdrop-source-root>

Run focused and complete suites on PHP 7.4.33 and 8.4.22, plus the complete Node
suite on 22.23.1 with BACKDROP_SOURCE_ROOT set explicitly. The focused fixture uses
native Form API and Config implementations, strict memory state/schema database
collaborators, injected backend faults, and actual ConfigFileStorage in a private
temporary directory. It never loads site settings, connects to the live database,
or bootstraps a site. Native file deletion delegates only unlink inside that
private fixture, which is removed afterward.

Earlier administration tests now activate their isolated fixtures explicitly,
before read-only snapshots; form construction itself performs no activation.
Focused tests also exercise a genuine legacy production form build directly and
verify no configuration or activation-journal writes.

## Remaining boundaries

This is not full R2/R3 deployment readiness. Checkpoint 4 implements the native
visibility controls. Stateless guards reject native theme/policy create, update
and delete imports at both validation and pre-application hooks, without a
coordinator capability or journal-based authorization. Mixed plans containing
such mutations are rejected during validation; unchanged entries do not block
otherwise supported settings-only or unrelated native imports. Native exports
and configuration schema/reference validation remain supported.

The custom coordinator, import forms and import-journal initialization/recovery
machinery have been removed. Existing `h5p_themer.import_security` state is left
untouched and does not gate publication. It is distinct from the retained
visibility activation journal and installed-schema witness.

Arbitrary direct Config saves remain outside the supported publication contract.
The config_sync marker is
checked but does not provide an atomic interlock against an import beginning
between operations. Do not treat native shape validation as publication safety.

Ordinary theme deletion and ID reuse retain restrictive visibility tombstones.
Any partially applied operation from the former import coordinator requires
separately planned data/cache review before deployment; the current code neither
resumes those operations nor repairs them from retained import history.
Recovery/deployment must remain explicitly authorized and
quiesced. Rollback to alpha.2 ignores the visibility policy and may expose every
valid theme again. Keep the activation journal, witness and saved themes intact;
do not use code rollback as an assumed publication restriction.
