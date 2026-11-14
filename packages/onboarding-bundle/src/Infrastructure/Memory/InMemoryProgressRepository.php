<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Infrastructure\Memory;

use Override;
use Phil\OnboardingBundle\Domain\Model\Progress;
use Phil\OnboardingBundle\Domain\Repository\ProgressRepository;

/**
 * The progress in memory, for tests and demonstrations; it lasts as long as the process.
 */
final class InMemoryProgressRepository implements ProgressRepository
{
    /** @var array<string, Progress> by user and flow */
    private array $progress = [];

    #[Override]
    public function find(string $user, string $flow): ?Progress
    {
        return $this->progress[$this->key($user, $flow)] ?? null;
    }

    #[Override]
    public function ofUser(string $user): array
    {
        return array_values(array_filter($this->progress, static fn (Progress $progress): bool => $progress->user === $user));
    }

    #[Override]
    public function save(Progress $progress): void
    {
        $this->progress[$this->key($progress->user, $progress->flow)] = $progress;
    }

    #[Override]
    public function removeAllOf(string $user): void
    {
        $this->progress = array_filter($this->progress, static fn (Progress $progress): bool => $progress->user !== $user);
    }

    private function key(string $user, string $flow): string
    {
        return $flow."\n".$user;
    }
}
