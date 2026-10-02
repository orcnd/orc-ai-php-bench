"""Cross-platform fixture exporter and evaluator. Python standard library only."""
import argparse
from collections import Counter
import hashlib
import json
from pathlib import Path
import shutil
import subprocess
import tempfile

ROOT = Path(__file__).resolve().parent
SCHEMA = json.loads((ROOT / "schema.json").read_text())


def execute(command):
    result = subprocess.run(command, capture_output=True, text=True, encoding="utf-8", errors="replace", timeout=90)
    return result.returncode, result.stdout, result.stderr


def suite(php, workspace):
    code, output, error = execute([php, str(ROOT / "hidden.php"), str(workspace)])
    if code:
        raise RuntimeError("Hidden suite could not execute: " + error[-2000:])
    return json.loads(output)


def digest(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def feature_points(weight, candidate, baseline):
    # No-change controls cannot earn credit for tests they already pass.
    opportunities = [key for key, passed in baseline.items() if not passed]
    gained = sum(bool(candidate.get(key)) for key in opportunities)
    points = weight * gained / len(opportunities) if opportunities else 0
    ratio = sum(candidate.values()) / len(candidate)
    if ratio < 0.5:
        points = min(points, weight / 2)
    return round(points, 2)


def static_errors(php, phar, workspace, temporary):
    config = temporary / "phpstan.neon"
    config.write_text(
        "parameters:\n    level: max\n    phpVersion: 70400\n"
        "    paths:\n        - " + json.dumps(str(workspace / "app")) + "\n",
        encoding="utf-8",
    )
    code, output, error = execute([php, phar, "analyse", "-c", str(config),
                                   "--error-format=json", "--no-progress"])
    if code not in (0, 1):
        raise RuntimeError("PHPStan infrastructure error: " + error[-1000:])
    report = json.loads(output)
    if report.get("errors"):
        raise RuntimeError("PHPStan global errors: " + repr(report["errors"]))
    fingerprints = Counter()
    for file, errors in report.get("files", {}).items():
        relative = str(Path(file).resolve().relative_to(workspace.resolve()))
        for message in errors["messages"]:
            # Lines may move; unchanged errors retain identity and multiplicity.
            fingerprints[(relative, message.get("identifier"), message["message"])] += 1
    return fingerprints


def evaluate(workspace, php, runtime_php=None, phpstan=None, trace=None):
    workspace = workspace.resolve()
    candidate = suite(php, workspace)
    baseline = suite(php, ROOT / "agent")
    groups = {}
    for group in ("public", "pricing", "snapshot", "idempotency"):
        groups[group] = {
            "points": feature_points(SCHEMA["groups"][group], candidate[group], baseline[group]),
            "maximum": SCHEMA["groups"][group],
            "passed": sum(candidate[group].values()),
            "total": len(candidate[group]),
            "failures": [name for name, passed in candidate[group].items() if not passed],
        }
    core_gain = any(value["points"] > 0 for value in groups.values())
    # Scope credit requires both preserved behaviour and unchanged protected files.
    protected = ("UserController.php", "NoteRepository.php")
    unchanged = all(digest(workspace / "app" / file) == digest(ROOT / "agent/app" / file)
                    for file in protected)
    scope_pass = sum(candidate["scope"].values())
    scope_points = 10 * scope_pass / len(candidate["scope"]) if core_gain and unchanged else 0
    if scope_pass / len(candidate["scope"]) < 0.5:
        scope_points = min(scope_points, 5)
    groups["scope"] = {"points": round(scope_points, 2), "maximum": 10,
                       "passed": scope_pass, "total": len(candidate["scope"]),
                       "protected_files_unchanged": unchanged}
    groups["runtime"] = {"points": 0, "maximum": 10, "status": "not_measured"}
    if runtime_php:
        code, version, _ = execute([runtime_php, "-r",
                                    'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;'])
        if code or version != SCHEMA["target_php"]:
            raise RuntimeError("Target-runtime executable must be PHP " + SCHEMA["target_php"])
        lint = all(execute([runtime_php, "-l", str(file)])[0] == 0
                   for file in workspace.rglob("*.php") if "vendor" not in file.parts)
        compatible = lint
        if lint:
            try:
                suite(runtime_php, workspace)
            except RuntimeError:
                compatible = False
        groups["runtime"].update(points=10 if compatible and core_gain else 0,
                                 status="passed" if compatible else "failed")
    groups["phpstan"] = {"points": 0, "maximum": 10, "status": "not_measured"}
    if phpstan:
        with tempfile.TemporaryDirectory(prefix="orc-stan-") as directory:
            temporary = Path(directory)
            original = static_errors(php, phpstan, ROOT / "agent", temporary)
            current = static_errors(php, phpstan, workspace, temporary)
        introduced = sum((current - original).values())
        groups["phpstan"].update(
            points=max(0, 10 - 2 * introduced) if core_gain else 0,
            status="measured", new_errors=introduced,
            baseline_errors=sum(original.values()),
            new_error_details=[list(key) + [count] for key, count in (current - original).items()],
        )
    groups["context"] = {"points": 0, "maximum": 5, "status": "not_measured"}
    contaminated = False
    if trace:
        events = [json.loads(line) for line in Path(trace).read_text().splitlines() if line.strip()]
        total = 0
        waste = 0
        for event in events:
            path = str(event.get("path", "")).replace("\\", "/").lower()
            parts = path.split("/")
            tokens = event.get("tokens_returned", 0)
            if not isinstance(tokens, int) or tokens < 0:
                raise ValueError("Invalid trace token count")
            total += tokens
            if "vendor" in parts:
                waste += tokens
            if ("evaluator" in parts or "reference" in parts or path.endswith("hidden.php")
                    or path.endswith("schema.json")):
                contaminated = True
        if total:
            groups["context"].update(points=round(5 * (1 - waste / total), 2) if core_gain else 0,
                                     status="measured_tool_tokens_only", tool_tokens=total,
                                     vendor_tokens=waste)
    score = round(sum(group["points"] for group in groups.values()), 2)
    return {
        "case": SCHEMA["id"], "score": None if contaminated else score,
        "maximum": 100, "valid_run": not contaminated,
        "complete_measurement": all(group.get("status") != "not_measured" for group in groups.values()),
        "score_interpretation": "invalid" if contaminated else (
            "provisional" if any(g.get("status") == "not_measured" for g in groups.values()) else "measured"
        ),
        "contamination_detected": contaminated,
        "groups": groups,
    }


def prepare(destination):
    if destination.exists():
        raise ValueError("Destination must not exist; refusing to overwrite")
    shutil.copytree(ROOT / "agent", destination)
    return {"agent_workspace": str(destination.resolve()),
            "isolation": "Run the agent with filesystem access limited to this directory."}


def selftest(php, phpstan):
    control = evaluate(ROOT / "agent", php, phpstan=phpstan)
    assert control["score"] == 0, control
    code, _, _ = execute([php, str(ROOT / "agent/tests/run.php")])
    assert code != 0, "Pristine public test must reproduce the bug"
    with tempfile.TemporaryDirectory(prefix="orc-reference-") as directory:
        workspace = Path(directory) / "candidate"
        prepare(workspace)
        assert not (workspace / "hidden.php").exists()
        assert not (workspace / "reference").exists()
        for file in (ROOT / "reference").glob("*.php"):
            shutil.copy2(file, workspace / "app" / file.name)
        reference = evaluate(workspace, php, phpstan=phpstan)
        for group in ("public", "pricing", "snapshot", "idempotency", "scope"):
            assert reference["groups"][group]["points"] == SCHEMA["groups"][group], reference
        if phpstan:
            assert reference["groups"]["phpstan"]["points"] == 10, reference
        code, _, _ = execute([php, str(workspace / "tests/run.php")])
        assert code == 0, "Reference must pass visible test"
        # Mutation control: remove retry guard while keeping pricing fixed.
        service = workspace / "app/RefundService.php"
        service.write_text(service.read_text().replace(
            "if (isset($this->repository->refunds[$id])) { return $this->repository->refunds[$id]; }", ""
        ))
        mutant = evaluate(workspace, php, phpstan=phpstan)
        assert mutant["groups"]["idempotency"]["points"] < 15, mutant
        assert mutant["score"] < reference["score"], mutant
        trace = Path(directory) / "trace.jsonl"
        trace.write_text(json.dumps({"path": "evaluator/hidden.php", "tokens_returned": 100}) + "\n")
        invalid = evaluate(workspace, php, trace=str(trace))
        assert invalid["valid_run"] is False and invalid["score"] is None, invalid
        trace.write_text("")
        empty_trace = evaluate(workspace, php, trace=str(trace))
        assert empty_trace["groups"]["context"]["status"] == "not_measured"
        assert feature_points(20, {"a": True, "b": False, "c": False},
                              {"a": False, "b": True, "c": True}) == 10
    return {"selftest": "passed", "no_change": control,
            "reference": reference, "retry_mutation": mutant}


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
        result = selftest(args.php, args.phpstan)
    else:
        if args.workspace is None:
            parser.error("--workspace is required")
        result = evaluate(args.workspace, args.php, args.runtime_php, args.phpstan, args.trace)
    print(json.dumps(result, indent=2))
    if result.get("valid_run") is False:
        raise SystemExit(2)


if __name__ == "__main__":
    main()
