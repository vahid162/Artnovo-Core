#!/usr/bin/env python3
"""Build the runtime-only WordPress ZIP using the Python standard library."""
import hashlib
import json
from pathlib import Path
import re
import stat
import zipfile


def build(root: Path) -> dict:
    source = (root / "artnovo-core.php").read_text()
    version = re.search(r"^ \* Version: (\d+\.\d+\.\d+)$", source, re.M)
    constant = re.search(r"^const VERSION = '([^']+)';$", source, re.M)
    if not version or not constant or version[1] != constant[1]:
        raise ValueError("Plugin header and runtime version must agree")
    version = version[1]
    files = [root / name for name in (
        "artnovo-core.php", "README.md", "CHANGELOG.md", "THIRD-PARTY-NOTICES.md"
    )]
    files += sorted(path for path in (root / "modules").rglob("*") if path.is_file())
    required = root / "modules/account/jquery-qrcode-LICENSE.txt"
    if required not in files:
        raise ValueError("QR vendor license is required")
    for path in files:
        if path.is_symlink() or not path.is_file():
            raise ValueError(f"Missing or unsafe runtime file: {path.relative_to(root)}")
        if path.suffix not in {".php", ".js", ".css", ".txt", ".md"}:
            raise ValueError(f"Unexpected runtime file: {path.relative_to(root)}")
    output = root / "dist"
    output.mkdir(exist_ok=True)
    archive = output / f"artnovo-core-{version}.zip"
    with zipfile.ZipFile(archive, "w", compression=zipfile.ZIP_STORED) as bundle:
        for path in sorted(files):
            name = "artnovo-core/" + path.relative_to(root).as_posix()
            entry = zipfile.ZipInfo(name, date_time=(1980, 1, 1, 0, 0, 0))
            entry.create_system = 3
            entry.external_attr = (stat.S_IFREG | 0o644) << 16
            bundle.writestr(entry, path.read_bytes())
    digest = hashlib.sha256(archive.read_bytes()).hexdigest()
    (output / "SHA256SUMS").write_text(f"{digest}  {archive.name}\n")
    return {"version": version, "tag": "v" + version,
            "archive": str(archive.relative_to(root)), "files": len(files), "sha256": digest}


if __name__ == "__main__":
    print(json.dumps(build(Path(__file__).resolve().parents[1])))
