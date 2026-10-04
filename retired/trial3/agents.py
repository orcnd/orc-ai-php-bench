"""Run coding agents on a prepared trial workspace and score them.

Usage:
  python trial3/agents.py --out RUNS_DIR --trial trial3 --runs 2 claude:haiku claude:sonnet opencode:openrouter/x
Workspaces and transcripts are written under RUNS_DIR, which must be outside
this repository. Results are appended to RUNS_DIR/results.jsonl.
"""
import argparse
from concurrent.futures import ThreadPoolExecutor
import json
import os
from pathlib import Path
import subprocess
import time

REPO = Path(__file__).resolve().parent.parent
PROMPT = ("Read TASK.md in the current directory and complete the task. "
          "Work autonomously until you are done; nobody will answer questions.")


def command(agent, model):
    if agent == "claude":
        return ["claude", "-p", PROMPT, "--model", model, "--bare", "--output-format", "stream-json",
                "--verbose", "--dangerously-skip-permissions", "--no-session-persistence"]
    if agent == "opencode":
        return ["opencode", "run", "-m", model, "--format", "json", PROMPT]
    raise ValueError(agent)


def trace_events(transcript):
    """Extract tool inputs (paths, commands) from a JSON-lines transcript."""
    events = []
    for line in transcript.read_text(errors="replace").splitlines():
        try:
            event = json.loads(line)
        except ValueError:
            continue
        stack = [event]
        while stack:
            item = stack.pop()
            if isinstance(item, dict):
                if item.get("type") in ("tool_use", "tool") and ("input" in item or "state" in item):
                    events.append({"tool": item.get("name") or item.get("tool"),
                                   "input": item.get("input") or (item.get("state") or {}).get("input")})
                stack.extend(item.values())
            elif isinstance(item, list):
                stack.extend(item)
    return events


def usage(transcript):
    result = {}
    for line in transcript.read_text(errors="replace").splitlines():
        try:
            event = json.loads(line)
        except ValueError:
            continue
        if event.get("type") == "result":
            result = {"cost_usd": event.get("total_cost_usd"), "turns": event.get("num_turns"),
                      "is_error": event.get("is_error")}
    return result


def run_one(spec, out, trial, index, args):
    agent, model = spec.split(":", 1)
    name = "%s-%s-%s-r%d" % (trial, agent, model.replace("/", "_"), index)
    workspace = out / name / "workspace"
    transcript = out / name / "transcript.jsonl"
    if not workspace.exists():
        workspace.parent.mkdir(parents=True, exist_ok=True)
        subprocess.run(["python3", str(REPO / trial / "runner.py"), "prepare", "--workspace", str(workspace)],
                       check=True, capture_output=True)
    started = time.time()
    timed_out = False
    if not transcript.exists():
        with transcript.open("w") as sink:
            try:
                subprocess.run(command(agent, model), cwd=workspace, stdout=sink, stderr=subprocess.STDOUT,
                               timeout=args.timeout, env=dict(os.environ, XDEBUG_MODE="off"))
            except subprocess.TimeoutExpired:
                timed_out = True
    elapsed = round(time.time() - started)
    trace = out / name / "trace.jsonl"
    trace.write_text("".join(json.dumps(e) + "\n" for e in trace_events(transcript)))
    score_cmd = ["python3", str(REPO / trial / "runner.py"), "score", "--workspace", str(workspace),
                 "--phpstan", str(REPO / "vendor/bin/phpstan.phar"), "--runtime-php", args.runtime_php,
                 "--trace", str(trace)]
    scored = subprocess.run(score_cmd, capture_output=True, text=True)
    try:
        report = json.loads(scored.stdout)
    except ValueError:
        report = {"error": scored.stderr[-2000:]}
    (out / name / "score.json").write_text(json.dumps(report, indent=2))
    row = {"run": name, "trial": trial, "agent": agent, "model": model, "seconds": elapsed,
           "timed_out": timed_out, "tool_calls": len(trace_events(transcript)), **usage(transcript),
           "score": report.get("score"), "valid": report.get("valid_run"),
           "groups": {k: v.get("points") for k, v in report.get("groups", {}).items()}}
    with (out / "results.jsonl").open("a") as sink:
        sink.write(json.dumps(row) + "\n")
    print(json.dumps(row), flush=True)
    return row


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("agents", nargs="+", help="agent:model, e.g. claude:sonnet")
    parser.add_argument("--out", type=Path, required=True)
    parser.add_argument("--trial", default="trial3")
    parser.add_argument("--runs", type=int, default=1)
    parser.add_argument("--parallel", type=int, default=4)
    parser.add_argument("--timeout", type=int, default=2400)
    parser.add_argument("--runtime-php", default="/opt/homebrew/opt/php@7.4/bin/php")
    args = parser.parse_args()
    out = args.out.resolve()
    if REPO in out.parents or out == REPO:
        parser.error("--out must be outside the benchmark repository")
    out.mkdir(parents=True, exist_ok=True)
    jobs = [(spec, index) for index in range(1, args.runs + 1) for spec in args.agents]
    with ThreadPoolExecutor(args.parallel) as pool:
        list(pool.map(lambda job: run_one(job[0], out, args.trial, job[1], args), jobs))


if __name__ == "__main__":
    main()
