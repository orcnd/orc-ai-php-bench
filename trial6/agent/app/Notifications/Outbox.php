<?php
declare(strict_types=1);
namespace App\Notifications;

use App\Storage\FileStore;

/**
 * CRM-owned outbox. The e-mail worker sends one customer e-mail per event.
 * It cannot deduplicate (see docs/OPERATIONS.md).
 */
final class Outbox
{
    private FileStore $store;

    public function __construct(FileStore $store)
    {
        $this->store = $store;
    }

    /**
     * @param array<string, int|string> $payload
     * @throws OutboxUnavailableException during CRM maintenance windows
     */
    public function publish(string $type, array $payload): void
    {
        $this->store->transaction('outbox', function () use ($type, $payload): void {
            if ($this->store->get('outbox-down', false) === true) {
                throw new OutboxUnavailableException('CRM outbox is in maintenance');
            }
            /** @var list<array{type: string, payload: array<string, int|string>}> $events */
            $events = $this->store->get('outbox', []);
            $events[] = ['type' => $type, 'payload' => $payload];
            $this->store->put('outbox', $events);
        });
    }

    /** CRM tooling: start a maintenance window. */
    public function takeDown(): void
    {
        $this->store->put('outbox-down', true);
    }

    public function restore(): void
    {
        $this->store->put('outbox-down', false);
    }

    /** @return list<array{type: string, payload: array<string, int|string>}> */
    public function events(): array
    {
        /** @var list<array{type: string, payload: array<string, int|string>}> $events */
        $events = $this->store->get('outbox', []);
        return $events;
    }
}
