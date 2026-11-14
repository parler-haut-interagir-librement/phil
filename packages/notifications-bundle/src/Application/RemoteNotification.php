<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Application;

use Phil\NotificationsBundle\Application\Message\SendNotification;
use Phil\NotificationsBundle\Domain\Exception\InvalidNotification;
use Phil\NotificationsBundle\Domain\Identifiers;
use Phil\NotificationsBundle\Domain\ValueObject\NotificationData;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;

/**
 * A notification another site of a network sent, read from the `data` of its signed payload — references only, as
 * every notification:
 *
 * ```json
 * {"origin": "events", "notification": "comment.replied", "recipients": ["42"],
 *  "subject": {"type": "comment", "id": "981"}, "data": {"event": 7}, "actor": "17"}
 * ```
 */
final readonly class RemoteNotification
{
    /**
     * @param non-empty-list<string> $recipients
     */
    private function __construct(
        public string $origin,
        public string $type,
        public array $recipients,
        public ?SubjectReference $subject,
        public NotificationData $data,
        public ?string $actor,
    ) {
    }

    /**
     * @param array<array-key, mixed> $payload
     *
     * @throws InvalidNotification when a member is missing or of the wrong shape
     */
    public static function fromPayload(array $payload): self
    {
        $recipients = $payload['recipients'] ?? null;

        if (!\is_array($recipients) || [] === $recipients || !array_is_list($recipients) || \count($recipients) > SendNotification::MAX_RECIPIENTS) {
            throw InvalidNotification::because(\sprintf('A remote notification names from 1 to %d recipients in a list.', SendNotification::MAX_RECIPIENTS));
        }

        $subject = $payload['subject'] ?? null;
        $data = $payload['data'] ?? [];

        if (null !== $subject && !\is_array($subject)) {
            throw InvalidNotification::because('The subject of a remote notification is an object with a "type" and an "id".');
        }

        if (!\is_array($data)) {
            throw InvalidNotification::because('The data of a remote notification is an object.');
        }

        return new self(
            Identifiers::key(self::string($payload, 'origin'), 'origin'),
            Identifiers::key(self::string($payload, 'notification'), 'notification type'),
            array_map(static fn (mixed $recipient): string => Identifiers::id(\is_string($recipient) ? $recipient : '', 'recipient'), $recipients),
            null === $subject ? null : new SubjectReference(self::string($subject, 'type'), self::string($subject, 'id')),
            NotificationData::from($data),
            null === ($payload['actor'] ?? null) ? null : Identifiers::id(self::string($payload, 'actor'), 'actor'),
        );
    }

    /**
     * @param array<array-key, mixed> $payload
     *
     * @throws InvalidNotification when the member is not a string
     */
    private static function string(array $payload, string $member): string
    {
        $value = $payload[$member] ?? null;

        if (!\is_string($value)) {
            throw InvalidNotification::because(\sprintf('The member "%s" of a remote notification is a string.', $member));
        }

        return $value;
    }
}
