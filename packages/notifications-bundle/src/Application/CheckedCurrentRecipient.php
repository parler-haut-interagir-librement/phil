<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Application;

use Override;
use Phil\NotificationsBundle\Application\Contract\CurrentRecipient;
use Phil\NotificationsBundle\Domain\Exception\InvalidNotification;
use Phil\NotificationsBundle\Domain\Identifiers;

/**
 * The application's current recipient, checked: a member is named by a stable identifier, the one notifications are
 * sent to, and never by an email address, which changes and would end up in every notification's row. An identifier
 * that looks like an address, or that is not an identifier, is refused as soon as it is read.
 */
final readonly class CheckedCurrentRecipient implements CurrentRecipient
{
    public function __construct(
        private CurrentRecipient $inner,
    ) {
    }

    /**
     * @throws InvalidNotification when the application names the recipient by an email address, or by something that
     *                             is not an identifier
     */
    #[Override]
    public function id(): ?string
    {
        $id = $this->inner->id();

        if (null === $id) {
            return null;
        }

        if (str_contains($id, '@')) {
            throw InvalidNotification::because('The current recipient looks like an email address: name members by a stable identifier, such as the id of their account, in the CurrentRecipient service of phil_notifications.current_recipient.');
        }

        return Identifiers::id($id, 'current recipient');
    }
}
