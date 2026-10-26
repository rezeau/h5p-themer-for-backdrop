"""Review source/package inputs without building or modifying an archive."""
from pathlib import Path, PurePosixPath
from collections import Counter
import argparse, hashlib, json, re, stat, subprocess, zipfile

ROOT = Path(__file__).resolve().parents[2]
ALLOWLIST = Path(__file__).with_name('v041-package-files.json')
EXCLUDED = {'tests', 'dev', 'artifacts', '.git', '.agents', '.codex', '.verification',
            'files', 'tmp', 'temp', 'backups', 'vendor', 'node_modules'}

def require(condition, message):
    if not condition:
        raise ValueError(message)

def safe_path(path):
    p = PurePosixPath(path)
    return (path == p.as_posix() and not p.is_absolute() and bool(p.parts)
            and '..' not in p.parts and '\\' not in path and ':' not in path
            and p.parts[0] not in EXCLUDED
            and p.suffix.lower() not in {'.sql', '.zip', '.log', '.bak', '.tmp', '.exe', '.dll'}
            and p.name not in {'settings.php', 'settings.local.php'})

def sha(data):
    return hashlib.sha256(data).hexdigest()

def metadata(data):
    text = data.decode('utf8')
    for pattern in (r'^type = module$', r'^name = H5P Themer$', r'^version = 0\.4\.1$',
                    r'^backdrop = 1\.x$', r'^package = H5P$',
                    r'^configure = admin/config/content/h5p-themer$'):
        require(re.search(pattern, text, re.M), 'Module metadata mismatch: ' + pattern)
    require(re.findall(r'^dependencies\[\] = (.+)$', text, re.M) == ['h5p'], 'Dependency expansion')
    require(all(term in text for term in ('Core API 1.28', 'TrueFalse 1.8.24 iframe players', 'disabled by default')), 'Qualification metadata missing')

def review_sources():
    plan = json.loads(ALLOWLIST.read_text())
    require(plan['version'] == '0.4.1' and plan['archive_root'] == 'h5p_themer/', 'Package identity')
    rows = plan['files']; names = [entry['path'] for entry in rows]
    require(len(names) == len(set(names)) == len({p.casefold() for p in names}) == 64, 'Allowlist count/collision')
    require(Counter(e['category'] for e in rows) == {'runtime': 27, 'documentation': 32, 'license': 5}, 'Category inventory')
    manifest = []
    for entry in rows:
        path = entry['path']; require(safe_path(path), 'Forbidden source path: ' + path)
        source = ROOT / path
        require(source.is_file() and source.resolve().is_relative_to(ROOT), 'Missing/outside source: ' + path)
        require(all(not node.is_symlink() and not (getattr(node.stat(), 'st_file_attributes', 0) & 0x400)
                    for node in (source, *source.parents) if node == ROOT or ROOT in node.parents), 'Linked/reparse source: ' + path)
        data = source.read_bytes()
        manifest.append({**entry, 'bytes': len(data), 'sha256': sha(data)})
    metadata((ROOT / 'h5p_themer.info').read_bytes())
    for path in ('README.md', 'CHANGELOG.md', 'docs/release-notes-v0.4.1.md', 'docs/v0.4.1-readiness.md'):
        require('v0.4.1' in (ROOT / path).read_text(encoding='utf-8-sig'), 'Current version missing: ' + path)
    head = subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=ROOT).decode().strip()
    dirty = bool(subprocess.check_output(['git', 'status', '--porcelain=v1', '--untracked-files=all'], cwd=ROOT).strip())
    return {'version': '0.4.1', 'archive_root': 'h5p_themer/', 'source': 'working tree (not a release commit)',
            'head': head, 'uncommitted': dirty, 'files': manifest}

