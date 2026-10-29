"""Review source/package inputs without building or modifying an archive."""
from pathlib import Path, PurePosixPath
from collections import Counter
import argparse, hashlib, json, re, stat, subprocess, zipfile

ROOT = Path(__file__).resolve().parents[2]
CURRENT_VERSION = '0.4.3'
ARCHIVE_ROOT = 'h5p_themer/'
# Explicit release policies: historical qualification and inventories stay pinned.
PROFILES = {
    '0.4.1': ('v041-package-files.json', 64, 32),
    '0.4.2': ('v042-package-files.json', 66, 34),
    '0.4.3': ('v043-package-files.json', 72, 40),
}
EXCLUDED = {'tests', 'dev', 'artifacts', '.git', '.agents', '.codex', '.verification',
            'files', 'tmp', 'temp', 'backups', 'vendor', 'node_modules'}

def require(condition, message):
    if not condition:
        raise ValueError(message)

def safe_path(path):
    p = PurePosixPath(path)
    return (path == p.as_posix() and not p.is_absolute() and bool(p.parts)
            and '..' not in p.parts and '\\' not in path and ':' not in path
            and not any(ord(char) < 32 or ord(char) == 127 for char in path)
            and p.parts[0].casefold() not in EXCLUDED
            and p.suffix.lower() not in {'.sql', '.zip', '.log', '.bak', '.tmp', '.exe', '.dll'}
            and p.name not in {'settings.php', 'settings.local.php'})

def sha(data):
    return hashlib.sha256(data).hexdigest()

def load_plan(version=CURRENT_VERSION):
    require(version in PROFILES, 'Unsupported release version')
    filename, count, documents = PROFILES[version]
    plan = json.loads(Path(__file__).with_name(filename).read_text(encoding='utf-8-sig'))
    require(plan['version'] == version and plan['archive_root'] == ARCHIVE_ROOT, 'Package identity')
    rows = plan['files']; names = [entry['path'] for entry in rows]
    require(len(names) == len(set(names)) == len({p.casefold() for p in names}) == count, 'Allowlist count/collision')
    require(Counter(e['category'] for e in rows) == {'runtime': 27, 'documentation': documents, 'license': 5}, 'Category inventory')
    require(all(safe_path(name) for name in names), 'Forbidden allowlist path')
    return plan

def metadata(data, version=CURRENT_VERSION):
    require(version in PROFILES, 'Unsupported release version')
    text = data.decode('utf8').replace('\r\n', '\n')
    # Reject duplicate declarations rather than accepting any matching version line.
    require(re.findall(r'^version\s*=\s*(.+)$', text, re.M) == [version], 'Module release version mismatch')
    for pattern in (r'^type = module$', r'^name = H5P Themer$',
                    r'^backdrop = 1\.x$', r'^package = H5P$',
                    r'^configure = admin/config/content/h5p-themer$'):
        require(re.search(pattern, text, re.M), 'Module metadata mismatch: ' + pattern)
    require(re.findall(r'^dependencies\[\] = (.+)$', text, re.M) == ['h5p'], 'Dependency expansion')
    terms = ('Core API 1.28', 'TrueFalse 1.8.24 iframe players', 'disabled by default')
    if version == '0.4.3':
        terms = ('Core API 1.28', 'standalone official', 'H5P.TrueFalse 1.8.24',
                 'H5P.MultiChoice 1.16.27', 'H5P.Blanks 1.14.37',
                 'H5P.MarkTheWords 1.11.24', 'iframe players', 'disabled by default')
    require(all(term in text for term in terms), 'Qualification metadata missing')

