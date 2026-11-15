<?php

declare(strict_types=1);

namespace Phil\Lti\Platform\Ags;

use Override;
use Phil\Lti\Contract\ScoreStore;
use Phil\Lti\Service\Ags\Score;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Scores held in one process, for tests.
 */
final class InMemoryScoreStore implements ScoreStore, ResetInterface
{
    /** @var array<string, array<string, Score>> */
    private array $scores = [];

    #[Override]
    public function latest(string $lineItemId, string $userId): ?Score
    {
        return $this->scores[$lineItemId][$userId] ?? null;
    }

    #[Override]
    public function record(string $lineItemId, Score $score): void
    {
        $this->scores[$lineItemId][$score->userId] = $score;
    }

    #[Override]
    public function scores(string $lineItemId, ?string $userId, int $limit, int $offset): array
    {
        $scores = $this->scores[$lineItemId] ?? [];

        if (null !== $userId) {
            $scores = isset($scores[$userId]) ? [$userId => $scores[$userId]] : [];
        }

        ksort($scores, \SORT_STRING);

        return \array_slice(array_values($scores), $offset, $limit);
    }

    #[Override]
    public function reset(): void
    {
        $this->scores = [];
    }
}
