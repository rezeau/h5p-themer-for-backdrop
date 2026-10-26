# v0.4.1 package review — no archive builder

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
