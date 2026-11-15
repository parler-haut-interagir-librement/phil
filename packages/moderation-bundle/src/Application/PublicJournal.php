<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Application;

use Phil\ModerationBundle\Domain\Contract\DecisionRepository;
use Phil\ModerationBundle\Domain\ValueObject\Decision;

/**
 * The public journal of the moderation: the public decisions, the latest first, with the role of the moderator and
 * never their name.
 */
final readonly class PublicJournal
{
    public function __construct(
        private DecisionRepository $decisions,
    ) {
    }

    /**
     * @param positive-int     $limit
     * @param non-negative-int $offset
     *
     * @return list<JournalEntry>
     *
     * @phpstan-impure
     */
    public function entries(int $limit, int $offset = 0): array
    {
        return array_map(
            static fn (Decision $decision): JournalEntry => new JournalEntry($decision->action, $decision->target->type, $decision->role, $decision->publicReason, $decision->decidedAt),
            $this->decisions->public($limit, $offset),
        );
    }

    /**
     * @phpstan-impure
     */
    public function count(): int
    {
        return $this->decisions->countPublic();
    }
}
