#!/usr/bin/env python3
"""Check canonical development documents and their local links, without network I/O."""
from pathlib import Path
import re
from urllib.parse import unquote, urlsplit


REQUIRED = (
    "AGENTS.md", "README.md", "CHANGELOG.md", "docs/development-governance.md",
    "docs/project-state.md", ".specify/memory/constitution.md",
)


def check(root: Path) -> list[str]:
    root = root.resolve()
    errors = [f"Missing canonical document: {name}" for name in REQUIRED
              if not (root / name).is_file()]
    documents = {root / name for name in REQUIRED}
    documents.update((root / "docs").rglob("*.md"))
    for document in sorted(documents):
        if not document.is_file():
            continue
        for target in re.findall(r"\[[^\]]+\]\(([^\s)]+)\)", document.read_text()):
            link = urlsplit(target)
            if link.scheme or link.netloc or not link.path:
                continue
            destination = (document.parent / unquote(link.path)).resolve()
            if not destination.is_relative_to(root) or not destination.exists():
                errors.append(f"{document.relative_to(root)}: invalid local link: {target}")
    return errors


if __name__ == "__main__":
    problems = check(Path(__file__).resolve().parents[1])
    for problem in problems:
        print("FAIL " + problem)
    if problems:
        raise SystemExit(1)
    print("PASS canonical documents and local links (structural only; external links not fetched)")
