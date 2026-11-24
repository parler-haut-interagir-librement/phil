<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Application;

use Phil\FollowBundle\Application\Exception\FollowRefused;
use Phil\FollowBundle\Domain\Contract\FollowRequestRepository;
use Phil\FollowBundle\Domain\Contract\MutualRepository;
use Phil\FollowBundle\Domain\Exception\InvalidFollow;
use Phil\FollowBundle\Domain\Model\FollowRequest;
use Phil\FollowBundle\Domain\Status\FollowLevel;
use Phil\FollowBundle\Domain\Status\RequestKind;
use Phil\FollowBundle\Domain\ValueObject\EventPreferences;
use Phil\FollowBundle\Domain\ValueObject\MemberPair;
use Phil\NotificationsBundle\Application\Notifications;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

/**
 * The mutual relationships between members, beside one-way following, when the application turns them on: one member
 * asks, the other accepts; two members who ask each other are joined at once. A relationship ends only when one of
 * them ends it: stopping to follow the other, or ignoring them, leaves it as it is.
 */
final readonly class Mutuals
{
    public function __construct(
        private MutualRepository $mutuals,
        private FollowRequestRepository $requests,
        private FollowRequests $answers,
        private Notifications $notifications,
        private ClockInterface $clock,
        private bool $enabled = false,
        private FollowTypes $types = new FollowTypes(),
        private string $memberType = 'member',
    ) {
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Asks the other member to join a mutual relationship, or accepts the one they asked for.
     *
     * @throws FollowRefused when relationships are turned off or the member asks themselves
     * @throws InvalidFollow when a member is not an identifier
     */
    public function request(string $member, string $other): FollowOutcome
    {
        $pair = $this->pair($member, $other);

        if ($this->mutuals->exists($pair)) {
            return FollowOutcome::Done;
        }

        $reverse = $this->requests->findPending($other, $this->memberSubject($member), RequestKind::Mutual);

        if ($reverse instanceof FollowRequest) {
            $this->answers->accept($member, $reverse->id);

            return FollowOutcome::Done;
        }

        $request = new FollowRequest(Uuid::v7()->toRfc4122(), $member, $this->memberSubject($other), RequestKind::Mutual, $other, FollowLevel::Watching, new EventPreferences(), $this->clock->now());

        if ($this->requests->add($request)) {
            $this->notifications->send($this->types->requested, $other, $this->memberSubject($member), ['request' => $request->id, 'kind' => RequestKind::Mutual->value], $member);
        }

        return FollowOutcome::Requested;
    }

    /**
     * Ends the relationship, and withdraws the requests between the two that still wait. The follows stay.
     *
     * @return bool whether there was a relationship or a request
     *
     * @throws FollowRefused when relationships are turned off or the member names themselves
     * @throws InvalidFollow when a member is not an identifier
     */
    public function end(string $member, string $other): bool
    {
        $ended = $this->mutuals->remove($this->pair($member, $other));

        foreach ([[$member, $other], [$other, $member]] as [$requester, $approver]) {
            $pending = $this->requests->findPending($requester, $this->memberSubject($approver), RequestKind::Mutual);

            if ($pending instanceof FollowRequest) {
                $ended = $this->requests->remove($pending->id) || $ended;
            }
        }

        return $ended;
    }

    public function areMutual(string $member, string $other): bool
    {
        return $this->enabled && $member !== $other && $this->mutuals->exists(MemberPair::of($member, $other));
    }

    /**
     * Whether the member asked the other and waits for their answer.
     */
    public function pending(string $member, string $other): bool
    {
        return $this->enabled && $member !== $other && $this->requests->findPending($member, $this->memberSubject($other), RequestKind::Mutual) instanceof FollowRequest;
    }

    /**
     * The other members of the member's relationships, in byte order.
     *
     * @return list<string>
     */
    public function of(string $member, ?string $after = null, int $limit = 50): array
    {
        return $this->enabled ? $this->mutuals->of($member, $after, max(1, min($limit, Follows::MAX_PAGE))) : [];
    }

    /**
     * @throws FollowRefused when relationships are turned off or the member names themselves
     */
    private function pair(string $member, string $other): MemberPair
    {
        if (!$this->enabled) {
            throw FollowRefused::mutualDisabled();
        }

        if ($member === $other) {
            throw FollowRefused::self();
        }

        return MemberPair::of($member, $other);
    }

    private function memberSubject(string $member): SubjectReference
    {
        return new SubjectReference($this->memberType, $member);
    }
}
