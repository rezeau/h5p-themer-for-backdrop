# Phase 2C Step 5B: session-only learner theme preference

SPDX-License-Identifier: GPL-3.0-or-later.

## Status and boundaries

Implemented and tested in an ordinary unlinked isolated copy. No live project,
H5P integration, active configuration, database or cache changes are authorized
or performed by this step. Controlled transfer and Firefox verification remain
separate checkpoints. Phase 2C is not complete; Phase 2D has not begun.

## Preference contract

One key is scoped to the actual validated Backdrop `basePath`:
`h5p_themer:learner-theme:v1:<basePath>` (root: `h5p_themer:learner-theme:v1:/`).
Missing or malformed base paths leave switching page-only. The value is only an
exact stable catalog token, such as `core`, `builtin:mint`, or
`custom:checkpoint_test`. No colors, generated CSS hashes, labels, objects,
account IDs or content IDs are stored. No configuration schema changes occur.

A valid token is restored after server-payload/context validation, before the
first selector construction/refresh. It is resolved to the current server-supplied
opaque CSS key. Core remains distinct from Site default and Daylight. Labels can
change without invalidating custom machine tokens; new palettes use the current
mapping. Invalid/deleted/unavailable tokens fall back to Site default and attempt
to remove only this key. No storage-wide clearing occurs.

A selector change applies normally, then attempts `setItem()`. Site default uses
`removeItem()` and follows the current administrator global theme. No preference
writes occur during ordinary refresh, behavior reattachment, stylesheet/frame
load or DOM discovery. Invalid initial/pageshow restoration is the explicit
cleanup exception. Already-open pages use their supplied catalog; no polling,
server endpoint or automatic remote-catalog refresh is introduced.

## Readiness, page restoration and disabled mode

A valid pending choice survives missing/mismatched parent styles. Global styling
remains active, the selector is disabled, and the existing polite status reports
that styles are not ready. Matching stylesheet readiness applies the pending
choice automatically. Existing validated iframe load handlers apply it when an
internal player is ready. No iframe validation condition was relaxed.

Ordinary Backdrop behavior reattachment does not reread storage. One removable
`pageshow` listener rereads/revalidates on `event.persisted === true`; normal
pageshow events do nothing. Restoration sets the existing selector value without
synthetic change events, focus calls, H5P initialization, frame navigation or
activity markup replacement. No polling or timers are added.

Disabled learner selection performs zero storage access and applies no personal
theme attributes. A prior preference can remain, ignored, until re-enabled and
validated. Existing PHP asset gating is unchanged; no disabled-page script is
loaded solely to remove a preference.

## Browser limitations

The preference follows same-origin Backdrop pages in the same browser tab and
survives reloads when sessionStorage is usable. An opener-created tab can inherit
a copy; copies subsequently diverge. Browser session restoration can retain a
preference. This is not a promise that every new tab is empty or that closing and
reopening always forgets the preference.

Access, read, write and removal exceptions are caught. Current-page switching
continues without persistence. If the browser rejects a write/removal, a previous
stored value may remain and be considered on a later page. No localStorage,
cookies, account/database preference, cross-device or durable cross-session
storage is implemented. This feature does not persist H5P answers, scores or
playback state across navigation.

No zero-flash guarantee is made: script and matching styles may arrive after
initial rendering. There is no activity hiding or pre-paint head script.

Browser semantics: [MDN sessionStorage](https://developer.mozilla.org/en-US/docs/Web/API/Window/sessionStorage)
and [HTML Web Storage](https://html.spec.whatwg.org/multipage/webstorage.html).

## Automated verification

- PHP 7.4.33: 1,850 passed, 0 failed.
- PHP 8.4.22: 1,850 passed, 0 failed.
- Node.js 22.23.1: 244 passed, 0 failed (174 prior checks plus 70 new session checks).
- `BACKDROP_SOURCE_ROOT` is explicit; learner workers use memory-only PHP
  collaborators and test-owned temporary storage, without live site bootstrap.
- No production catalog, CSS serializer, preset, schema or iframe security change.

The learner fixture now supplies an injectable sessionStorage double, operation
recording/failures and window pageshow events. Existing shared DOM/admin/preview
fixtures are unchanged. The narrow obsolete assertions updated are the prior
no-sessionStorage prohibition, old selector description, and pending-style
message/selection behavior. The old new-page-default test remains valid for
separate fixtures with separate stores. PHP assertion count is unchanged.

New tests exercise exact tokens, navigation/reload, Core/Daylight/Site default,
custom labels/deletion, current palette mappings, malformed/opaque values,
property/method exceptions, disabled/excluded contexts, multiple players, delayed
parent/frame styles, no ordinary-refresh I/O, reattachment, persisted pageshow,
focus/control/player identity, base-path separation and validation, opener copies,
and absence of persistent stores, network, timers or H5P lifecycle operations.

## Pending Firefox checkpoints

After separately authorized transfer/cache preparation, use ordinary playback
pages and the existing multi-activity View. Verify Mint/Dark/custom selection
survives same-tab navigation and reload, explicit Core remains distinct, and Site
default clears the preference. Confirm all players change without replacing their
instances or losing in-page answers. Check Back/Forward cache, focus/status,
private/blocked storage, delayed players and new/duplicated/restored tabs.

These are pending browser tests, not results inferred from DOM doubles. Reload
or navigation can naturally reset H5P answers; Themer adds no answer persistence.