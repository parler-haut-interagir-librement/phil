<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Infrastructure\Messenger;

use Override;
use Phil\AuditBundle\Application\Contract\AuditNotifier;
use Phil\AuditBundle\Application\Message\AuditRecorded;
use Phil\AuditBundle\Domain\Chain\AuditRow;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Each recorded row dispatched on a Messenger bus as an `AuditRecorded` message: the segment and the sequence only.
 * Routed to an asynchronous transport, the dashboards are fed without slowing the request that recorded it.
 */
final readonly class MessengerAuditNotifier implements AuditNotifier
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    #[Override]
    public function recorded(AuditRow $row): void
    {
        $this->bus->dispatch(new AuditRecorded($row->segment, $row->sequence));
    }
}
