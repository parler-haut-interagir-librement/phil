<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Application\Handler;

use DateInterval;
use Phil\FollowBundle\Application\Contract\FollowAccess;
use Phil\FollowBundle\Application\FollowTypes;
use Phil\FollowBundle\Application\Message\FanOutActivity;
use Phil\FollowBundle\Application\RecipientResolver;
use Phil\FollowBundle\Application\SubjectChain;
use Phil\FollowBundle\Domain\Contract\ActivityRepository;
use Phil\FollowBundle\Domain\Contract\DeliveryRepository;
use Phil\FollowBundle\Domain\Contract\FollowRepository;
use Phil\FollowBundle\Domain\Exception\InvalidFollow;
use Phil\FollowBundle\Domain\Model\Activity;
use Phil\FollowBundle\Domain\Model\Follow;
use Phil\NotificationsBundle\Application\Notifications;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;

/**
 * Sends an activity to the followers of its subject and of its parents, a page of members at a time:
 *
 * 1. the follows of the page's members on the chain, read in two queries, decide who hears of it;
 * 2. the application's access drops the members who may no longer see the subject;
 * 3. the delivery to each member is claimed, so that a retried page, or a page read twice, sends to no one twice;
 * 4. the members who were notified about the subject on every channel and have not seen it since get it in their
 *    centre only, the others on every channel of the type, in one call each to the notifications, which split it by
 *    recipient on the bus; a call that fails gives its claims back for the next attempt.
 *
 * With a bus, a full page ends with the message of the next one, so that each message does a bounded amount of work;
 * without one, the pages follow each other here. The activity is read again from its identifier: one purged in the
 * meantime is sent to no one.
 */
final readonly class FanOutActivityHandler
{
    public const int MAX_BATCH = 1000;

    /**
     * @param int $resendAfter how long, in seconds, a notification sent on every channel holds the next ones to the centre
     *
     * @throws InvalidFollow when the page size or the delay is out of range
     */
    public function __construct(
        private ActivityRepository $activities,
        private FollowRepository $follows,
        private DeliveryRepository $deliveries,
        private SubjectChain $chain,
        private FollowAccess $access,
        private Notifications $notifications,
        private ClockInterface $clock,
        private FollowTypes $types = new FollowTypes(),
        private int $batchSize = 500,
        private bool $onceUntilSeen = true,
        private int $resendAfter = 604800,
        private ?MessageBusInterface $bus = null,
    ) {
        if ($batchSize < 1 || $batchSize > self::MAX_BATCH) {
            throw InvalidFollow::because(\sprintf('A page of followers holds 1 to %d members.', self::MAX_BATCH));
        }

        if ($resendAfter < 0) {
            throw InvalidFollow::because('The delay before a notification goes out on every channel again is 0 seconds or more.');
        }
    }

    public function __invoke(FanOutActivity $message): void
    {
        $activity = $this->activities->find($message->activity);

        if (!$activity instanceof Activity) {
            return;
        }

        $chain = $this->chain->of($activity->subject);
        $after = $message->after;

        do {
            $follows = $this->follows->candidates($chain, $after, $this->batchSize);
            $members = array_values(array_unique(array_map(static fn (Follow $follow): string => $follow->member, $follows)));

            if ([] === $members) {
                return;
            }

            $this->deliver($activity, new RecipientResolver()->resolve($activity, $chain, $follows));
            $after = $members[\count($members) - 1];
            $full = \count($members) >= $this->batchSize;

            if ($full && $this->bus instanceof MessageBusInterface) {
                $this->bus->dispatch(new FanOutActivity($activity->id, $after));

                return;
            }
        } while ($full);
    }

    /**
     * @param list<string> $recipients
     */
    private function deliver(Activity $activity, array $recipients): void
    {
        $allowed = [] === $recipients ? [] : $this->access->filter($activity->subject, $recipients);
        $now = $this->clock->now();
        $claimed = [] === $allowed ? [] : $this->deliveries->claim($activity->id, $allowed, $now);

        if ([] === $claimed) {
            return;
        }

        $quiet = $this->onceUntilSeen
            ? $this->deliveries->unseen($activity->subject, $claimed, $now->sub(new DateInterval('PT'.$this->resendAfter.'S')))
            : [];
        $loud = array_values(array_diff($claimed, $quiet));

        $this->send($activity, $this->types->activity, $loud);

        if ($this->onceUntilSeen && [] !== $loud) {
            $this->deliveries->markNotified($activity->subject, $loud, $now);
        }

        $this->send($activity, $this->types->quiet, $quiet);
    }

    /**
     * @param list<string> $recipients
     *
     * @throws Throwable what the notifications threw, the claims given back
     */
    private function send(Activity $activity, string $type, array $recipients): void
    {
        if ([] === $recipients) {
            return;
        }

        try {
            $this->notifications->send($type, $recipients, $activity->subject, ['event' => $activity->event, 'activity' => $activity->id], $activity->actor, dedupeKey: 'follow:'.$activity->id);
        } catch (Throwable $throwable) {
            $this->deliveries->release($activity->id, $recipients);

            throw $throwable;
        }
    }
}
