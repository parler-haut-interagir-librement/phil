<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Application;

use Phil\AccessBundle\Application\Contract\CurrentMember;
use Phil\AccessBundle\Application\Contract\ViewAsStore;
use Phil\AccessBundle\Application\Event\ViewAsStarted;
use Phil\AccessBundle\Application\Event\ViewAsStopped;
use Phil\AccessBundle\Application\Exception\ViewAsRefused;
use Phil\AccessBundle\Domain\ValueObject\MemberId;
use Phil\ResourcePermissions\Exception\TraversalBudgetExceeded;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

/**
 * "View as this member": an admin of the space sees the site as another member sees it, read-only, without taking
 * their session. Every start and stop is logged, with both identifiers, and dispatched as an event; the member viewed
 * is forgotten as soon as the admin is no longer one.
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
     * @throws ViewAsRefused when nobody is signed in, the member is no admin, or names themselves
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

        $this->store->set($member);
        $this->logger?->notice('{admin} started viewing the site as {member}.', ['admin' => $admin->value, 'member' => $member->value]);
        $this->events?->dispatch(new ViewAsStarted($admin->value, $member->value));
    }

    public function stop(): void
    {
        $member = $this->store->get();

        if (!$member instanceof MemberId) {
            return;
        }

        $this->store->set(null);
        $admin = $this->members->current()->value ?? '';
        $this->logger?->notice('{admin} stopped viewing the site as {member}.', ['admin' => $admin, 'member' => $member->value]);
        $this->events?->dispatch(new ViewAsStopped($admin, $member->value));
    }

    /**
     * The member the current admin views the site as, or null.
     */
    public function member(): ?MemberId
    {
        $member = $this->store->get();
        $admin = $this->members->current();

        if (!$member instanceof MemberId || !$admin instanceof MemberId || !$this->isAdmin($admin)) {
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

    private function isAdmin(MemberId $member): bool
    {
        try {
            return $this->spaces->isAdmin($member);
        } catch (TraversalBudgetExceeded $traversalBudgetExceeded) {
            $this->logger?->warning('The admins of the space could not be read within the budget: {reason}', ['reason' => $traversalBudgetExceeded->getMessage()]);

            return false;
        }
    }
}
