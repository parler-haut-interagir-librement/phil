<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Application;

use Phil\FollowBundle\Application\Exception\UnknownFollowRequest;
use Phil\FollowBundle\Domain\Contract\FollowRepository;
use Phil\FollowBundle\Domain\Contract\FollowRequestRepository;
use Phil\FollowBundle\Domain\Contract\MutualRepository;
use Phil\FollowBundle\Domain\Model\Follow;
use Phil\FollowBundle\Domain\Model\FollowRequest;
use Phil\FollowBundle\Domain\Status\FollowLevel;
use Phil\FollowBundle\Domain\Status\RequestKind;
use Phil\FollowBundle\Domain\ValueObject\EventPreferences;
use Phil\FollowBundle\Domain\ValueObject\MemberPair;
use Phil\NotificationsBundle\Application\Notifications;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;
use Psr\Clock\ClockInterface;

/**
 * The requests a member answers — `Accept` or `Reject`, the words of ActivityPub — and those they sent. Accepting a
 * request to follow makes the follow at the level asked; accepting a mutual relationship makes it and, when so
 * configured, each member follows the other unless they chose a level already. Rejecting deletes the request without a
 * word to the requester.
 */
final readonly class FollowRequests
{
    public function __construct(
        private FollowRequestRepository $requests,
        private FollowRepository $follows,
        private MutualRepository $mutuals,
        private Notifications $notifications,
        private ClockInterface $clock,
        private FollowTypes $types = new FollowTypes(),
        private string $memberType = 'member',
        private bool $followEachOther = true,
    ) {
    }

    /**
     * The requests the member has to answer, oldest first.
     *
     * @return list<FollowRequest>
     */
    public function pendingFor(string $approver, ?string $after = null, int $limit = 50): array
    {
        return $this->requests->pendingFor($approver, $after, max(1, min($limit, Follows::MAX_PAGE)));
    }

    /**
     * The requests the member sent that still wait, oldest first.
     *
     * @return list<FollowRequest>
     */
    public function sentBy(string $requester, ?string $after = null, int $limit = 50): array
    {
        return $this->requests->sentBy($requester, $after, max(1, min($limit, Follows::MAX_PAGE)));
    }

    /**
     * @throws UnknownFollowRequest when no such request waits for the approver
     */
    public function accept(string $approver, string $id): FollowRequest
    {
        $request = $this->claim($approver, $id);
        $now = $this->clock->now();

        if (RequestKind::Mutual === $request->kind) {
            $this->mutuals->add(MemberPair::of($request->requester, $request->approver), $now);

            if ($this->followEachOther) {
                $this->followIfUndecided($request->requester, $this->memberSubject($request->approver), FollowLevel::Watching, new EventPreferences());
                $this->followIfUndecided($request->approver, $this->memberSubject($request->requester), FollowLevel::Watching, new EventPreferences());
            }
        } else {
            $existing = $this->follows->find($request->requester, $request->subject);

            if ($existing instanceof Follow) {
                $existing->change($request->level, $request->events);
                $this->follows->save($existing);
            } else {
                $this->follows->save(new Follow($request->requester, $request->subject, $request->level, $request->events, $now));
            }
        }

        $this->notifications->send($this->types->accepted, $request->requester, $this->memberSubject($request->approver), ['request' => $request->id, 'kind' => $request->kind->value], $request->approver);

        return $request;
    }

    /**
     * @throws UnknownFollowRequest when no such request waits for the approver
     */
    public function reject(string $approver, string $id): void
    {
        $this->claim($approver, $id);
    }

    /**
     * Withdraws a request the member sent.
     *
     * @throws UnknownFollowRequest when the member sent no such request
     */
    public function withdraw(string $requester, string $id): void
    {
        $request = $this->requests->find($id);

        if (!$request instanceof FollowRequest || $request->requester !== $requester || !$this->requests->remove($id)) {
            throw UnknownFollowRequest::create();
        }
    }

    /**
     * Takes the request out of the waiting ones, once: of two answers at once, one finds it gone.
     *
     * @throws UnknownFollowRequest when no such request waits for the approver
     */
    private function claim(string $approver, string $id): FollowRequest
    {
        $request = $this->requests->find($id);

        if (!$request instanceof FollowRequest || $request->approver !== $approver || !$this->requests->remove($id)) {
            throw UnknownFollowRequest::create();
        }

        return $request;
    }

    private function followIfUndecided(string $member, SubjectReference $subject, FollowLevel $level, EventPreferences $events): void
    {
        if (!$this->follows->find($member, $subject) instanceof Follow) {
            $this->follows->save(new Follow($member, $subject, $level, $events, $this->clock->now()));
        }
    }

    private function memberSubject(string $member): SubjectReference
    {
        return new SubjectReference($this->memberType, $member);
    }
}
