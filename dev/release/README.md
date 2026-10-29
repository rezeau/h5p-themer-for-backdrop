# Release packaging guidance

## v0.4.3 preparation status

Phase 6B prepared module metadata as 0.4.3. Phase 6C1 updates the existing reviewer
and defines the source inventory; neither phase builds an archive. No 0.4.3
package or publication exists as a result of these phases. See the
[current user-facing packaging requirements](../../docs/installation.md#v043-release-packaging-preparation)
and [prepared release notes](../../docs/release-notes-v0.4.3.md).

The required archive shape is exactly one `h5p_themer/` root with approved regular
file entries, no second wrapper and no explicit directory entries. Runtime PHP,
JavaScript and CSS are shipped source; there is no compile/bundle/dependency-install
build step. Archive construction is a separate, explicitly authorized operation.

### Production and metadata inventory

The existing v0.4.2 list has 66 files (27 runtime, 34 documentation, five license).
Its runtime list still covers the implementation; no path below is newly created
by release preparation. The release version belongs in `h5p_themer.info`, which
must retain the module name/type, Backdrop 1.x compatibility, H5P-only dependency,
package and configure route. Schema 1003 and exact H5P library versions are separate
contracts, not release-version fields.

Required production files under the module root:

```text
config/h5p_themer.settings.json
css/h5p_themer.learner-picker.css
css/h5p_themer.learner.css
css/h5p_themer.preview.css
h5p_themer.admin.inc
h5p_themer.catalog.inc
h5p_themer.config.inc
h5p_themer.import.inc
h5p_themer.info
h5p_themer.install
h5p_themer.learner-icons.inc
h5p_themer.learner-runtime.inc
h5p_themer.learner.inc
h5p_themer.module
h5p_themer.native-density.inc
h5p_themer.presets.inc
h5p_themer.preview.inc
h5p_themer.runtime.inc
h5p_themer.visibility-activation.inc
h5p_themer.visibility-admin.inc
h5p_themer.visibility-publication.inc
h5p_themer.visibility.inc
js/h5p_themer.admin.js
js/h5p_themer.learner-picker.js
js/h5p_themer.learner.js
js/h5p_themer.native-density.js
js/h5p_themer.preview.js
```

Preserve `LICENSE`, `LICENSE-GPL-3.0.md`,
`LICENSE-MIT-H5P-THEME-PICKER.txt`, `LICENSE.txt` and `THIRD-PARTY-NOTICES.md`.
Ship README, CHANGELOG and the explicitly approved user-documentation inventory.
The six added 0.4.3 documents (release notes, development, readiness, Blanks,
selector and MarkTheWords) are absent from the old list. Old inventory plus those
six is the explicit `v043-package-files.json` allowlist: 72 files (27 runtime,
40 documentation, five license). Phase 6C1 validates that inventory. A source
review emits the byte sizes/hashes for the current working tree; that output must
be deliberately frozen for a separately authorized archive build.

### Exclusions and validation gates

Exclude all tests and fixtures, `dev/`, `artifacts/`, Git/editor/agent metadata,
local settings/credentials, dependency trees, SQL/dumps, logs, caches, generated
site CSS/JS, backups, temporary files and executables. No Backdrop or H5P library
source is redistributed. Ignored files can still leak into recursive filesystem
ZIPs; tracked developer/tests files enter an unfiltered Git archive. Neither
`.gitignore` nor a repository source download defines a production package.

Before packaging, approve a 0.4.3 allowlist and checker, then freeze source version,
commit/working-tree identity, path/category inventory, sizes and SHA-256 hashes.
After a separately authorized build, reject duplicate/case-colliding, traversal,
absolute, normalized/truncated, directory, encrypted, symlink/reparse or special
entries. Verify exact root/inventory, CRC, sizes, content hashes, embedded metadata
and retained licenses against the frozen manifest; independently inspect/extract
only to a validated disposable directory. Keep manifests, logs and backups outside
the archive. No 0.4.3 archive integrity or upgrade/rollback acceptance is claimed
by metadata preparation.

Reproducible source contents are feasible. Byte-identical ZIPs are not established:
pin ordering, timestamps, permissions/attributes, encoding/line endings, compression
settings and tool version before making that claim. A package-based upgrade and
rollback rehearsal remains recommended before publication.

### Current reviewer and historical profiles

The reviewer defaults to the fixed 0.4.3 policy and `v043-package-files.json`.
Explicit `--version 0.4.1` and `--version 0.4.2` profiles retain the historical
64/66-file lists and matching metadata/qualification checks. Those profiles need
matching source; they do not relabel the current tree as an earlier version.
The root, dependency, category/count, safe-path, source ownership, reparse,
manifest, archive inventory, CRC, size and hash checks remain enforced.

Source-only verification (creates no archive):

```text
python -B dev/release/review-package.py --self-test
python -B dev/release/test_review_package.py -v
python -B dev/release/review-package.py
```

The self-test preserves the original one valid/nine rejected synthetic member
cases. The focused test suite covers current/historical policies, exact metadata,
dependency/qualification rejection, inventories, unsafe paths, linked sources,
frozen manifests and read-only archive verification through mocks. No ZIP bytes,
archive file or extraction are created by these tests. Tests and this tooling
are excluded from the distribution.

Full source review prints JSON with version 0.4.3, the single module root, HEAD,
working-tree dirty status and all 72 path/category/byte-size/SHA-256 records.
It reads only allowlisted production/documentation/license files. The source
manifest identifies the actual uncommitted working tree; HEAD alone does not
identify those prepared bytes. Freeze the reviewed output/hash before packaging.

The existing archive reader remains available for a later authorized candidate:

```text
python -B dev/release/review-package.py --archive <candidate.zip> --manifest <frozen-source-manifest.json>
```

It opens an existing archive read-only and validates it against the selected
fixed policy and external frozen manifest; it never builds or extracts one.
Passing source review or mocked member tests is not evidence of actual archive
CRC/content integrity or package upgrade/rollback acceptance. Phase 6C2 archive
construction and validation require explicit authorization. No build occurs here.

The historical 0.4.1 instructions below remain a record of that earlier process;
select `--version 0.4.1` explicitly when reviewing matching historical source.

---

# Historical v0.4.1 package review — no archive builder

The explicit `v041-package-files.json` allowlist has 64 files: 27 runtime, 32
documentation and five license/notice files. It preserves the published v0.4.0
61-file inventory and adds only the v0.4.1 release notes, readiness and planned
upgrade-rehearsal documents. Native runtime assets are existing paths, not new
dependencies. Every ZIP entry must be a regular file beneath `h5p_themer/`; no
second wrapper directory or explicit directory entries are expected.

Everything outside the allowlist is excluded: tests/fixtures, this developer
tooling, native proof harnesses, artifacts/evidence, Git/editor/agent data, cloned
sites, settings/credentials, SQL, logs, generated CSS/JS, caches, sessions, backups,
temporary files, dependency trees and executable/DLL files. No Backdrop Core or H5P
library is redistributed. Preserve all five existing license/attribution files.

## Stage 1: source review only

Requires Python 3.9+ and Git. From the repository root:

```text
python dev/release/review-package.py
python dev/release/review-package.py --self-test
```

Default mode reads the allowlist/current source and emits a source manifest to
stdout, including sizes/SHA-256, HEAD and uncommitted status. It makes no archive
or extraction. Self-test constructs synthetic ZIP entry metadata only, creating no
ZIP file or bytes. It exercises accepted regular-entry metadata and rejection of
duplicates/count mismatch, traversal, backslash normalization, wrong-case root,
wrong size, symlinks, encryption and special files. This is checker validation,
not proof that a real archive passed CRC/content checks.

Source review enforces module identity/version 0.4.1, Backdrop 1.x compatibility,
unchanged H5P-only dependency and configure route, and retained qualification/gate
wording. The fixed file/category inventory, regular-source/reparse checks and
current document version references are checked. It does not bootstrap a site.

## Later authorized archive checks

After separately authorized archive construction, preserve the reviewed source
manifest outside the ZIP, record its own SHA-256 and freeze the exact source bytes.
Fail if any byte differs before construction; do not substitute HEAD for a dirty
working-tree snapshot. The builder must read only approved paths and write one
`h5p_themer/` root with 64 file entries. Record archive-tool version, file order and
timestamp/mode policy; do not promise identical ZIP hashes across different tools.

```text
python dev/release/review-package.py --archive <existing-candidate.zip> --manifest <frozen-source-manifest.json>
```

The tool opens an existing archive read-only. It verifies the frozen manifest's
allowlist/version/root, archive inventory/count/case uniqueness, raw versus
normalized filenames, traversal/root rules, file type/encryption, uncompressed
sizes, CRCs, every content SHA-256 and embedded module metadata. It reports archive
SHA-256. There is intentionally no archive-construction/extraction option.

Follow with extraction into a new directory whose absolute destination paths are
checked under that directory, then independently compare every extracted file and
inventory to the same frozen manifest. Keep the archive hash, source manifest/hash,
extraction results and test logs outside the module ZIP; no manifest self-hash is
claimed. Compare metadata and current notes consistently with 0.4.1 while allowing
explicit historical references to 0.4.0. Do not perform blind version replacements.

No archive build, extraction or upgrade rehearsal is part of Stage 1. See the
[planned upgrade procedure](../../docs/v0.4.1-upgrade-rehearsal.md) and
[prepared release notes](../../docs/release-notes-v0.4.1.md). Actual archive integrity,
package delivery and rollback acceptance remain pending.
