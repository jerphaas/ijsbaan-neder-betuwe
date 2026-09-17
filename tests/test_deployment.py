"""Protect the production credential boundary and backup-before-overwrite guarantee."""
import copy
import json
import os
from pathlib import Path
import sys
import tempfile
import unittest
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / 'scripts'))
import hosting
import sponsor_admin
import deploy_sponsor

SAMPLE = dict(db={'name': 'example', 'password': 'db-test'}, smtp={'password': 'smtp-test'},
              maintenance_key='maintenance-test', form_key='form-test',
              site_url='https://example.test', from_email='from@example.test',
              organizer_email='organizer@example.test', enabled=True)


class FakeFTP:
    def __init__(self):
        self.directory = ''

    def cwd(self, path):
        self.directory = path

    def pwd(self):
        return self.directory

    def mlsd(self, folder=None):
        if self.directory == str(Path(deploy_sponsor.PRIVATE_REMOTE).parent).replace('\\', '/'):
            return [('sponsor-private', {'type': 'dir'})]
        if self.directory == deploy_sponsor.PRIVATE_REMOTE:
            return [('config.json', {'type': 'file'}), ('app', {'type': 'dir'})]
        return [('bootstrap.php', {'type': 'file'})]

    def sendcmd(self, command):
        pass


class DeploymentTests(unittest.TestCase):
    def test_ci_uses_environment_without_windows_dpapi(self):
        with patch.dict(os.environ, {'GITHUB_ACTIONS': 'true', 'IJSBAAN_FTP_PASSWORD': 'ftp-test',
                                    'IJSBAAN_MAINTENANCE_KEY': 'maintenance-test'}, clear=True), \
                patch.object(hosting, 'crypt', side_effect=AssertionError('DPAPI must not run')):
            self.assertEqual(hosting.ftp_password(), 'ftp-test')
            self.assertEqual(sponsor_admin.maintenance_key(), 'maintenance-test')

    def test_missing_ci_credentials_fail_without_local_fallback(self):
        with patch.dict(os.environ, {'GITHUB_ACTIONS': 'true'}, clear=True):
            for operation in (hosting.ftp_password, sponsor_admin.maintenance_key,
                              lambda: sponsor_admin.validate_remote_config(SAMPLE)):
                with self.assertRaises(RuntimeError):
                    operation()

    def test_config_fingerprint_detects_wrong_database_and_credentials(self):
        env = {'GITHUB_ACTIONS': 'true', 'IJSBAAN_CONFIG_SHA256': sponsor_admin.config_fingerprint(SAMPLE)}
        with patch.dict(os.environ, env, clear=True):
            sponsor_admin.validate_remote_config(SAMPLE)
            for key in sponsor_admin.CONFIG_KEYS:
                changed = copy.deepcopy(SAMPLE)
                changed[key] = 'other'
                with self.subTest(key=key), self.assertRaises(RuntimeError):
                    sponsor_admin.validate_remote_config(changed)
            with self.assertRaises(RuntimeError):
                sponsor_admin.validate_remote_config({**SAMPLE, 'enabled': False})

    def test_local_config_validation_remains_supported(self):
        with patch.dict(os.environ, {}, clear=True), patch.object(sponsor_admin, 'credentials', return_value=SAMPLE):
            sponsor_admin.validate_remote_config(SAMPLE)

    def test_failed_backup_prevents_backend_overwrite(self):
        def read(ftp, name):
            return json.dumps(SAMPLE).encode() if name == 'config.json' else b'previous PHP'
        env = {'IJSBAAN_CONFIG_SHA256': sponsor_admin.config_fingerprint(SAMPLE)}
        hosting.PRIVATE.mkdir(exist_ok=True)
        with tempfile.TemporaryDirectory(dir=hosting.PRIVATE) as directory, patch.dict(os.environ, env, clear=True), \
                patch('publish.read_remote', side_effect=read), patch('publish.upload') as upload, \
                patch.object(deploy_sponsor, 'preserve_backup', side_effect=RuntimeError('backup unavailable')):
            with self.assertRaisesRegex(RuntimeError, 'backup unavailable'):
                deploy_sponsor.deploy(FakeFTP(), 'abc123', {'bootstrap.php': b'new PHP'}, Path(directory), 'token')
            upload.assert_not_called()
            self.assertEqual((Path(directory) / 'sponsor-server/bootstrap.php').read_bytes(), b'previous PHP')


if __name__ == '__main__':
    unittest.main()
