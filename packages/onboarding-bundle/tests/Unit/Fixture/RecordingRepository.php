<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Tests\Unit\Fixture;

use Override;
use Phil\OnboardingBundle\Domain\Exception\ProgressNotSaved;
use Phil\OnboardingBundle\Domain\Model\Progress;
use Phil\OnboardingBundle\Domain\Repository\ProgressRepository;
use Phil\OnboardingBundle\Infrastructure\Memory\InMemoryProgressRepository;
use RuntimeException;

/**
 * The in-memory store, counting what is saved, and refusing it as a race would when a test says so.
 */
final class RecordingRepository implements ProgressRepository
{
    public int $saves = 0;

    public bool $racing = false;

    private readonly InMemoryProgressRepository $memory;

    public function __construct()
    {
        $this->memory = new InMemoryProgressRepository();
    }

    #[Override]
    public function find(string $user, string $flow): ?Progress
    {
        return $this->memory->find($user, $flow);
    }

    #[Override]
    public function ofUser(string $user): array
    {
        return $this->memory->ofUser($user);
    }

    #[Override]
    public function save(Progress $progress): void
    {
        if ($this->racing) {
            throw ProgressNotSaved::of($progress->flow, new RuntimeException('race'));
        }

        ++$this->saves;
        $this->memory->save($progress);
    }

    #[Override]
    public function removeAllOf(string $user): void
    {
        $this->memory->removeAllOf($user);
    }
}
