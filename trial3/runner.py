"""Trial 003 exporter and evaluator. Python standard library only."""
import argparse
from collections import Counter
import hashlib
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile

ROOT = Path(__file__).resolve().parent
SCHEMA = json.loads((ROOT / "schema.json").read_text())
MUTANTS = json.loads((ROOT / "mutants.json").read_text())
FEATURES = ("public", "discount", "allocation", "partial", "snapshot", "idempotency")
ENV = dict(os.environ, XDEBUG_MODE="off")


def execute(command, cwd=None, timeout=120):
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


def digest(path):
    return hashlib.sha256(path.read_bytes()).hexdigest() if path.exists() else None


def feature_points(weight, candidate, baseline):
    # No-change controls cannot earn credit for tests they already pass.
    opportunities = [key for key, passed in baseline.items() if not passed]
    gained = sum(bool(candidate.get(key)) for key in opportunities)
    points = weight * gained / len(opportunities) if opportunities else 0
    if sum(candidate.values()) / len(candidate) < 0.5:
        points = min(points, weight / 2)
    return round(points, 2)


def static_errors(php, phar, workspace, temporary):
    config = temporary / "phpstan.neon"
    config.write_text(
        "parameters:\n    level: max\n    phpVersion: 70400\n"
        "    paths:\n        - " + json.dumps(str(workspace / "app")) + "\n",
        encoding="utf-8",
    )
    code, output, error = execute([php, "-d", "memory_limit=1G", phar, "analyse", "-c", str(config),
                                   "--error-format=json", "--no-progress"])
    if code not in (0, 1):
        raise RuntimeError("PHPStan infrastructure error: " + (error or output)[-1000:])
    report = json.loads(output)
    fingerprints = Counter()
    for file, errors in report.get("files", {}).items():
        relative = str(Path(file).resolve().relative_to(workspace.resolve()))
        for message in errors["messages"]:
            # Lines may move; unchanged errors retain identity and multiplicity.
            fingerprints[(relative, message.get("identifier"), message["message"])] += 1
    for message in report.get("errors", []):
        fingerprints[("<global>", None, message)] += 1
    return fingerprints


def run_tests(php, workspace):
    code, output, _ = execute([php, str(workspace / "tests/run.php")], cwd=str(workspace), timeout=60)
    return code == 0, output


def mutant_kills(php, tests_from, temporary):
    """Run tests from `tests_from` against the reference and each mutant."""
    workspace = temporary / "mutation"
    if workspace.exists():
        shutil.rmtree(workspace)
    shutil.copytree(tests_from, workspace, ignore=shutil.ignore_patterns("vendor", ".git"))
    for file in (ROOT / "reference").rglob("*.php"):
        target = workspace / file.relative_to(ROOT / "reference")
        target.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(file, target)
    passes_reference, _ = run_tests(php, workspace)
    kills = {}
    for mutant in MUTANTS:
        path = workspace / mutant["file"]
        original = path.read_text()
        assert mutant["old"] in original, mutant["id"]
        path.write_text(original.replace(mutant["old"], mutant["new"]))
        kills[mutant["id"]] = not run_tests(php, workspace)[0]
        path.write_text(original)
    return passes_reference, kills


