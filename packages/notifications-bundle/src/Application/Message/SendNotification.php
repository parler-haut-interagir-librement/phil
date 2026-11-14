<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Application\Message;

use Phil\NotificationsBundle\Domain\Exception\InvalidNotification;
use Phil\NotificationsBundle\Domain\Identifiers;
use Phil\NotificationsBundle\Domain\ValueObject\NotificationData;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;

/**
 * A notification to deliver, made of identifiers only: its type, its recipients or the audience they belong to, its
 * subject, its data, its actor and the site it comes from. Sent to many, it is split into one message per recipient
 * when a bus carries it, so that a failure is retried for that recipient alone; the broadcast channels are posted to
 * by the first message only.
 */
final readonly class SendNotification
{
    public const int MAX_RECIPIENTS = 1000;

    /** @var list<string> */
    public array $recipients;

    /** @var array<string, int|string> */
    public array $data;

    /**
     * @param list<string>            $recipients
     * @param array<array-key, mixed> $data
     *
     * @throws InvalidNotification when an identifier is of the wrong shape, or there are too many recipients
     */
    public function __construct(
        public string $type,
        array $recipients = [],
        public ?string $audience = null,
        public ?string $subjectType = null,
        public ?string $subjectId = null,
        array $data = [],
        public ?string $actor = null,
        public ?string $origin = null,
        public bool $broadcast = true,
    ) {
        Identifiers::key($type, 'notification type');

        if (\count($recipients) > self::MAX_RECIPIENTS) {
            throw InvalidNotification::because(\sprintf('A notification names %d recipients at most; send it to an audience instead.', self::MAX_RECIPIENTS));
        }

        $this->recipients = array_values(array_unique(array_map(static fn (string $id): string => Identifiers::id($id, 'recipient'), $recipients)));

        if (null !== $audience) {
            Identifiers::id($audience, 'audience');
        }

        $this->subject();
        $this->data = NotificationData::from($data)->values;

        if (null !== $actor) {
            Identifiers::id($actor, 'actor');
        }

        if (null !== $origin) {
            Identifiers::key($origin, 'origin');
        }
    }

    /**
     * @throws InvalidNotification when only one half of the subject is given, or a half is of the wrong shape
     */
    public function subject(): ?SubjectReference
    {
        if (null === $this->subjectType && null === $this->subjectId) {
            return null;
        }

        if (null === $this->subjectType || null === $this->subjectId) {
            throw InvalidNotification::because('The subject of a notification has both a type and an identifier.');
        }

        return new SubjectReference($this->subjectType, $this->subjectId);
    }

    /**
     * The same notification for one recipient, without the broadcast.
     */
    public function for(string $recipient): self
    {
        return new self($this->type, [$recipient], null, $this->subjectType, $this->subjectId, $this->data, $this->actor, $this->origin, false);
    }
}
