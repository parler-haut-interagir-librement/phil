<?php

declare(strict_types=1);

namespace Phil\AccessLinks\Messenger;

use Phil\AccessLinks\AccessLinks;
use Phil\AccessLinks\Contract\ReminderSender;
use Phil\AccessLinks\Exception\UnknownInvitation;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Sends the reminder a message asks for, if the invitation still has no answer and its reminder is still due: a
 * message handled twice, or after the recipient answered, sends nothing. An invitation deleted since is ignored.
 */
#[AsMessageHandler]
final readonly class SendInvitationReminderHandler
{
    public function __construct(
        private AccessLinks $links,
        private ReminderSender $sender,
    ) {
    }

    public function __invoke(SendInvitationReminder $message): bool
    {
        try {
            return $this->links->remind($message->invitationId, $this->sender);
        } catch (UnknownInvitation) {
            return false;
        }
    }
}
