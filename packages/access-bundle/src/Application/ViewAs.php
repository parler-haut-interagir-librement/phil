<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Application;

use Phil\AccessBundle\Application\Contract\CurrentMember;
use Phil\AccessBundle\Application\Contract\ViewAsStore;
use Phil\AccessBundle\Application\Event\ViewAsStarted;
use Phil\AccessBundle\Application\Event\ViewAsStopped;
use Phil\AccessBundle\Application\Exception\ViewAsRefused;
use Phil\AccessBundle\Domain\Status\ViewAsEnd;
use Phil\AccessBundle\Domain\ValueObject\MemberId;
use Phil\ResourcePermissions\Exception\TraversalBudgetExceeded;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

/**
 * "View as this member": an admin of the space sees the site as a member of that same space sees it, read-only,
 * without taking their session. Every start, stop and refusal is logged, by identifiers alone, and starts and stops
 * are dispatched as events. The member viewed is forgotten — removed from the store, logged and dispatched — as soon
 * as the admin is no longer one, or the member no longer a member of the space.
 *
 * The checks of this bundle then answer for the member viewed, and never above the narrowest sharing level. What does
 * not ask them — a role, another voter, a controller or a service of the application — still sees the admin: the
 * guard of the bundle refuses the requests that change something during the view, and `refuseWhileViewing()` lets a
 * service refuse on its own.
 */
final readonly class ViewAs
{
    public function __construct(
        private ViewAsStore $store,
        private CurrentMember $members,
        private Spaces $spaces,
        private ?LoggerInterface $logger = null,
        private ?EventDispatcherInterface $events = null,
    ) {
    }

    /**
     * @throws ViewAsRefused when nobody is signed in, the member is no admin, names themselves, or names someone who
     *                       is no member of the space
     */
    public function start(MemberId $member): void
    {
        $admin = $this->members->current() ?? throw ViewAsRefused::signedOut();

        if (!$this->isAdmin($admin)) {
            throw ViewAsRefused::notAnAdmin();
        }

        if ($admin->equals($member)) {
            throw ViewAsRefused::self();
        }

        if (!$this->isMember($member)) {
            $this->logger?->warning('{admin} was refused viewing the site as {member}, who is no member of the space.', ['admin' => $admin->value, 'member' => $member->value]);

            throw ViewAsRefused::notAMemberOfTheSpace();
        }

        $this->store->set($member);
        $this->logger?->notice('{admin} started viewing the site as {member}.', ['admin' => $admin->value, 'member' => $member->value]);
        $this->events?->dispatch(new ViewAsStarted($admin->value, $member->value));
    }

    public function stop(): void
    {
        $member = $this->store->get();

        if ($member instanceof MemberId) {
            $this->end($this->members->current(), $member, ViewAsEnd::Stopped);
        }
    }

    /**
     * The member the current admin views the site as, or null. A view whose admin is no longer one, or whose member is
     * no longer a member of the space, ends here.
     */
    public function member(): ?MemberId
    {
        $member = $this->store->get();

        if (!$member instanceof MemberId) {
            return null;
        }

        $admin = $this->members->current();

        if (!$admin instanceof MemberId || !$this->isAdmin($admin)) {
            $this->end($admin, $member, ViewAsEnd::AdminRightLost);

            return null;
        }

        if (!$this->isMember($member)) {
            $this->end($admin, $member, ViewAsEnd::MemberLeftSpace);

            return null;
        }

        return $member;
    }

    /**
     * Whether the current member may view the site as another.
     */
    public function isAllowed(): bool
    {
        $admin = $this->members->current();

        return $admin instanceof MemberId && $this->isAdmin($admin);
    }

    /**
     * Refuses a change while the current admin views the site as a member: for a service, a command handler or a
     * controller the guard does not cover.
     *
     * @throws ViewAsRefused while a view is under way
     */
    public function refuseWhileViewing(): void
    {
        if ($this->member() instanceof MemberId) {
            throw ViewAsRefused::readOnly();
        }
    }

    private function end(?MemberId $admin, MemberId $member, ViewAsEnd $reason): void
    {
        $this->store->set(null);
        $context = ['admin' => $admin->value ?? '', 'member' => $member->value];

        match ($reason) {
            ViewAsEnd::Stopped => $this->logger?->notice('{admin} stopped viewing the site as {member}.', $context),
            ViewAsEnd::AdminRightLost => $this->logger?->notice('The view of {member} by {admin} ended: the admin right is gone.', $context),
            ViewAsEnd::MemberLeftSpace => $this->logger?->notice('The view of {member} by {admin} ended: the member left the space.', $context),
        };

        $this->events?->dispatch(new ViewAsStopped($context['admin'], $member->value, $reason));
    }

    private function isAdmin(MemberId $member): bool
    {
        try {
            return $this->spaces->isAdmin($member);
        } catch (TraversalBudgetExceeded $traversalBudgetExceeded) {
            $this->logger?->warning('The admins of the space could not be read within the budget: {reason}', ['reason' => $traversalBudgetExceeded->getMessage()]);

            return false;
        }
    }

    private function isMember(MemberId $member): bool
    {
        try {
            return $this->spaces->isMember($member);
        } catch (TraversalBudgetExceeded $traversalBudgetExceeded) {
            $this->logger?->warning('The members of the space could not be read within the budget: {reason}', ['reason' => $traversalBudgetExceeded->getMessage()]);

            return false;
        }
    }
}
