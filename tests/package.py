#!/usr/bin/env python3
"""Validate the current-only package, optionally build its distribution archive."""
from pathlib import Path
import re
import sys
import zipfile
root = Path(__file__).resolve().parent.parent
plugin = root / 'files/zc_plugins/BulkDeleteCustomers/v1.0.1'
assert plugin.is_dir()
assert "'pluginVersion' => 'v1.0.1'" in (plugin / 'manifest.php').read_text()
assert "$version = '1.0.1'" in (plugin / 'Installer/ScriptedInstaller.php').read_text()
for path in plugin.rglob('*'):
    if path.is_file():
        assert path.suffix == '.php', path
        assert path.read_bytes().startswith(b'<?php'), path
        assert b'\xef\xbb\xbf' not in path.read_bytes(), path
assert not (plugin / 'catalog').exists(), 'No storefront changes'
page = (plugin / 'admin/bulk_delete_customers.php').read_text()
lang = (plugin / 'admin/includes/languages/english/lang.bulk_delete_customers.php').read_text()
for key in set(re.findall(r'\bBDC_[A-Z_]+\b', page)):
    assert "'" + key + "'" in lang, key
for path in [root / 'README.md', root / 'CHANGELOG.md', root / 'SECURITY.md']:
    assert path.read_text().isascii(), path
print('Package checks passed.')
if len(sys.argv) > 1:
    out = Path(sys.argv[1]).resolve()
    with zipfile.ZipFile(out, 'w', zipfile.ZIP_DEFLATED) as z:
        for path in sorted(plugin.rglob('*')):
            if path.is_file(): z.write(path, path.relative_to(root))
        for name in ['README.md', 'CHANGELOG.md', 'LICENSE', 'SECURITY.md']:
            z.write(root / name, name)
    with zipfile.ZipFile(out) as z:
        assert z.testzip() is None
        assert len(z.namelist()) == len(set(z.namelist()))
    print(out)