def review_sources(version=CURRENT_VERSION):
    plan = load_plan(version)
    rows = plan['files']
    manifest = []
    for entry in rows:
        path = entry['path']; require(safe_path(path), 'Forbidden source path: ' + path)
        source = ROOT / path
        require(source.is_file() and source.resolve().is_relative_to(ROOT), 'Missing/outside source: ' + path)
        require(all(not node.is_symlink() and not (getattr(node.stat(), 'st_file_attributes', 0) & 0x400)
                    for node in (source, *source.parents) if node == ROOT or ROOT in node.parents), 'Linked/reparse source: ' + path)
        data = source.read_bytes()
        manifest.append({**entry, 'bytes': len(data), 'sha256': sha(data)})
    metadata((ROOT / 'h5p_themer.info').read_bytes(), version)
    evidence = 'docs/v0.4.3-markthewords.md' if version == '0.4.3' else 'docs/v' + version + '-readiness.md'
    if version == '0.4.2':
        evidence = 'docs/v0.4.2-development.md'
    for path in ('README.md', 'CHANGELOG.md', 'docs/release-notes-v' + version + '.md', evidence):
        text = (ROOT / path).read_text(encoding='utf-8-sig')
        if path.startswith('docs/release-notes-'):
            require(re.search(r'^# H5P Themer(?: for Backdrop)? v?' + re.escape(version) + r'(?:\s|$)', text, re.M),
                    'Release notes version mismatch: ' + path)
        else:
            require('v' + version in text, 'Current version missing: ' + path)
    head = subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=ROOT).decode().strip()
    dirty = bool(subprocess.check_output(['git', 'status', '--porcelain=v1', '--untracked-files=all'], cwd=ROOT).strip())
    return {'version': version, 'archive_root': ARCHIVE_ROOT, 'source': 'working tree (not a release commit)',
            'head': head, 'uncommitted': dirty, 'files': manifest}

def validate_members(infos, manifest):
    require(manifest['archive_root'] == ARCHIVE_ROOT, 'Wrong archive root')
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

def validate_manifest(manifest, version=CURRENT_VERSION):
    plan = load_plan(version)
    require(manifest['version'] == version and manifest['archive_root'] == ARCHIVE_ROOT, 'Frozen manifest version/root')
    rows = manifest['files']; names = [e['path'] for e in rows]
    require(len(names) == len(set(names)) == len({p.casefold() for p in names}) == len(plan['files']), 'Frozen manifest count/collision')
    require({e['path']: e['category'] for e in rows} == {e['path']: e['category'] for e in plan['files']}, 'Frozen manifest allowlist mismatch')
    require(all(safe_path(e['path']) and type(e['bytes']) is int and e['bytes'] >= 0
                and re.fullmatch(r'[a-f0-9]{64}', e['sha256']) for e in rows), 'Invalid frozen hash/size/path')
    return plan

def verify_archive(path, manifest, version=CURRENT_VERSION):
    # Read-only operation: this tool never constructs/extracts an archive.
    require(path.is_file(), 'Archive does not exist')
    validate_manifest(manifest, version)
    with zipfile.ZipFile(path, 'r') as archive:
        expected = validate_members(archive.infolist(), manifest)
        require(archive.testzip() is None, 'CRC integrity failure')
        for name, entry in expected.items():
            require(sha(archive.read(name)) == entry['sha256'], 'Archive content hash mismatch: ' + name)
        metadata(archive.read('h5p_themer/h5p_themer.info'), version)
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
    parser.add_argument('--version', choices=tuple(PROFILES), default=CURRENT_VERSION,
                        help='Fixed release policy; defaults to 0.4.3. Historical profiles require matching source.')
    parser.add_argument('--self-test', action='store_true')
    parser.add_argument('--archive', type=Path, help='Verify an existing archive read-only; never builds one')
    parser.add_argument('--manifest', type=Path, help='Frozen external source manifest for --archive')
    args = parser.parse_args()
    if args.self_test:
        require(not args.archive and not args.manifest, 'Self-test is separate from archive verification')
        result = self_test()
    elif args.archive:
        require(args.manifest is not None, 'Archive checks require an external frozen manifest')
        result = verify_archive(args.archive, json.loads(args.manifest.read_text(encoding='utf-8-sig')), args.version)
    else:
        require(args.manifest is None, '--manifest requires --archive')
        result = review_sources(args.version)
    print(json.dumps(result, indent=2))
