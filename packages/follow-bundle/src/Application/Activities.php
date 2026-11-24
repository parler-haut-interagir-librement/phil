<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Application;

use Phil\FollowBundle\Application\Handler\FanOutActivityHandler;
use Phil\FollowBundle\Application\Message\FanOutActivity;
use Phil\FollowBundle\Domain\Contract\ActivityRepository;
use Phil\FollowBundle\Domain\Exception\InvalidFollow;
use Phil\FollowBundle\Domain\Model\Activity;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * What the application calls when something new happens on a subject: the activity is kept, then sent to the
 * followers of the subject and of its parents by `FanOutActivity` messages, which carry its identifier only. Published
 * again under the same identifier — a retry, a second listener —, it is kept once and sent to each follower once.
 *
 * ```php
 * $activities->publish(new SubjectReference('thread', $thread->id), 'reply.created', $author->id, $mentioned, 'reply:'.$reply->id);
 * ```
 */
final readonly class Activities
{
    public function __construct(
        private ActivityRepository $activities,
        private FanOutActivityHandler $handler,
        private ClockInterface $clock,
        private ?MessageBusInterface $bus = null,
    ) {
    }

    /**
     * @param list<string> $mentions the members the activity mentions, who hear of it at the mentions level
     * @param string|null  $id       the application's own identifier of the activity, a UUID v7 otherwise
     *
     * @return string the identifier of the activity
     *
     * @throws InvalidFollow when an identifier or the event is of the wrong shape
     */
    public function publish(SubjectReference $subject, string $event, ?string $actor = null, array $mentions = [], ?string $id = null): string
    {
        $activity = new Activity($id ?? Uuid::v7()->toRfc4122(), $subject, $event, $actor, $mentions, $this->clock->now());
        $this->activities->record($activity);
        $message = new FanOutActivity($activity->id);

        if ($this->bus instanceof MessageBusInterface) {
            $this->bus->dispatch($message);
        } else {
            ($this->handler)($message);
        }

        return $activity->id;
    }
}
