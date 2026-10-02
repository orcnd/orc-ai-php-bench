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

    /** @param array<string, int|string> $payload */
    public function publish(string $type, array $payload): void
    {
        $this->store->transaction('outbox', function () use ($type, $payload): void {
            /** @var list<array{type: string, payload: array<string, int|string>}> $events */
            $events = $this->store->get('outbox', []);
            $events[] = ['type' => $type, 'payload' => $payload];
            $this->store->put('outbox', $events);
        });
    }

    /** @return list<array{type: string, payload: array<string, int|string>}> */
    public function events(): array
    {
        /** @var list<array{type: string, payload: array<string, int|string>}> $events */
        $events = $this->store->get('outbox', []);
        return $events;
    }
}
