# Phase 1B live verification — historical record

SPDX-License-Identifier: GPL-2.0-or-later.

This is historical development evidence from 15 October 2026. It describes the
Phase 1B administration-only checkpoint, not the current feature set or site
configuration. Use [installation](installation.md), [administration](administration.md)
and the [README](../README.md) for current guidance. Personal paths, private backup
locations, local activity identifiers/URLs and unnecessary log identifiers have
been generalized for publication.

## Scope and manual evidence

H5P Themer was enabled manually after an authorized module junction was prepared.
H5P Core API 1.28 was confirmed, and a separately stored pre-installation backup
was available. No authentication bypass, permission changes, manufactured
sessions or direct configuration/database substitutions were used.

The user performed authenticated administration tests manually in Firefox.
The automated browser session did not have administrator authentication; its
access restriction was not a functionality failure.

| Checkpoint | Result | Evidence |
| --- | --- | --- |
| 3A: create | PASS | User created a disposable schema-1 custom theme with medium density, main CTA #336699 and background #F5F5F5. |
| 3B: edit | PASS | User changed its label, density to small and main CTA to #224466. Background and machine identity stayed unchanged; a read-only check independently matched the edited data. |
| 3C: duplicate | PASS | User confirmed duplication and independence under a separate disposable ID. |
| 3D: global default | PASS | Selection persisted after reload and could be cleared; null was independently verified after cleanup. |
| 3E: validation | PASS | Invalid colors, invalid machine IDs and duplicate IDs were rejected without overwriting valid definitions. |
| 3F: deletion protection | PASS | The selected default could not be deleted. The user then cleared it. |
| 3G: cleanup | PASS | User deleted both disposable themes; read-only inspection confirmed no custom definitions and a null default at this checkpoint. |

The earlier pre-cleanup inspection found schema-1 settings with a null default
and one edited schema-1 custom definition. Its two sparse overrides matched the
manual test and had no extra entries. Those creation/editing values are historical;
they are not installation defaults or assertions about later site state.

## Final Phase 1B configuration and source checks

Read-only inspection found H5P Themer, H5P and H5P Editor enabled. Core declared
exactly API 1.28. All 731 checked H5P integration files, including Editor files and
repository metadata, matched the pre-installation backup. Settings remained
schema 1 with a null default; no disposable custom definitions or orphaned
default references remained.

## Automated tests at that checkpoint

The supported complete runner was `tests/run.php`, including its administration
tests, with these historical totals:

| Runtime | Passed | Failed | Exit code |
| --- | ---: | ---: | ---: |
| PHP 7.4.33 | 368 | 0 | 0 |
| PHP 8.4.22 | 368 | 0 | 0 |

The runner loaded existing Backdrop/H5P definitions with memory-only collaborators,
without site bootstrap or live configuration/database writes. A mistaken standalone
invocation of an internal test include failed for lack of the runner bootstrap;
the complete runner subsequently passed. This was a CLI invocation error, not a
Backdrop runtime failure. See the README for current commands and totals.

## Representative HTTP, rendering and Editor observations

These were historical requests, not operations performed during publication work.

| Representative target | Observation |
| --- | --- |
| Site home | HTTP 200 |
| Existing True/False, Dialogcards and Image Choice Rounds pages | HTTP 200; rendered activities |
| Existing content Edit page, unauthenticated | HTTP 403; expected access restriction |
| H5P Editor script asset | HTTP 200 |

The user separately confirmed that the existing content Edit page loaded the H5P
Editor without warnings in authenticated Firefox, without saving.

The three activities were observed without answering questions or activating play
modes. Their rendered frames contained no Themer assets/markers or injected theme
variable blocks at this administration-only phase. Core's existing theme CSS was
normal Core output. Sampled pages contained no PHP diagnostic markers. These
observations do not qualify every library or the later runtime implementation.

## Logs and backup comparison

The reviewed recent log window contained expected installation/enablement and
access-denied events. No PHP-type entry or unexpected Themer warning, error or
exception was found. Credentials and content parameters were not printed.

The database dump and baseline metadata passed their SHA-256 checks. Table row
counts and order-independent digests were compared read-only; exceptions were
examined against decoded dump fields. No backup was restored.

Before HTTP/browser checks, all 11 H5P tables matched the backup, including all
20 authored-content rows. Node, revision, managed-file and file-usage data also
matched.

After rendering, authored data still matched, but complete row/file identity did
not: two activities acquired regenerated `filtered` caches; dependency usage
was regenerated and its total grew from 216 to 218, then 220 rows. The detailed
first comparison found nine changed dependency weights and two added preloaded
dependencies. All 30 node and 30 revision rows and the other nine H5P tables
still matched, as did managed-file and file-usage data.

Two generated H5P export archives differed within a 696-file content-file
inventory. Their authored `content/content.json` payloads were unchanged;
packaged metadata/library payloads differed. No content files were missing or
newly added. Concurrent manual site checks prevent attributing every refresh to
the sampled automated requests alone.

The inspected render path calls `h5p_get_content_settings()`, then
`H5PCore::filterParameters()`. An empty filtered cache can regenerate
dependencies, an export archive and the filtered cache through the existing
adapter. These are normal H5P derived-data operations, not authored-content edits
or Themer theme application. Thus GET-only rendering was not physically write-free.
The cache state should have been inspected before choosing a representative page.

No repair, forced cleanup, cache rebuild or restoration was attempted. The user
accepted these documented normal derived-data exceptions before proceeding.
Claiming every H5P record/file remained byte-for-byte identical would be false.

## Historical conclusion

Phase 1B administration tests 3A-3G and the authenticated Editor opening were
manually confirmed PASS. Automated checks passed with the documented normal
generated-data exception. Phase 1B was validated and closed. Later schemas,
global runtime and preview work are described by the current guides and
[roadmap](roadmap.md), not by the final state of this earlier checkpoint.
