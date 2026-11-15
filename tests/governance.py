#!/usr/bin/env python3
"""Regressions for the document checker; not an agent-behavior evaluation."""
import importlib.util
from pathlib import Path
import tempfile
import unittest


root = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location("document_check", root / "tools/check.py")
checker = importlib.util.module_from_spec(spec)
spec.loader.exec_module(checker)


class DocumentChecks(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory(prefix="artnovo-doc-check-")
        self.addCleanup(self.temporary.cleanup)
        self.root = Path(self.temporary.name)
        for name in checker.REQUIRED:
            path = self.root / name
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_text("# Fixture\n")

    def test_current_versions_are_valid_document_content(self):
        (self.root / "README.md").write_text("Core 1.0.0; PHP 8.5.x; Bridge 0.2.5.\n")
        self.assertEqual([], checker.check(self.root))

    def test_missing_canonical_state_is_reported(self):
        (self.root / "docs/project-state.md").unlink()
        self.assertIn("Missing canonical document: docs/project-state.md", checker.check(self.root))

    def test_missing_relative_target_is_reported(self):
        (self.root / "README.md").write_text("[Missing](docs/missing.md)\n")
        self.assertTrue(any("invalid local link" in error for error in checker.check(self.root)))

    def test_relative_encoded_targets_and_external_links_are_allowed(self):
        (self.root / "docs/a note.md").write_text("# Note\n")
        (self.root / "docs/project-state.md").write_text(
            "[Entry](../AGENTS.md#development-checks) [Note](a%20note.md) "
            "[Web](https://example.invalid/docs) [Section](#local)\n")
        self.assertEqual([], checker.check(self.root))

    def test_links_cannot_escape_the_repository(self):
        (self.root / "README.md").write_text("[Outside](../)\n")
        self.assertTrue(any("invalid local link" in error for error in checker.check(self.root)))

    def test_symlink_escape_is_reported(self):
        (self.root / "outside").symlink_to(self.root.parent, target_is_directory=True)
        (self.root / "README.md").write_text("[Outside](outside/)\n")
        self.assertTrue(any("invalid local link" in error for error in checker.check(self.root)))


if __name__ == "__main__":
    unittest.main(verbosity=2)
