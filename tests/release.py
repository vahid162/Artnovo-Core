#!/usr/bin/env python3
"""Exercise the real release step with a local gh stub; no network or token is used."""
import json
import os
from pathlib import Path
import subprocess
import tempfile
import textwrap


root = Path(__file__).resolve().parents[1]
workflow = (root / ".github/workflows/release.yml").read_text()
publish = workflow.split("      - name: Publish versioned ZIP\n", 1)[1]
script = textwrap.dedent(publish.split("        run: |\n", 1)[1])
archive = "artnovo-core-1.0.0.zip"
complete = {"isDraft": False, "assets": [
    {"name": archive, "size": 123}, {"name": "SHA256SUMS", "size": 90}
]}
cases = [
    ("published complete release", complete, False, True, False),
    ("draft release", {**complete, "isDraft": True}, False, False, False),
    ("missing ZIP", {"isDraft": False, "assets": [complete["assets"][1]]}, False, False, False),
    ("missing checksum", {"isDraft": False, "assets": [complete["assets"][0]]}, False, False, False),
    ("empty ZIP", {"isDraft": False, "assets": [{"name": archive, "size": 0}, complete["assets"][1]]}, False, False, False),
    ("empty checksum", {"isDraft": False, "assets": [complete["assets"][0], {"name": "SHA256SUMS", "size": 0}]}, False, False, False),
    ("new release", None, False, True, True),
    ("existing tag without release", None, True, False, False),
]

stub = '''#!/usr/bin/env python3
import json, os, sys
from pathlib import Path
args = sys.argv[1:]
with Path(os.environ["MOCK_GH_CALLS"]).open("a") as output:
    output.write(json.dumps(args) + "\\n")
if args[:2] == ["release", "view"]:
    value = json.loads(os.environ["MOCK_RELEASE"])
    if value is None:
        sys.exit(1)
    print(json.dumps(value) if "--json" in args else "Existing release")
elif args[:1] == ["api"]:
    sys.exit(0 if os.environ["MOCK_TAG_EXISTS"] == "1" else 1)
elif args[:2] != ["release", "create"]:
    sys.exit(99)
'''

failures = []
with tempfile.TemporaryDirectory(prefix="artnovo-core-release-") as directory:
    temporary = Path(directory)
    binary = temporary / "gh"
    binary.write_text(stub)
    binary.chmod(0o700)
    for number, (name, metadata, tag_exists, succeeds, creates) in enumerate(cases):
        case = temporary / str(number)
        case.mkdir(mode=0o700)
        calls = case / "calls.jsonl"
        environment = {**os.environ,
            "PATH": str(temporary) + os.pathsep + os.environ["PATH"],
            "RUNNER_TEMP": str(case), "MOCK_GH_CALLS": str(calls),
            "MOCK_RELEASE": json.dumps(metadata), "MOCK_TAG_EXISTS": "1" if tag_exists else "0",
            "GH_TOKEN": "fixture-only", "GITHUB_REPOSITORY": "fixture/Artnovo-Core",
            "GITHUB_SHA": "a" * 40, "RELEASE_TAG": "v1.0.0", "RELEASE_VERSION": "1.0.0",
            "RELEASE_ARCHIVE": "dist/" + archive,
        }
        result = subprocess.run(["bash", "-e", "-o", "pipefail", "-c", script],
                                cwd=root, env=environment, text=True, capture_output=True)
        commands = [json.loads(line) for line in calls.read_text().splitlines()] if calls.exists() else []
        creation = [command for command in commands if command[:2] == ["release", "create"]]
        passed = (result.returncode == 0) == succeeds and len(creation) == int(creates)
        if not succeeds:
            passed = passed and "::error::" in result.stdout + result.stderr
        if creates and creation:
            command = creation[0]
            passed = passed and command[2:5] == ["v1.0.0", "dist/" + archive, "dist/SHA256SUMS"]
            passed = passed and command[command.index("--target") + 1] == environment["GITHUB_SHA"]
        print(("PASS " if passed else "FAIL ") + name)
        if not passed:
            failures.append(name)
            print((result.stdout + result.stderr)[-600:])

raise SystemExit(1 if failures else 0)
