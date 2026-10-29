"""Release reviewer contracts. Uses synthetic ZIP metadata/mocks; creates no archive."""
import copy
import importlib.util
import json
from pathlib import Path
import stat
import subprocess
import unittest
from unittest.mock import patch
import zipfile

SPEC = importlib.util.spec_from_file_location('review_package', Path(__file__).with_name('review-package.py'))
review = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(review)


def member(path='h5p_themer/h5p_themer.info', size=3, mode=stat.S_IFREG, flags=0):
    item = zipfile.ZipInfo(path)
    item.file_size = size
    item.external_attr = (mode | 0o644) << 16
    item.flag_bits = flags
    return item


class ReleaseReviewerTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.source = review.review_sources()
        cls.info = (review.ROOT / 'h5p_themer.info').read_bytes()

    def test_existing_self_test_is_preserved(self):
        self.assertEqual(review.self_test(), {'valid_metadata_cases': 1,
                                             'rejected_metadata_cases': 9, 'zip_created': False})

    def test_current_profile_and_inventory(self):
        plan = review.load_plan()
        self.assertEqual(plan['version'], '0.4.3')
        self.assertEqual(plan['archive_root'], 'h5p_themer/')
        self.assertEqual(len(plan['files']), 72)
        old = review.load_plan('0.4.2')['files']
        added = {e['path'] for e in plan['files']} - {e['path'] for e in old}
        self.assertEqual(added, {'docs/release-notes-v0.4.3.md', 'docs/v0.4.3-blanks.md',
                                'docs/v0.4.3-development.md', 'docs/v0.4.3-markthewords.md',
                                'docs/v0.4.3-readiness.md', 'docs/v0.4.3-selector.md'})
        for category in ('runtime', 'license'):
            self.assertEqual({e['path'] for e in plan['files'] if e['category'] == category},
                             {e['path'] for e in old if e['category'] == category})

    def test_historical_profiles_and_metadata_stay_pinned(self):
        for version, count in [('0.4.1', 64), ('0.4.2', 66)]:
            with self.subTest(version=version):
                self.assertEqual(len(review.load_plan(version)['files']), count)
                data = subprocess.check_output(['git', 'show', 'v' + version + ':h5p_themer.info'], cwd=review.ROOT)
                review.metadata(data, version)
                with self.assertRaises(ValueError):
                    review.metadata(data)

    def test_current_metadata_and_crlf(self):
        review.metadata(self.info)
        review.metadata(self.info.replace(b'\r\n', b'\n').replace(b'\n', b'\r\n'))

    def test_metadata_rejects_wrong_or_duplicate_version(self):
        for data in [self.info.replace(b'version = 0.4.3', b'version = 0.4.3-dev'),
                     self.info + b'\nversion = 0.4.1\n', self.info + b'\nversion = 0.4.3\n']:
            with self.subTest(data=data):
                with self.assertRaises(ValueError):
                    review.metadata(data)

    def test_metadata_rejects_dependency_and_qualification_changes(self):
        for data in [self.info + b'\ndependencies[] = other\n',
                     self.info.replace(b'backdrop = 1.x', b'backdrop = 2.x'),
                     self.info.replace(b'H5P.MarkTheWords 1.11.24', b'H5P.MarkTheWordsPapiJo 1.11.24'),
                     self.info.replace(b'H5P.Blanks 1.14.37', b'H5P.Blanks 1.14.38')]:
            with self.subTest(data=data):
                with self.assertRaises(ValueError):
                    review.metadata(data)

    def test_unsupported_policy_is_rejected(self):
        with self.assertRaises(ValueError):
            review.load_plan('9.9.9')

    def test_allowlist_rejects_wrong_identity(self):
        for field, value in [('version', '0.4.1'), ('archive_root', 'other/')]:
            plan = copy.deepcopy(review.load_plan())
            plan[field] = value
            with self.subTest(field=field), patch.object(Path, 'read_text', return_value=json.dumps(plan)):
                with self.assertRaises(ValueError):
                    review.load_plan()

    def test_allowlist_rejects_missing_duplicate_case_collision_and_wrong_category(self):
        base = review.load_plan()
        cases = [copy.deepcopy(base) for _ in range(4)]
        cases[0]['files'].pop()
        cases[1]['files'][-1] = cases[1]['files'][0]
        cases[2]['files'][-1] = {**cases[2]['files'][0], 'path': cases[2]['files'][0]['path'].upper()}
        cases[3]['files'][0]['category'] = 'runtime'
        for plan in cases:
            with self.subTest(plan=plan), patch.object(Path, 'read_text', return_value=json.dumps(plan)):
                with self.assertRaises(ValueError):
                    review.load_plan()

    def test_allowlist_rejects_excluded_path_with_unchanged_count(self):
        plan = copy.deepcopy(review.load_plan())
        plan['files'][0]['path'] = 'artifacts/private.md'
        with patch.object(Path, 'read_text', return_value=json.dumps(plan)):
            with self.assertRaises(ValueError):
                review.load_plan()

    def test_path_security_and_exclusions(self):
        for path in ['tests/private.php', 'dev/release.py', 'artifacts/secrets.md', '.git/config',
                     '.GIT/config', 'settings.php', 'settings.local.php', '../secret', '/secret',
                     'C:/secret', 'docs/../secret', 'docs\\secret', 'docs//secret',
                     'docs/./secret', 'docs/a\x00.md', 'backups/x', 'node_modules/x', 'files/x',
                     'tmp/x', 'temp/x', 'vendor/x', 'dump.sql', 'out.zip', 'x.log', 'x.bak', 'x.exe']:
            with self.subTest(path=path):
                self.assertFalse(review.safe_path(path))

    def test_source_manifest_hashes_sizes_and_inventory(self):
        self.assertEqual(len(self.source['files']), 72)
        self.assertEqual(self.source['version'], '0.4.3')
        review.validate_manifest(self.source)
        for entry in self.source['files']:
            data = (review.ROOT / entry['path']).read_bytes()
            self.assertEqual(entry['bytes'], len(data))
            self.assertEqual(entry['sha256'], review.sha(data))

    def test_source_review_rejects_linked_sources(self):
        with patch.object(Path, 'is_symlink', return_value=True):
            with self.assertRaisesRegex(ValueError, 'Linked/reparse'):
                review.review_sources()

    def test_source_review_requires_exact_release_notes_heading(self):
        original = Path.read_text
        def changed(path, *args, **kwargs):
            text = original(path, *args, **kwargs)
            if path.name == 'release-notes-v0.4.3.md':
                text = text.replace('# H5P Themer for Backdrop 0.4.3',
                                    '# H5P Themer for Backdrop 0.4.30', 1)
            return text
        with patch.object(Path, 'read_text', autospec=True, side_effect=changed):
            with self.assertRaisesRegex(ValueError, 'Release notes version mismatch'):
                review.review_sources()

    def test_manifest_rejects_version_and_inventory_changes(self):
        cases = [copy.deepcopy(self.source) for _ in range(4)]
        cases[0]['version'] = '0.4.1'
        cases[1]['archive_root'] = 'other/'
        cases[2]['files'].pop()
        cases[3]['files'][0]['category'] = 'runtime'
        for data in cases:
            with self.subTest(data=data):
                with self.assertRaises(ValueError):
                    review.validate_manifest(data)

    def test_manifest_rejects_invalid_sizes_and_hashes(self):
        for field, value in [('bytes', True), ('bytes', -1), ('bytes', '1'),
                             ('sha256', 'bad'), ('sha256', 'A' * 64)]:
            data = copy.deepcopy(self.source)
            data['files'][0][field] = value
            with self.subTest(field=field, value=value):
                with self.assertRaises(ValueError):
                    review.validate_manifest(data)

    def test_archive_member_additional_security_cases_without_zip(self):
        manifest = {'archive_root': 'h5p_themer/', 'files': [{'path': 'h5p_themer.info', 'bytes': 3}]}
        bad = [member('h5p_themer/'), member('/h5p_themer/h5p_themer.info'),
               member('h5p_themer/h5p_themer.info\x00hidden'), member(mode=stat.S_IFDIR),
               member('h5p_themer/.git/config')]
        reparse = member()
        reparse.external_attr |= 0x400
        bad.append(reparse)
        for entry in bad:
            with self.subTest(entry=entry.filename):
                with self.assertRaises(ValueError):
                    review.validate_members([entry], manifest)

    def test_archive_member_validation_preserves_every_expected_entry(self):
        infos = [member('h5p_themer/' + e['path'], e['bytes']) for e in self.source['files']]
        self.assertEqual(len(review.validate_members(infos, self.source)), 72)
        with self.assertRaises(ValueError):
            review.validate_members(infos + [member('h5p_themer/tests/leak.php')], self.source)

    def test_read_only_archive_verification_with_mock_bytes(self):
        # Exercise the existing reader without constructing any ZIP, even in memory.
        class ArchivePath:
            def is_file(self): return True
            def read_bytes(self): return b'synthetic archive identity'
            def resolve(self): return Path('not-created.zip')
        infos = [member('h5p_themer/' + e['path'], e['bytes']) for e in self.source['files']]
        def read(name):
            return (review.ROOT / name.removeprefix('h5p_themer/')).read_bytes()
        with patch.object(review.zipfile, 'ZipFile') as opener:
            archive = opener.return_value.__enter__.return_value
            archive.infolist.return_value = infos
            archive.testzip.return_value = None
            archive.read.side_effect = read
            result = review.verify_archive(ArchivePath(), self.source)
            self.assertEqual(result['entries'], 72)
            archive.testzip.return_value = infos[0].filename
            with self.assertRaisesRegex(ValueError, 'CRC'):
                review.verify_archive(ArchivePath(), self.source)
            archive.testzip.return_value = None
            archive.read.side_effect = lambda name: b'tampered'
            with self.assertRaisesRegex(ValueError, 'content hash'):
                review.verify_archive(ArchivePath(), self.source)
        opener.assert_called_with(unittest.mock.ANY, 'r')


if __name__ == '__main__':
    unittest.main()
