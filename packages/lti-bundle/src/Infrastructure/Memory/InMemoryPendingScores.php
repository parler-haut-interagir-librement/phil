<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Infrastructure\Memory;

use Override;
use Phil\LtiBundle\Application\Contract\PendingScores;
use Phil\LtiBundle\Application\PendingScore;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Pending scores held in one process, for tests.
 */
final class InMemoryPendingScores implements PendingScores, ResetInterface
{
    /** @var array<string, PendingScore> */
    private array $scores = [];

    #[Override]
    public function save(PendingScore $score): void
    {
        $this->scores[$score->id] = $score;
    }

    #[Override]
    public function find(string $id): ?PendingScore
    {
        return $this->scores[$id] ?? null;
    }

    #[Override]
    public function delete(string $id): void
    {
        unset($this->scores[$id]);
    }

    #[Override]
    public function reset(): void
    {
        $this->scores = [];
    }
}
