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
        $waiting = [];
        foreach ($this->edges as $edge) {
            $waiting[$edge['to']] = ($waiting[$edge['to']] ?? 0) + 1;
        }
        $queue = [$start];
        $ran = [];
        while ($queue !== []) {
            $name = array_shift($queue);
            ($this->nodes[$name])($context);
            $ran[] = $name;
            foreach ($this->edges as $edge) {
                if ($edge['from'] !== $name) {
                    continue;
                }
                if ($edge['when'] === null || ($edge['when'])($context)) {
                    if (--$waiting[$edge['to']] === 0) {
                        $queue[] = $edge['to'];
                    }
                }
            }
        }
        return $ran;
    }
}
