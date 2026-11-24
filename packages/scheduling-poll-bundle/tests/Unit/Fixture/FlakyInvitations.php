<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use Phil\AccessLinks\CampaignCounts;
use Phil\AccessLinks\Contract\InvitationStore;
use Phil\AccessLinks\Invitation;

/**
 * A store of invitations that fails the way a race does: a link that stops being found after some reads, an answer
 * that finds its invitation closed although the poll is still open.
 */
final class FlakyInvitations implements InvitationStore
{
    /**
     * @param int|null $finds how many reads of a token find anything, `null` for all
     */
    public function __construct(
        private readonly InvitationStore $store,
        public ?int $finds = null,
        public bool $refuseAnswers = false,
    ) {
    }

    #[Override]
    public function add(Invitation $invitation, string $tokenHash): bool
    {
        return $this->store->add($invitation, $tokenHash);
    }

    #[Override]
    public function addLink(string $invitationId, string $tokenHash, int $maxLinks): bool
    {
        return $this->store->addLink($invitationId, $tokenHash, $maxLinks);
    }

    #[Override]
    public function get(string $invitationId): ?Invitation
    {
        return $this->store->get($invitationId);
    }

    #[Override]
    public function findByToken(array $candidates): ?Invitation
    {
        if (null !== $this->finds) {
            if ($this->finds <= 0) {
                return null;
            }

            --$this->finds;
        }

        return $this->store->findByToken($candidates);
    }

    #[Override]
    public function respond(string $invitationId, string $answer, DateTimeImmutable $now): bool
    {
        return !$this->refuseAnswers && $this->store->respond($invitationId, $answer, $now);
    }

    #[Override]
    public function claimReminder(string $invitationId, int $sent, ?DateTimeImmutable $next, DateTimeImmutable $now): bool
    {
        return $this->store->claimReminder($invitationId, $sent, $next, $now);
    }

    #[Override]
    public function due(DateTimeImmutable $now, int $limit): array
    {
        return $this->store->due($now, $limit);
    }

    #[Override]
    public function revoke(string $invitationId, DateTimeImmutable $now): bool
    {
        return $this->store->revoke($invitationId, $now);
    }

    #[Override]
    public function counts(string $campaign): CampaignCounts
    {
        return $this->store->counts($campaign);
    }
}
