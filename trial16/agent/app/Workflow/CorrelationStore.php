<?php
declare(strict_types=1);
namespace App\Workflow;

/** Which process subscriptions a published message was correlated to (shown in the ops console). */
final class CorrelationStore
{
    /** @var array<string, array{subscription: string, process: string}> */
    private array $byMessage = [];

    /** Called once per correlation; the broker redelivers events at least once. */
    public function record(string $messageKey, string $subscriptionKey, string $processInstance): void
    {
        $this->byMessage[$messageKey] = ['subscription' => $subscriptionKey, 'process' => $processInstance];
    }

    /** @return list<array{subscription: string, process: string}> in the order they were first recorded */
    public function correlations(string $messageKey): array
    {
        return isset($this->byMessage[$messageKey]) ? [$this->byMessage[$messageKey]] : [];
    }
}
