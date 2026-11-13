<?php

declare(strict_types=1);

namespace Phil\AccessLinks\Contract;

use DateTimeImmutable;
use Phil\AccessLinks\CampaignCounts;
use Phil\AccessLinks\Invitation;

/**
 * Where the invitations and the hashes of their link tokens live. A store never sees a token in clear: it receives the
 * keyed hashes, and finds a link by its hash under each key of the hasher.
 *
 * Every change is one conditional write, so that two requests at the same moment cannot answer a closed invitation,
 * send a reminder twice, or issue more links than allowed.
 */
interface InvitationStore
{
    /**
     * Adds an invitation and the hash of its first link; returns `false`, adding nothing, when the recipient is
     * already invited to the campaign.
     */
    public function add(Invitation $invitation, string $tokenHash): bool;

    /**
     * Adds the hash of another link of an invitation, unless it has `$maxLinks` already or is unknown; returns whether
     * it was added.
     */
    public function addLink(string $invitationId, string $tokenHash, int $maxLinks): bool;

    public function get(string $invitationId): ?Invitation;

    /**
     * The invitation of the link one of these hashes stands for, or `null`.
     *
     * @param non-empty-list<non-empty-string> $candidates
     */
    public function findByToken(array $candidates): ?Invitation;

    /**
     * Records an answer, atomically, only while the invitation is not revoked, its link is valid and it is not closed
     * at `$now`: the first answer sets when the recipient responded, every answer when they last answered; no
     * reminder falls due any more. Returns whether the answer was recorded.
     */
    public function respond(string $invitationId, string $answer, DateTimeImmutable $now): bool;

    /**
     * Claims the next reminder of an invitation, atomically: only when `$sent` reminders were sent, it has no answer,
     * it is not revoked, not closed, and its reminder is due at `$now`. The count goes up by one and the next reminder
     * falls due at `$next`, or never. Of two claims at the same moment, one wins.
     */
    public function claimReminder(string $invitationId, int $sent, ?DateTimeImmutable $next, DateTimeImmutable $now): bool;

    /**
     * The invitations whose reminder is due at `$now`, the most overdue first: no answer, not revoked, not closed.
     *
     * @param positive-int $limit
     *
     * @return list<string> their identifiers
     */
    public function due(DateTimeImmutable $now, int $limit): array;

    /**
     * Revokes an invitation: its links open nothing more. Returns `false` for one revoked before or unknown.
     */
    public function revoke(string $invitationId, DateTimeImmutable $now): bool;

    /**
     * The counters of a campaign; an invitation revoked after it was answered counts as answered.
     */
    public function counts(string $campaign): CampaignCounts;
}
