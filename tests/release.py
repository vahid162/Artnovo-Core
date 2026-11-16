#!/usr/bin/env python3
"""Exercise the real release step with a local gh stub; no network or token is used."""
import hashlib
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
candidate_zip = "synthetic candidate runtime archive\n"
candidate_sums = hashlib.sha256(candidate_zip.encode()).hexdigest() + "  " + archive + "\n"
published = {archive: candidate_zip, "SHA256SUMS": candidate_sums}
complete = {"isDraft": False, "assets": [
    {"name": archive, "size": 123}, {"name": "SHA256SUMS", "size": 90}
]}
cases = [
    ("published identical release", complete, False, published, True, False),
    ("different published ZIP", complete, False, {
        archive: "different published archive\n",
        "SHA256SUMS": hashlib.sha256(b"different published archive\n").hexdigest() + "  " + archive + "\n",
    }, False, False),
    ("different published checksum", complete, False, {**published, "SHA256SUMS": "0" * 64 + "  " + archive + "\n"}, False, False),
    ("partial asset download", complete, False, {archive: candidate_zip}, False, False),
    ("asset download failure", complete, False, None, False, False),
    ("draft release", {**complete, "isDraft": True}, False, published, False, False),
    ("missing ZIP", {"isDraft": False, "assets": [complete["assets"][1]]}, False, published, False, False),
    ("missing checksum", {"isDraft": False, "assets": [complete["assets"][0]]}, False, published, False, False),
    ("empty ZIP", {"isDraft": False, "assets": [{"name": archive, "size": 0}, complete["assets"][1]]}, False, published, False, False),
    ("empty checksum", {"isDraft": False, "assets": [complete["assets"][0], {"name": "SHA256SUMS", "size": 0}]}, False, published, False, False),
    ("new release", None, False, published, True, True),
    ("existing tag without release", None, True, published, False, False),
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
elif args[:2] == ["release", "download"]:
    assets = json.loads(os.environ["MOCK_PUBLISHED_ASSETS"])
    if assets is None:
        sys.exit(1)
    target = Path(args[args.index("--dir") + 1])
    for name, content in assets.items():
        (target / name).write_text(content)
elif args[:2] != ["release", "create"]:
    sys.exit(99)
'''

failures = []
with tempfile.TemporaryDirectory(prefix="artnovo-core-release-") as directory:
    temporary = Path(directory)
    binary = temporary / "gh"
    binary.write_text(stub)
    binary.chmod(0o700)
    for number, (name, metadata, tag_exists, assets, succeeds, creates) in enumerate(cases):
        case = temporary / str(number)
        case.mkdir(mode=0o700)
        (case / "dist").mkdir()
        (case / "dist" / archive).write_text(candidate_zip)
        (case / "dist" / "SHA256SUMS").write_text(candidate_sums)
        calls = case / "calls.jsonl"
        environment = {**os.environ,
            "PATH": str(temporary) + os.pathsep + os.environ["PATH"],
            "RUNNER_TEMP": str(case), "MOCK_GH_CALLS": str(calls),
            "MOCK_RELEASE": json.dumps(metadata), "MOCK_TAG_EXISTS": "1" if tag_exists else "0",
            "MOCK_PUBLISHED_ASSETS": json.dumps(assets),
            "GH_TOKEN": "fixture-only", "GITHUB_REPOSITORY": "fixture/Artnovo-Core",
            "GITHUB_SHA": "a" * 40, "RELEASE_TAG": "v1.0.0", "RELEASE_VERSION": "1.0.0",
            "RELEASE_ARCHIVE": "dist/" + archive,
            "GITHUB_EVENT_NAME": "workflow_dispatch", "GITHUB_REF": "refs/heads/main",
            "RELEASE_CONFIRMED": "true",
        }
        result = subprocess.run(["bash", "-e", "-o", "pipefail", "-c", script],
                                cwd=case, env=environment, text=True, capture_output=True)
        commands = [json.loads(line) for line in calls.read_text().splitlines()] if calls.exists() else []
        creation = [command for command in commands if command[:2] == ["release", "create"]]
        downloads = [command for command in commands if command[:2] == ["release", "download"]]
        passed = (result.returncode == 0) == succeeds and len(creation) == int(creates)
        passed = passed and len(downloads) == int(metadata == complete)
        if downloads:
            command = downloads[0]
            patterns = [command[index + 1] for index, value in enumerate(command) if value == "--pattern"]
            passed = passed and command[2] == environment["RELEASE_TAG"]
            passed = passed and command[command.index("--repo") + 1] == environment["GITHUB_REPOSITORY"]
            passed = passed and set(patterns) == {archive, "SHA256SUMS"} and "--clobber" not in command
        permitted = (["release", "view"], ["release", "download"], ["release", "create"])
        passed = passed and all(command[:2] in permitted or command[:1] == ["api"] for command in commands)
        passed = passed and (case / "dist" / archive).read_text() == candidate_zip
        passed = passed and (case / "dist" / "SHA256SUMS").read_text() == candidate_sums
        passed = passed and not list(case.glob("artnovo-published-*"))
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

    denied = [
        ("push cannot publish", {"GITHUB_EVENT_NAME": "push"}),
        ("pull request cannot publish", {"GITHUB_EVENT_NAME": "pull_request"}),
        ("feature branch cannot publish", {"GITHUB_REF": "refs/heads/work/test"}),
        ("tag cannot publish", {"GITHUB_REF": "refs/tags/v1.0.0"}),
        ("unconfirmed dispatch cannot publish", {"RELEASE_CONFIRMED": "false"}),
        ("missing confirmation cannot publish", {"RELEASE_CONFIRMED": ""}),
    ]
    for number, (name, overrides) in enumerate(denied):
        case = temporary / f"denied-{number}"
        case.mkdir(mode=0o700)
        calls = case / "calls.jsonl"
        blocked_environment = {**environment, "RUNNER_TEMP": str(case),
            "MOCK_GH_CALLS": str(calls), "MOCK_RELEASE": "null", "MOCK_TAG_EXISTS": "0",
            **overrides,
        }
        result = subprocess.run(["bash", "-e", "-o", "pipefail", "-c", script],
                                cwd=root, env=blocked_environment, text=True, capture_output=True)
        passed = result.returncode != 0 and "::error::" in result.stdout + result.stderr
        passed = passed and not calls.exists()
        print(("PASS " if passed else "FAIL ") + name)
        if not passed:
            failures.append(name)
            print((result.stdout + result.stderr)[-600:])

raise SystemExit(1 if failures else 0)
