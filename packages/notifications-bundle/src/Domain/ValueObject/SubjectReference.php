<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Domain\ValueObject;

use Phil\NotificationsBundle\Domain\Exception\InvalidNotification;
use Phil\NotificationsBundle\Domain\Identifiers;

/**
 * What a notification is about, by reference: a kind (`comment`, `event`) and the identifier of the record. Its label and
 * its URL are resolved when the notification is shown, never stored.
 */
final readonly class SubjectReference
{
    public string $type;

    public string $id;

    /**
     * @throws InvalidNotification when the kind is not a key or the identifier is not one
     */
    public function __construct(string $type, string $id)
    {
        $this->type = Identifiers::key($type, 'subject type');
        $this->id = Identifiers::id($id, 'subject identifier');
    }

    /**
     * The reference in one string, `type:id`, by which similar notifications are grouped.
     */
    public function key(): string
    {
        return $this->type.':'.$this->id;
    }
}
