"""Trial 015 (maintainer review) evaluator, built on the Trials 011-014 runner. Python standard library only.

schema.json keys:
  groups            weights per group (sum 100), including "runtime" and "phpstan"
  features          hidden-suite groups credited as improvement over the pristine baseline
  preserve          hidden-suite groups credited as share passing, only alongside a feature gain
  protected         paths (files or directories) the candidate must not change (else invalid)
  minimal           optional {"weight_group", "root_cause", "max_files", "max_lines"} (Trial 013)
"""
import argparse
from collections import Counter
import difflib
import functools
import hashlib
import json
import os
import re
from pathlib import Path
import shutil
import subprocess
import tempfile

ROOT = Path(__file__).resolve().parent
SCHEMA = json.loads((ROOT / "schema.json").read_text())
MUTANTS = json.loads((ROOT / "mutants.json").read_text()) if (ROOT / "mutants.json").exists() else []
ENV = dict(os.environ, XDEBUG_MODE="off")
PHP74 = "/opt/homebrew/opt/php@7.4/bin/php"


def execute(command, cwd=None, timeout=300):
    try:
        result = subprocess.run(command, capture_output=True, text=True, encoding="utf-8",
                                errors="replace", timeout=timeout, cwd=cwd, env=ENV)
    except subprocess.TimeoutExpired:
        return 124, "", "timeout"
    return result.returncode, result.stdout, result.stderr


def suite(php, workspace):
    code, output, error = execute([php, str(ROOT / "hidden.php"), str(workspace)])
    try:
        return json.loads(output.strip().splitlines()[-1])
    except (ValueError, IndexError):
        raise RuntimeError("Hidden suite could not execute (exit %d): %s" % (code, (error or output)[-2000:]))


@functools.lru_cache(maxsize=None)
def baseline_suite(php):
    return suite(php, ROOT / "agent")


def tree_digest(path):
    if not path.exists():
        return None
    files = [path] if path.is_file() else sorted(p for p in path.rglob("*") if p.is_file())
    h = hashlib.sha256()
    for file in files:
        h.update(str(file.relative_to(path.parent)).encode())
        h.update(file.read_bytes())
    return h.hexdigest()


def feature_points(weight, candidate, baseline):
    opportunities = [key for key, passed in baseline.items() if not passed]
    gained = sum(bool(candidate.get(key)) for key in opportunities)
    points = weight * gained / len(opportunities) if opportunities else 0
    if sum(candidate.values()) / len(candidate) < 0.5:
        points = min(points, weight / 2)
    return round(points, 2)


def static_errors(php, phar, workspace, temporary):
    config = temporary / "phpstan.neon"
    config.write_text("parameters:\n    level: max\n    phpVersion: 70400\n    paths:\n        - "
                      + json.dumps(str(workspace / "app")) + "\n", encoding="utf-8")
    code, output, error = execute([php, "-d", "memory_limit=1G", phar, "analyse", "-c", str(config),
                                   "--error-format=json", "--no-progress"])
    if code not in (0, 1):
        raise RuntimeError("PHPStan infrastructure error: " + (error or output)[-1000:])
    report = json.loads(output)
    fingerprints = Counter()
    for file, errors in (report.get("files") or {}).items():
        relative = str(Path(file).resolve().relative_to(workspace.resolve()))
        for message in errors["messages"]:
            fingerprints[(relative, message.get("identifier"), message["message"])] += 1
    return fingerprints


def app_diff(workspace):
    """Files under app/ that differ from the pristine workspace, and changed line count."""
    pristine, candidate = ROOT / "agent" / "app", workspace / "app"
    names = {p.relative_to(pristine) for p in pristine.rglob("*.php")} | {p.relative_to(candidate) for p in candidate.rglob("*.php")}
    changed, lines = [], 0
    for name in sorted(names):
        a = (pristine / name).read_text().splitlines() if (pristine / name).exists() else []
        b = (candidate / name).read_text().splitlines() if (candidate / name).exists() else []
        if a != b:
            changed.append(str(Path("app") / name))
            lines += sum(1 for line in difflib.unified_diff(a, b, lineterm="", n=0)
                         if line[:1] in "+-" and not line.startswith(("+++", "---")))
    return changed, lines



CONVENTIONS = [
    ("clock", r"\b(time|date|strtotime|mktime)\s*\(|new\s+\\?DateTime(Immutable)?\s*\(\s*(\)|['\"]now)", None),
    ("exceptions", r"throw\s+new\s+\\?(RuntimeException|LogicException|InvalidArgumentException|DomainException|Exception|UnexpectedValueException|RangeException|OutOfBoundsException)\b", None),
    ("addslashes", r"\baddslashes\s*\(", None),
    ("float-money", r"\(float\)|\bfloatval\s*\(|\bround\s*\(", ("Payments", "Shipping", "Tax")),
]


def convention_counts(workspace):
    counts = Counter()
    for file in (workspace / "app").rglob("*.php"):
        rel = file.relative_to(workspace / "app")
        if rel.parts[0] == "Support":
            continue
        text = file.read_text(errors="replace")
        for name, pattern, dirs in CONVENTIONS:
            if dirs and rel.parts[0] not in dirs:
                continue
            counts[name] += len(re.findall(pattern, text))
    return counts