def evaluate(workspace, php, runtime_php=None, phpstan=None, trace=None):
    workspace = workspace.resolve()
    weights = SCHEMA["groups"]
    candidate = suite(php, workspace)
    baseline = suite(php, ROOT / "agent")
    groups = {}
    for group in FEATURES:
        groups[group] = {
            "points": feature_points(weights[group], candidate[group], baseline[group]),
            "maximum": weights[group],
            "passed": sum(candidate[group].values()),
            "total": len(candidate[group]),
            "failures": [name for name, passed in candidate[group].items() if not passed],
        }
    core_gain = any(groups[group]["points"] > 0 for group in FEATURES)

    # Scope: other teams' behaviour and protected files must be untouched.
    changed = [file for file in SCHEMA["protected_files"]
               if digest(workspace / file) != digest(ROOT / "agent" / file)]
    scope_pass = sum(candidate["scope"].values())
    ratio = scope_pass / len(candidate["scope"])
    scope_points = weights["scope"] * ratio if core_gain and not changed else 0
    groups["scope"] = {"points": round(scope_points, 2), "maximum": weights["scope"],
                       "passed": scope_pass, "total": len(candidate["scope"]),
                       "failures": [n for n, ok in candidate["scope"].items() if not ok],
                       "protected_files_changed": changed}

    groups["runtime"] = {"points": 0, "maximum": weights["runtime"], "status": "not_measured"}
    if runtime_php:
        code, version, _ = execute([runtime_php, "-r", 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;'])
        if code or version != SCHEMA["target_php"]:
            raise RuntimeError("Target-runtime executable must be PHP " + SCHEMA["target_php"])
        lint_failures = [str(file.relative_to(workspace)) for file in sorted(workspace.rglob("*.php"))
                         if "vendor" not in file.parts and execute([runtime_php, "-l", str(file)])[0] != 0]
        visible_ok = run_tests(runtime_php, workspace)[0] if not lint_failures else False
        same_results = False
        if not lint_failures:
            try:
                same_results = suite(runtime_php, workspace) == candidate
            except RuntimeError:
                same_results = False
        compatible = not lint_failures and visible_ok and same_results
        groups["runtime"].update(points=weights["runtime"] if compatible and core_gain else 0,
                                 status="passed" if compatible else "failed",
                                 lint_failures=lint_failures, visible_tests_pass=visible_ok,
                                 hidden_results_match=same_results)

    groups["phpstan"] = {"points": 0, "maximum": weights["phpstan"], "status": "not_measured"}
    if phpstan:
        with tempfile.TemporaryDirectory(prefix="orc-stan-") as directory:
            original = static_errors(php, phpstan, ROOT / "agent", Path(directory))
            current = static_errors(php, phpstan, workspace, Path(directory))
        introduced = sum((current - original).values())
        groups["phpstan"].update(
            points=max(0, weights["phpstan"] - introduced) if core_gain else 0,
            status="measured", new_errors=introduced, baseline_errors=sum(original.values()),
            new_error_details=[list(key) + [count] for key, count in (current - original).items()],
        )

    # Regression-test quality: mutants of the reference killed by the candidate's tests.
    with tempfile.TemporaryDirectory(prefix="orc-mut-") as directory:
        temporary = Path(directory)
        own_pass, _ = run_tests(php, workspace)
        _, pristine_kills = mutant_kills(php, ROOT / "agent", temporary)
        reference_pass, kills = mutant_kills(php, workspace, temporary)
    opportunities = [m for m, killed in pristine_kills.items() if not killed]
    gained = [m for m in opportunities if kills.get(m)] if own_pass and reference_pass else []
    groups["tests"] = {
        "points": round(weights["tests"] * len(gained) / len(opportunities), 2) if core_gain and opportunities else 0,
        "maximum": weights["tests"], "own_tests_pass": own_pass, "pass_on_reference": reference_pass,
        "mutants_killed": len(gained), "mutants_available": len(opportunities),
        "survivors": [m for m in opportunities if m not in gained],
    }

    contaminated = False
    if trace:
        for line in Path(trace).read_text().splitlines():
            if not line.strip():
                continue
            text = json.dumps(json.loads(line)).replace("\\\\", "/").lower()
            if any(marker in text for marker in ("hidden.php", "mutants.json", "selftest_tests.php",
                                                 "/orc-ai-php-bench/trial", "/orc-ai-php-bench/evaluator")):
                contaminated = True
    score = round(sum(group["points"] for group in groups.values()), 2)
    return {
        "case": SCHEMA["id"], "score": None if contaminated else score, "maximum": 100,
        "valid_run": not contaminated, "contamination_detected": contaminated,
        "score_interpretation": "invalid" if contaminated else (
            "provisional" if any(g.get("status") == "not_measured" for g in groups.values()) else "measured"),
        "groups": groups,
    }


def prepare(destination):
    if destination.exists():
        raise ValueError("Destination must not exist; refusing to overwrite")
    shutil.copytree(ROOT / "agent", destination)
    return {"agent_workspace": str(destination.resolve()),
            "isolation": "Run the agent with filesystem access limited to this directory."}


def selftest(php, runtime_php, phpstan):
    control = evaluate(ROOT / "agent", php, runtime_php, phpstan)
    assert control["score"] == 0, control
    assert not run_tests(php, ROOT / "agent")[0], "Pristine visible test must reproduce the bug"
    with tempfile.TemporaryDirectory(prefix="orc-reference-") as directory:
        workspace = Path(directory) / "candidate"
        prepare(workspace)
        assert not (workspace / "hidden.php").exists() and not (workspace / "reference").exists()
        for file in (ROOT / "reference").rglob("*.php"):
            shutil.copy2(file, workspace / file.relative_to(ROOT / "reference"))
        shutil.copy2(ROOT / "selftest_tests.php", workspace / "tests/regression.php")
        run = workspace / "tests/run.php"
        run.write_text(run.read_text().replace(
            "echo $failures === 0", "require __DIR__ . '/regression.php';\n\necho $failures === 0"))
        reference = evaluate(workspace, php, runtime_php, phpstan)
        for group, weight in SCHEMA["groups"].items():
            if group == "runtime" and not runtime_php or group == "phpstan" and not phpstan:
                continue
            assert reference["groups"][group]["points"] == weight, (group, reference["groups"][group])
        # Mutation controls: every mutant must lose feature credit.
        for mutant in MUTANTS:
            path = workspace / mutant["file"]
            original = path.read_text()
            path.write_text(original.replace(mutant["old"], mutant["new"]))
            result = evaluate(workspace, php)
            path.write_text(original)
            assert result["score"] < reference["score"], (mutant["id"], result)
        trace = Path(directory) / "trace.jsonl"
        trace.write_text(json.dumps({"tool": "Read", "path": "/x/orc-ai-php-bench/trial3/hidden.php"}) + "\n")
        invalid = evaluate(workspace, php, trace=str(trace))
        assert invalid["valid_run"] is False and invalid["score"] is None
    return {"selftest": "passed", "no_change_score": control["score"], "reference_score": reference["score"],
            "mutants_checked": len(MUTANTS)}


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("action", choices=("prepare", "score", "selftest", "export-evaluator"))
    parser.add_argument("--workspace", type=Path)
    parser.add_argument("--php", default="php")
    parser.add_argument("--runtime-php")
    parser.add_argument("--phpstan")
    parser.add_argument("--trace")
    args = parser.parse_args()
    if args.action == "prepare":
        if args.workspace is None:
            parser.error("--workspace is required")
        result = prepare(args.workspace)
    elif args.action == "export-evaluator":
        if args.workspace is None or args.workspace.exists():
            parser.error("--workspace must be a new evaluator directory")
        shutil.copytree(ROOT, args.workspace, ignore=shutil.ignore_patterns("__pycache__"))
        result = {"evaluator_directory": str(args.workspace.resolve()),
                  "next_step": "Keep this directory outside the agent mount."}
    elif args.action == "selftest":
        result = selftest(args.php, args.runtime_php, args.phpstan)
    else:
        if args.workspace is None:
            parser.error("--workspace is required")
        result = evaluate(args.workspace, args.php, args.runtime_php, args.phpstan, args.trace)
    print(json.dumps(result, indent=2))
    if result.get("valid_run") is False:
        raise SystemExit(2)


if __name__ == "__main__":
    main()
