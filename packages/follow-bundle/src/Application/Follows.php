<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Application;

use Phil\FollowBundle\Application\Contract\FollowAccess;
use Phil\FollowBundle\Application\Contract\FollowApproval;
use Phil\FollowBundle\Application\Exception\FollowRefused;
use Phil\FollowBundle\Domain\Contract\DeliveryRepository;
use Phil\FollowBundle\Domain\Contract\FollowRepository;
use Phil\FollowBundle\Domain\Contract\FollowRequestRepository;
use Phil\FollowBundle\Domain\Exception\InvalidFollow;
use Phil\FollowBundle\Domain\Identifier;
use Phil\FollowBundle\Domain\Model\Follow;
use Phil\FollowBundle\Domain\Model\FollowRequest;
use Phil\FollowBundle\Domain\Status\FollowLevel;
use Phil\FollowBundle\Domain\Status\RequestKind;
use Phil\FollowBundle\Domain\ValueObject\EventPreferences;
use Phil\NotificationsBundle\Application\Notifications;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

/**
 * What a member does with a subject: follow it at a level — watching, mentions only, ignoring, or custom by event —,
 * change that level, stop following it, and see it. A subject the application approves becomes a request to its
 * approver instead; ignoring needs no approval. Stopping to follow a member leaves a mutual relationship with them as it is.
 *
 * ```php
 * $follows->follow($member, new SubjectReference('thread', $thread->id));
 * $follows->follow($member, new SubjectReference('forum', '7'), FollowLevel::Mentions);
 * ```
 */
final readonly class Follows
{
    public const int MAX_PAGE = 200;

    public function __construct(
        private FollowRepository $follows,
        private FollowRequestRepository $requests,
        private DeliveryRepository $deliveries,
        private FollowApproval $approval,
        private FollowAccess $access,
        private SubjectChain $chain,
        private Notifications $notifications,
        private ClockInterface $clock,
        private FollowTypes $types = new FollowTypes(),
        private string $memberType = 'member',
    ) {
    }

    /**
     * Follows the subject at the level, or changes the level of the member's follow.
     *
     * @throws InvalidFollow when the member is not an identifier
     * @throws FollowRefused when the member follows themselves or may not follow the subject
     */
    public function follow(string $member, SubjectReference $subject, FollowLevel $level = FollowLevel::Watching, EventPreferences $events = new EventPreferences()): FollowOutcome
    {
        Identifier::id($member, 'member');

        if ($this->isMember($subject) && $subject->id === $member) {
            throw FollowRefused::self();
        }

        $existing = $this->follows->find($member, $subject);

        // A member who follows already changes their level freely; one who ignored the subject asks like anyone else.
        if ($existing instanceof Follow && ($existing->level()->follows() || !$level->follows())) {
            if ($existing->change($level, $events)) {
                $this->follows->save($existing);
            }

            return FollowOutcome::Done;
        }

        if ($level->follows()) {
            if ([] === $this->access->filter($subject, [$member])) {
                throw FollowRefused::noAccess();
            }

            $approver = $this->approval->approverOf($subject, $member);

            if (null !== $approver && $approver !== $member) {
                $this->request($member, $subject, $approver, $level, $events);

                return FollowOutcome::Requested;
            }
        }

        if ($existing instanceof Follow) {
            $existing->change($level, $events);
            $this->follows->save($existing);
        } else {
            $this->follows->save(new Follow($member, $subject, $level, $events, $this->clock->now()));
        }

        if ($level->follows() && $this->isMember($subject)) {
            $this->notifications->send($this->types->followed, $subject->id, $this->memberSubject($member), [], $member);
        }

        return FollowOutcome::Done;
    }

    /**
     * Stops following the subject, and withdraws a request to follow it that still waits. A mutual relationship with a
     * followed member stays.
     *
     * @return bool whether there was a follow or a request
     */
    public function unfollow(string $member, SubjectReference $subject): bool
    {
        $removed = $this->follows->remove($member, $subject);
        $pending = $this->requests->findPending($member, $subject, RequestKind::Follow);

        if ($pending instanceof FollowRequest) {
            return $this->requests->remove($pending->id) || $removed;
        }

        return $removed;
    }

    /**
     * The member's own follow of the subject, else the nearest parent's, and whether a request waits.
     */
    public function state(string $member, SubjectReference $subject): FollowState
    {
        $chain = $this->chain->of($subject);
        $own = $this->follows->find($member, $subject);
        $inherited = null;

        if (!$own instanceof Follow) {
            foreach (\array_slice($chain, 1) as $parent) {
                $inherited = $this->follows->find($member, $parent);

                if ($inherited instanceof Follow) {
                    break;
                }
            }
        }

        return new FollowState($own, $inherited, $this->requests->findPending($member, $subject, RequestKind::Follow) instanceof FollowRequest);
    }

    /**
     * A page of the member's own follows, every level, by subject: what a site reads to compose a member's home page.
     *
     * @param string|null $after the key, `type:id`, of the last subject of the page before
     *
     * @return list<Follow>
     */
    public function following(string $member, ?string $after = null, int $limit = 50): array
    {
        return $this->follows->ofMember($member, $after, max(1, min($limit, self::MAX_PAGE)));
    }

    /**
     * The member saw the subject: its next activity goes out on every channel again.
     */
    public function seen(string $member, SubjectReference $subject): void
    {
        $this->deliveries->seen($member, $subject);
    }

    private function request(string $member, SubjectReference $subject, string $approver, FollowLevel $level, EventPreferences $events): void
    {
        $request = new FollowRequest(Uuid::v7()->toRfc4122(), $member, $subject, RequestKind::Follow, $approver, $level, FollowLevel::Custom === $level ? $events : new EventPreferences(), $this->clock->now());

        if ($this->requests->add($request)) {
            $this->notifications->send($this->types->requested, $approver, $this->memberSubject($member), ['request' => $request->id, 'kind' => RequestKind::Follow->value], $member);
        }
    }

    private function isMember(SubjectReference $subject): bool
    {
        return $subject->type === $this->memberType;
    }

    private function memberSubject(string $member): SubjectReference
    {
        return new SubjectReference($this->memberType, $member);
    }
}
