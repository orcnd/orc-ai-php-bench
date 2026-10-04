<?php
declare(strict_types=1);
namespace App\Workflow;

/** Approval workflows: a DAG of steps with optional edge conditions. See docs/WORKFLOW.md. */
final class Graph
{
    /** @var array<string, callable(\ArrayObject<string, mixed>): void> */
    private $nodes = [];
    /** @var list<array{from: string, to: string, when: (callable(\ArrayObject<string, mixed>): bool)|null}> */
    private $edges = [];

    /** @param callable(\ArrayObject<string, mixed>): void $step */
    public function addNode(string $name, callable $step): void
    {
        $this->nodes[$name] = $step;
    }

    /** @param (callable(\ArrayObject<string, mixed>): bool)|null $when */
    public function addEdge(string $from, string $to, ?callable $when = null): void
    {
        $this->edges[] = ['from' => $from, 'to' => $to, 'when' => $when];
    }

    /**
     * @param \ArrayObject<string, mixed> $context
     * @return list<string> the steps that ran, in order
     */
    public function run(string $start, \ArrayObject $context): array
    {
        // Only steps reachable from the start take part; edges from elsewhere never resolve.
        $reachable = [$start => true];
        for ($grew = true; $grew;) {
            $grew = false;
            foreach ($this->edges as $edge) {
                if (isset($reachable[$edge['from']]) && !isset($reachable[$edge['to']])) {
                    $reachable[$edge['to']] = $grew = true;
                }
            }
        }
        $unresolved = [];
        $taken = [];
        foreach ($this->edges as $edge) {
            if (isset($reachable[$edge['from']])) {
                $unresolved[$edge['to']] = ($unresolved[$edge['to']] ?? 0) + 1;
            }
        }
        // Queue entries: [step, runs?]. A step whose incoming edges were all skipped is itself
        // skipped, and its outgoing edges count as skipped so that joins further down resolve.
        $queue = [[$start, true]];
        $ran = [];
        while ($queue !== []) {
            [$name, $runs] = array_shift($queue);
            if ($runs) {
                ($this->nodes[$name])($context);
                $ran[] = $name;
            }
            foreach ($this->edges as $edge) {
                if ($edge['from'] !== $name) {
                    continue;
                }
                $to = $edge['to'];
                if ($runs && ($edge['when'] === null || ($edge['when'])($context))) {
                    $taken[$to] = true;
                }
                if (--$unresolved[$to] === 0) {
                    $queue[] = [$to, isset($taken[$to])];
                }
            }
        }
        return $ran;
    }
}
