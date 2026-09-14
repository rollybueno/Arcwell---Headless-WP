#!/usr/bin/env python3
"""Build a reproducible uploadable plugin ZIP using an explicit runtime allowlist."""
from pathlib import Path
from zipfile import ZipFile, ZipInfo, ZIP_DEFLATED
import hashlib
import re

root = Path(__file__).resolve().parents[1]
plugin = root / 'wordpress/wp-content/plugins/arcwell-core'
version = re.search(r'\* Version: ([\d.]+)', (plugin / 'arcwell-core.php').read_text()).group(1)
output = root / 'dist' / f'arcwell-core-{version}.zip'
output.parent.mkdir(exist_ok=True)
files = [plugin / name for name in ('arcwell-core.php', 'readme.txt', 'README.md', 'LICENSE')]
files += [p for folder in ('src', 'assets', 'docs') for p in (plugin / folder).rglob('*') if p.is_file()]
with ZipFile(output, 'w', ZIP_DEFLATED) as archive:
    for path in sorted(files):
        info = ZipInfo('arcwell-core/' + path.relative_to(plugin).as_posix(), date_time=(2026, 1, 1, 0, 0, 0))
        info.compress_type = ZIP_DEFLATED
        info.external_attr = 0o100644 << 16
        archive.writestr(info, path.read_bytes())
digest = hashlib.sha256(output.read_bytes()).hexdigest()
output.with_suffix('.zip.sha256').write_text(f'{digest}  {output.name}\n')
print(f'{output}\nSHA256 {digest}\n{len(files)} runtime files; {output.stat().st_size:,} bytes')
