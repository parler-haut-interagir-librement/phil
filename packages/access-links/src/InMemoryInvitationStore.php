<?php

declare(strict_types=1);

namespace Phil\AccessLinks;

use DateTimeImmutable;
use Override;
use Phil\AccessLinks\Contract\InvitationStore;

/**
 * The invitations and links of one process: for tests, and for scripts that invite and answer in one run.
 */
final class InMemoryInvitationStore implements InvitationStore
{
    /** @var array<string, Invitation> */
    private array $invitations = [];

    /** @var array<string, string> the invitation of each link, by hash */
    private array $links = [];

    /** @var array<string, int> the links of each invitation */
    private array $linkCounts = [];

    #[Override]
    public function add(Invitation $invitation, string $tokenHash): bool
    {
        foreach ($this->invitations as $existing) {
            if ($existing->campaign === $invitation->campaign && $existing->recipient === $invitation->recipient) {
                return false;
            }
        }

        $this->invitations[$invitation->id] = $invitation;
        $this->links[$tokenHash] = $invitation->id;
        $this->linkCounts[$invitation->id] = 1;

        return true;
    }

    #[Override]
    public function addLink(string $invitationId, string $tokenHash, int $maxLinks): bool
    {
        $count = $this->linkCounts[$invitationId] ?? null;

        if (null === $count || $count >= $maxLinks) {
            return false;
        }

        $this->links[$tokenHash] = $invitationId;
        $this->linkCounts[$invitationId] = $count + 1;

        return true;
    }

    #[Override]
    public function get(string $invitationId): ?Invitation
    {
        return $this->invitations[$invitationId] ?? null;
    }

    #[Override]
    public function findByToken(array $candidates): ?Invitation
    {
        foreach ($candidates as $candidate) {
            if (isset($this->links[$candidate])) {
                return $this->invitations[$this->links[$candidate]];
            }
        }

        return null;
    }

    #[Override]
    public function respond(string $invitationId, string $answer, DateTimeImmutable $now): bool
    {
        $invitation = $this->invitations[$invitationId] ?? null;

        if (null === $invitation || !$invitation->isEditableAt($now)) {
            return false;
        }

        $this->invitations[$invitationId] = $this->copy($invitation, answer: $answer, respondedAt: $invitation->respondedAt ?? $now, answeredAt: $now, setsNextReminder: true);

        return true;
    }

    #[Override]
    public function claimReminder(string $invitationId, int $sent, ?DateTimeImmutable $next, DateTimeImmutable $now): bool
    {
        $invitation = $this->invitations[$invitationId] ?? null;

        if (null === $invitation || !self::isDue($invitation, $now) || $invitation->remindersSent !== $sent) {
            return false;
        }

        $this->invitations[$invitationId] = $this->copy($invitation, remindersSent: $sent + 1, setsNextReminder: true, nextReminderAt: $next);

        return true;
    }

    #[Override]
    public function due(DateTimeImmutable $now, int $limit): array
    {
        $due = array_filter($this->invitations, static fn (Invitation $invitation): bool => self::isDue($invitation, $now));
        uasort($due, static fn (Invitation $a, Invitation $b): int => $a->nextReminderAt <=> $b->nextReminderAt);

        return \array_slice(array_map(strval(...), array_keys($due)), 0, $limit);
    }

    #[Override]
    public function revoke(string $invitationId, DateTimeImmutable $now): bool
    {
        $invitation = $this->invitations[$invitationId] ?? null;

        if (null === $invitation || $invitation->isRevoked()) {
            return false;
        }

        $this->invitations[$invitationId] = $this->copy($invitation, revokedAt: $now);

        return true;
    }

    #[Override]
    public function counts(string $campaign): CampaignCounts
    {
        $invited = 0;
        $responded = 0;
        $revoked = 0;

        foreach ($this->invitations as $invitation) {
            if ($invitation->campaign !== $campaign) {
                continue;
            }

            ++$invited;
            $responded += $invitation->hasResponded() ? 1 : 0;
            $revoked += $invitation->isRevoked() && !$invitation->hasResponded() ? 1 : 0;
        }

        return new CampaignCounts($invited, $responded, $revoked);
    }

    private static function isDue(Invitation $invitation, DateTimeImmutable $now): bool
    {
        return !$invitation->hasResponded()
            && !$invitation->isRevoked()
            && $now < $invitation->closesAt
            && $invitation->nextReminderAt instanceof DateTimeImmutable
            && $invitation->nextReminderAt <= $now;
    }

    private function copy(
        Invitation $invitation,
        ?string $answer = null,
        ?DateTimeImmutable $respondedAt = null,
        ?DateTimeImmutable $answeredAt = null,
        ?int $remindersSent = null,
        ?DateTimeImmutable $revokedAt = null,
        bool $setsNextReminder = false,
        ?DateTimeImmutable $nextReminderAt = null,
    ): Invitation {
        return new Invitation(
            $invitation->id,
            $invitation->campaign,
            $invitation->recipient,
            $invitation->createdAt,
            $invitation->closesAt,
            $invitation->expiresAt,
            $invitation->validFrom,
            $answer ?? $invitation->answer,
            $respondedAt ?? $invitation->respondedAt,
            $answeredAt ?? $invitation->answeredAt,
            $remindersSent ?? $invitation->remindersSent,
            $setsNextReminder ? $nextReminderAt : $invitation->nextReminderAt,
            $revokedAt ?? $invitation->revokedAt,
        );
    }
}
