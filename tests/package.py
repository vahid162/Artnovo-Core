#!/usr/bin/env python3
"""Exercise deterministic distribution and rejection boundaries in private fixtures."""
import importlib.util
from pathlib import Path
import posixpath
import re
import shutil
import tempfile
import unittest
from urllib.parse import unquote, urlsplit
import zipfile


root = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location("package_builder", root / "tools/package.py")
builder = importlib.util.module_from_spec(spec)
spec.loader.exec_module(builder)


class PackageChecks(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory(prefix="artnovo-package-check-")
        self.addCleanup(self.temporary.cleanup)
        self.root = Path(self.temporary.name)
        for name in ["artnovo-core.php", "README.md", "CHANGELOG.md", "THIRD-PARTY-NOTICES.md"]:
            shutil.copyfile(root / name, self.root / name)
        shutil.copytree(root / "modules", self.root / "modules")

    def test_private_and_development_files_are_excluded_and_output_is_repeatable(self):
        for name in [".env", "AGENTS.md", "docs/private.md", "tests/fixture.php",
                     "tools/migrate.php", ".github/workflows/validate.yml", "backup/customer.sql"]:
            path = self.root / name
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_text("synthetic-private-development-marker")
        first = builder.build(self.root)
        archive = self.root / first["archive"]
        before = archive.read_bytes()
        second = builder.build(self.root)
        self.assertEqual(before, archive.read_bytes())
        self.assertEqual(first["sha256"], second["sha256"])
        with zipfile.ZipFile(archive) as bundle:
            self.assertEqual(36, len(bundle.namelist()))
            self.assertTrue(all(name.startswith("artnovo-core/") for name in bundle.namelist()))
            self.assertIn("artnovo-core/modules/account/jquery-qrcode-LICENSE.txt", bundle.namelist())
            for name in ("notices.php", "notices.js", "notices.css"):
                self.assertIn("artnovo-core/modules/notices/" + name, bundle.namelist())
            self.assertFalse(any(b"synthetic-private-development-marker" in bundle.read(name)
                                 for name in bundle.namelist()))

    def test_mismatched_runtime_version_is_rejected(self):
        path = self.root / "artnovo-core.php"
        changed, count = re.subn(r"^const VERSION = '[^']+';$",
                                 "const VERSION = '9.9.9';", path.read_text(), flags=re.M)
        self.assertEqual(1, count, "Fixture must change the authoritative runtime version")
        path.write_text(changed)
        with self.assertRaisesRegex(ValueError, "version must agree"):
            builder.build(self.root)

    def test_packaged_readme_links_resolve_inside_the_archive(self):
        output = builder.build(self.root)
        with zipfile.ZipFile(self.root / output["archive"]) as bundle:
            readme = bundle.read("artnovo-core/README.md").decode()
            for target in re.findall(r"\[[^\]]+\]\(([^\s)]+)\)", readme):
                link = urlsplit(target)
                if link.scheme or link.netloc or not link.path:
                    continue
                name = posixpath.normpath("artnovo-core/" + unquote(link.path))
                self.assertIn(name, bundle.namelist(), f"Packaged README has a broken link: {target}")

    def test_linked_runtime_file_is_rejected(self):
        path = self.root / "modules/header/artnovo-header.js"
        path.unlink()
        path.symlink_to(root / "modules/header/artnovo-header.js")
        with self.assertRaisesRegex(ValueError, "unsafe runtime file"):
            builder.build(self.root)

    def test_missing_vendor_license_is_rejected(self):
        (self.root / "modules/account/jquery-qrcode-LICENSE.txt").unlink()
        with self.assertRaisesRegex(ValueError, "vendor license is required"):
            builder.build(self.root)


if __name__ == "__main__":
    unittest.main(verbosity=2)