def run_tests(php, workspace):
    code, output, _ = execute([php, str(workspace / "tests/run.php")], cwd=str(workspace), timeout=120)
    return code == 0, output


def failing_tests(output):
    return {line[5:].split(":", 1)[0] for line in output.splitlines() if line.startswith("FAIL ")}


def mutant_kills(php, tests_from, temporary):
    workspace = temporary / "mutation"
    if workspace.exists():
        shutil.rmtree(workspace)
    shutil.copytree(tests_from, workspace, ignore=shutil.ignore_patterns("vendor", ".git", "tools"))
    for file in (ROOT / "reference" / "app").rglob("*.php"):
        target = workspace / file.relative_to(ROOT / "reference")
        target.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(file, target)
    passes, output = run_tests(php, workspace)
    ignored = failing_tests(output) if not passes else set()
    usable = passes or bool(ignored)
    kills = {}
    for mutant in MUTANTS:
        path = workspace / mutant["file"]
        original = path.read_text()
        path.write_text(original.replace(mutant["old"], mutant["new"]))
        ok, out = run_tests(php, workspace)
        kills[mutant["id"]] = (not ok) if passes else bool(failing_tests(out) - ignored)
        path.write_text(original)
    return usable, kills


@functools.lru_cache(maxsize=None)
def pristine_kills(php):
    with tempfile.TemporaryDirectory(prefix="orc-mut-") as directory:
        return mutant_kills(php, ROOT / "agent", Path(directory))[1]


def review_groups(workspace, php, weights, core_gain, mutation=True):
    groups = {}
    ci_files = SCHEMA["ci_files"]
    tampered = [f for f in ci_files if tree_digest(workspace / f) != tree_digest(ROOT / "agent" / f)]
    code, output, _ = execute([php, str(workspace / "bin/ci")], cwd=str(workspace), timeout=600)
    green = code == 0 and not tampered
    groups["ci"] = {"points": weights["ci"] if green and core_gain else 0, "maximum": weights["ci"],
                    "green": code == 0, "ci_files_changed": tampered,
                    "tail": [l for l in output.splitlines() if l.strip()][-6:] if code else []}
    new = convention_counts(workspace) - convention_counts(ROOT / "agent")
    groups["conventions"] = {"points": max(0, weights["conventions"] - 2.5 * sum(new.values())) if core_gain else 0,
                             "maximum": weights["conventions"], "violations": dict(new)}
    original_tests = sorted((ROOT / "agent" / "tests" / "cases").glob("*.php"))
    untouched = all(tree_digest(workspace / "tests/cases" / f.name) == tree_digest(f) for f in original_tests)
    added = len(list((workspace / "tests" / "cases").glob("*.php"))) > len(original_tests)
    changed, lines = app_diff(workspace)
    # New files are fine (e.g. a DomainError subclass); editing unrelated existing files is not.
    outside = [f for f in changed if (ROOT / "agent" / f).exists()
               and not any(f.startswith(p) for p in SCHEMA["scope_allowed"])]
    points = (2 if untouched else 0) + (1 if added else 0) + (2 if not outside else 0)
    groups["discipline"] = {"points": points if core_gain else 0, "maximum": weights["discipline"],
                            "existing_tests_untouched": untouched, "new_test_file": added,
                            "out_of_scope_files": outside, "changed_lines": lines}
    groups["tests"] = {"points": 0, "maximum": weights["tests"]}
    if mutation:
        with tempfile.TemporaryDirectory(prefix="orc-mut-") as directory:
            usable, kills = mutant_kills(php, workspace, Path(directory))
        chances = [m for m, k in pristine_kills(php).items() if not k]
        gained = [m for m in chances if kills.get(m)] if usable else []
        groups["tests"].update(points=round(weights["tests"] * len(gained) / len(chances), 2) if core_gain and chances else 0,
                               usable_on_reference=usable, survivors=[m for m in chances if m not in gained])
    return groups


