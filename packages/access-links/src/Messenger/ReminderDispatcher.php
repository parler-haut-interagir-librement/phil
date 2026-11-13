<?php

declare(strict_types=1);

namespace Phil\AccessLinks\Messenger;

use Phil\AccessLinks\AccessLinks;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Dispatches one message per invitation whose reminder is due, for a scheduled task or a console command to run as
 * often as reminders should leave. The handler claims each reminder before sending it, so that running the dispatcher
 * twice, or on two servers, sends each reminder once.
 */
final readonly class ReminderDispatcher
{
    public function __construct(
        private AccessLinks $links,
        private MessageBusInterface $bus,
    ) {
    }

    /**
     * @param positive-int $limit the most messages dispatched in one run
     *
     * @return int how many were dispatched
     */
    public function dispatchDue(int $limit = 100): int
    {
        $due = $this->links->dueReminders($limit);

        foreach ($due as $invitationId) {
            $this->bus->dispatch(new SendInvitationReminder($invitationId));
        }

        return \count($due);
    }
}