def validate_members(infos, manifest):
    expected = {manifest['archive_root'] + e['path']: e for e in manifest['files']}
    names = [entry.filename for entry in infos]
    require(len(names) == len(set(names)) == len({n.casefold() for n in names}) == len(expected), 'Archive duplicate/count/case collision')
    require(set(names) == set(expected), 'Archive inventory differs from frozen manifest')
    for entry in infos:
        require(entry.orig_filename == entry.filename, 'Archive filename was normalized/truncated')
        require(not entry.is_dir() and entry.filename.startswith('h5p_themer/'), 'Wrong root/directory entry')
        require(safe_path(entry.filename[len('h5p_themer/'):]), 'Unsafe archive path')
        mode = stat.S_IFMT(entry.external_attr >> 16)
        require(mode in (0, stat.S_IFREG) and not (entry.external_attr & 0x400), 'Linked/special/reparse entry')
        require(not (entry.flag_bits & 1), 'Encrypted archive entry')
        require(entry.file_size == expected[entry.filename]['bytes'], 'Uncompressed size mismatch')
    return expected

def verify_archive(path, manifest):
    # Read-only operation: this tool never constructs/extracts an archive.
    require(path.is_file(), 'Archive does not exist')
    require(manifest['version'] == '0.4.1' and manifest['archive_root'] == 'h5p_themer/', 'Frozen manifest version/root')
    plan = json.loads(ALLOWLIST.read_text())
    rows = manifest['files']; names = [e['path'] for e in rows]
    require(len(names) == len(set(names)) == len({p.casefold() for p in names}) == 64, 'Frozen manifest count/collision')
    require({e['path']: e['category'] for e in rows} == {e['path']: e['category'] for e in plan['files']}, 'Frozen manifest allowlist mismatch')
    require(all(safe_path(e['path']) and isinstance(e['bytes'], int) and e['bytes'] >= 0
                and re.fullmatch(r'[a-f0-9]{64}', e['sha256']) for e in rows), 'Invalid frozen hash/size/path')
    with zipfile.ZipFile(path, 'r') as archive:
        expected = validate_members(archive.infolist(), manifest)
        require(archive.testzip() is None, 'CRC integrity failure')
        for name, entry in expected.items():
            require(sha(archive.read(name)) == entry['sha256'], 'Archive content hash mismatch: ' + name)
        metadata(archive.read('h5p_themer/h5p_themer.info'))
    return {'archive': str(path.resolve()), 'sha256': sha(path.read_bytes()), 'entries': len(expected),
            'inventory_crc_sizes_hashes_metadata': 'PASS'}

def self_test():
    # Synthetic entry metadata only: no ZIP, file or extraction is created.
    manifest = {'archive_root': 'h5p_themer/', 'files': [{'path': 'h5p_themer.info', 'bytes': 3}]}
    def entry(name='h5p_themer/h5p_themer.info', size=3, mode=stat.S_IFREG, flags=0):
        item = zipfile.ZipInfo(name); item.file_size = size; item.external_attr = (mode | 0o644) << 16; item.flag_bits = flags
        return item
    validate_members([entry()], manifest)
    bad = [[], [entry(), entry()], [entry('h5p_themer/../settings.php')],
           [entry('h5p_themer\\h5p_themer.info')], [entry('H5P_THEMER/h5p_themer.info')],
           [entry(size=4)], [entry(mode=stat.S_IFLNK)], [entry(flags=1)], [entry(mode=stat.S_IFIFO)]]
    for index, members in enumerate(bad):
        try:
            validate_members(members, manifest)
        except ValueError:
            continue
        raise AssertionError('Invalid archive metadata accepted: case ' + str(index))
    return {'valid_metadata_cases': 1, 'rejected_metadata_cases': len(bad), 'zip_created': False}

if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--self-test', action='store_true')
    parser.add_argument('--archive', type=Path, help='Verify an existing archive read-only; never builds one')
    parser.add_argument('--manifest', type=Path, help='Frozen external source manifest for --archive')
    args = parser.parse_args()
    if args.self_test:
        require(not args.archive and not args.manifest, 'Self-test is separate from archive verification')
        result = self_test()
    elif args.archive:
        require(args.manifest is not None, 'Archive checks require an external frozen manifest')
        result = verify_archive(args.archive, json.loads(args.manifest.read_text(encoding='utf-8-sig')))
    else:
        require(args.manifest is None, '--manifest requires --archive')
        result = review_sources()
    print(json.dumps(result, indent=2))
