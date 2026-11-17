<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Infrastructure\Memory;

use DateTimeImmutable;
use InvalidArgumentException;
use Override;
use Phil\LtiBundle\Application\Contract\PendingScores;
use Phil\LtiBundle\Application\PendingScore;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Pending scores held in one process, for tests: a process runs one claim at a time.
 */
final class InMemoryPendingScores implements PendingScores, ResetInterface
{
    /** @var array<string, PendingScore> */
    private array $scores = [];

    /** @var array<string, DateTimeImmutable> */
    private array $claims = [];

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
    public function markDispatched(string $id, DateTimeImmutable $at): void
    {
        $score = $this->scores[$id] ?? null;

        if ($score instanceof PendingScore) {
            $this->scores[$id] = new PendingScore($score->id, $score->issuer, $score->clientId, $score->lineItem, $score->score, $score->createdAt, $at, $score->refusedAt, $score->refusal);
        }
    }

    #[Override]
    public function markRefused(string $id, string $reason, DateTimeImmutable $at): void
    {
        $score = $this->scores[$id] ?? null;

        if ($score instanceof PendingScore) {
            $this->scores[$id] = new PendingScore($score->id, $score->issuer, $score->clientId, $score->lineItem, $score->score, $score->createdAt, $score->dispatchedAt, $at, PendingScore::refusal($reason));
        }
    }

    #[Override]
    public function claim(DateTimeImmutable $before, DateTimeImmutable $now, int $limit): array
    {
        if ($now <= $before) {
            throw new InvalidArgumentException('A claim is made after its threshold: a score claimed now would be claimed again at once.');
        }

        $claimed = [];

        foreach ($this->sorted() as $score) {
            if (\count($claimed) >= $limit) {
                break;
            }

            if (null === $score->dispatchedAt && null === $score->refusedAt && ($this->claims[$score->id] ?? $score->createdAt) <= $before) {
                $this->claims[$score->id] = $now;
                $claimed[] = $score;
            }
        }

        return $claimed;
    }

    #[Override]
    public function refused(DateTimeImmutable $before, int $limit): array
    {
        $refused = array_filter($this->sorted(), static fn (PendingScore $score): bool => $score->refusedAt instanceof DateTimeImmutable && $score->refusedAt <= $before);
        usort($refused, static fn (PendingScore $a, PendingScore $b): int => $a->refusedAt <=> $b->refusedAt);

        return \array_slice($refused, 0, $limit);
    }

    #[Override]
    public function delete(string $id): void
    {
        unset($this->scores[$id], $this->claims[$id]);
    }

    #[Override]
    public function reset(): void
    {
        $this->scores = [];
        $this->claims = [];
    }

    /**
     * @return list<PendingScore>
     */
    private function sorted(): array
    {
        $scores = $this->scores;
        usort($scores, static fn (PendingScore $a, PendingScore $b): int => [$a->createdAt, $a->id] <=> [$b->createdAt, $b->id]);

        return $scores;
    }
}