def evaluate(workspace, php=PHP74, runtime_php=None, phpstan=None, mutation=True):
    workspace = workspace.resolve()
    weights = SCHEMA["groups"]
    tampered = [p for p in SCHEMA.get("protected", [])
                if tree_digest(workspace / p) != tree_digest(ROOT / "agent" / p)]
    candidate = suite(php, workspace)
    baseline = baseline_suite(php)
    groups = {}
    for group in SCHEMA["features"]:
        groups[group] = {"points": feature_points(weights[group], candidate[group], baseline[group]),
                         "maximum": weights[group], "passed": sum(candidate[group].values()),
                         "total": len(candidate[group]),
                         "failures": [k for k, ok in candidate[group].items() if not ok]}
    core_gain = any(groups[g]["points"] > 0 for g in SCHEMA["features"])
    for group in SCHEMA.get("preserve", []):
        passed = sum(candidate[group].values())
        groups[group] = {"points": round(weights[group] * passed / len(candidate[group]), 2) if core_gain else 0,
                         "maximum": weights[group], "passed": passed, "total": len(candidate[group]),
                         "failures": [k for k, ok in candidate[group].items() if not ok]}
    minimal = SCHEMA.get("minimal")
    if minimal:
        changed, lines = app_diff(workspace)
        name = minimal["weight_group"]
        root_hit = minimal["root_cause"] in changed
        others = [f for f in changed if f != minimal["root_cause"]]
        if not core_gain or not root_hit:
            points = 0
        else:
            points = weights[name]
            points -= weights[name] * 0.25 * len(others)
            if lines > minimal["max_lines"]:
                points -= weights[name] * 0.25
        groups[name] = {"points": round(max(0, points), 2), "maximum": weights[name], "changed_files": changed,
                        "changed_lines": lines, "root_cause_changed": root_hit}
    groups.update(review_groups(workspace, php, weights, core_gain, mutation))
    if "runtime" not in weights:
        score = round(sum(g["points"] for g in groups.values()), 2)
        return {"case": SCHEMA["id"], "score": 0 if tampered else score, "maximum": 100,
                "valid_run": not tampered, "protected_changed": tampered, "groups": groups}
    groups["runtime"] = {"points": 0, "maximum": weights["runtime"], "status": "not_measured"}
    if runtime_php:
        lint = [str(f.relative_to(workspace)) for f in sorted(workspace.rglob("*.php"))
                if "vendor" not in f.parts and execute([runtime_php, "-l", str(f)])[0] != 0]
        visible = execute([runtime_php, str(workspace / "tests/run.php")], cwd=str(workspace))[0] == 0 if not lint else False
        ok = not lint and visible
        groups["runtime"].update(points=weights["runtime"] if ok and core_gain else 0,
                                 status="passed" if ok else "failed", lint_failures=lint, visible_tests_pass=visible)
    groups["phpstan"] = {"points": 0, "maximum": weights["phpstan"], "status": "not_measured"}
    if phpstan:
        with tempfile.TemporaryDirectory(prefix="orc-stan-") as directory:
            original = static_errors(php, phpstan, ROOT / "agent", Path(directory))
            current = static_errors(php, phpstan, workspace, Path(directory))
        introduced = sum((current - original).values())
        groups["phpstan"].update(points=max(0, weights["phpstan"] - introduced) if core_gain else 0,
                                 status="measured", new_errors=introduced,
                                 new_error_details=[list(k) + [c] for k, c in (current - original).items()])
    score = round(sum(g["points"] for g in groups.values()), 2)
    return {"case": SCHEMA["id"], "score": 0 if tampered else score, "maximum": 100,
            "valid_run": not tampered, "protected_changed": tampered, "groups": groups}


def prepare(destination):
    if destination.exists():
        raise ValueError("Destination must not exist")
    shutil.copytree(ROOT / "agent", destination)
    return {"agent_workspace": str(destination.resolve())}


def selftest(php, runtime_php, phpstan):
    control = evaluate(ROOT / "agent", php, runtime_php, phpstan)
    assert control["score"] == 0, control
    with tempfile.TemporaryDirectory(prefix="orc-ref-") as directory:
        workspace = Path(directory) / "candidate"
        prepare(workspace)
        for file in (ROOT / "reference").rglob("*.php"):
            target = workspace / file.relative_to(ROOT / "reference")
            target.parent.mkdir(parents=True, exist_ok=True)
            shutil.copy2(file, target)
        reference = evaluate(workspace, php, runtime_php, phpstan)
        for group, weight in SCHEMA["groups"].items():
            if group in ("phpstan", "runtime") and not (phpstan if group == "phpstan" else runtime_php):
                continue
            assert reference["groups"][group]["points"] == weight, (group, reference["groups"][group])
        core = lambda r: sum(g["points"] for n, g in r["groups"].items() if n in SCHEMA["features"] + SCHEMA.get("preserve", []))
        for mutant in MUTANTS:
            path = workspace / mutant["file"]
            original = path.read_text()
            assert mutant["old"] in original, mutant["id"]
            path.write_text(original.replace(mutant["old"], mutant["new"]))
            result = evaluate(workspace, php, mutation=False)
            path.write_text(original)
            assert core(result) < core(reference), (mutant["id"], result["groups"])
    return {"selftest": "passed", "no_change_score": control["score"], "reference_score": reference["score"],
            "mutants_checked": len(MUTANTS)}


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("action", choices=("prepare", "score", "selftest", "export-evaluator"))
    parser.add_argument("--workspace", type=Path)
    parser.add_argument("--php", default=PHP74)
    parser.add_argument("--runtime-php")
    parser.add_argument("--phpstan")
    args = parser.parse_args()
    if args.action == "prepare":
        result = prepare(args.workspace)
    elif args.action == "export-evaluator":
        shutil.copytree(ROOT, args.workspace, ignore=shutil.ignore_patterns("__pycache__"))
        result = {"evaluator_directory": str(args.workspace.resolve())}
    elif args.action == "selftest":
        result = selftest(args.php, args.runtime_php, args.phpstan)
    else:
        result = evaluate(args.workspace, args.php, args.runtime_php, args.phpstan)
    print(json.dumps(result, indent=2))


if __name__ == "__main__":
    main()
