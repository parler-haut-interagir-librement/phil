<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Infrastructure\Memory;

use Override;
use Phil\SitesConsoleBundle\Domain\Contract\KeyRotationRepository;
use Phil\SitesConsoleBundle\Domain\Model\KeyRotation;
use Phil\SitesConsoleBundle\Domain\Status\RotationPhase;

/**
 * The rotations of the console key in memory, for tests and demonstrations.
 */
final class InMemoryKeyRotationRepository implements KeyRotationRepository
{
    /** @var array<string, KeyRotation> */
    private array $rotations = [];

    #[Override]
    public function get(string $id): ?KeyRotation
    {
        return $this->rotations[$id] ?? null;
    }

    #[Override]
    public function save(KeyRotation $rotation): void
    {
        $this->rotations[$rotation->id()] = $rotation;
    }

    #[Override]
    public function unfinished(): ?KeyRotation
    {
        return $this->newest(static fn (KeyRotation $rotation): bool => RotationPhase::Finished !== $rotation->phase());
    }

    #[Override]
    public function latestTo(string $nextKey): ?KeyRotation
    {
        return $this->newest(static fn (KeyRotation $rotation): bool => $rotation->nextKey()->toBase64() === $nextKey);
    }

    /**
     * @param callable(KeyRotation): bool $matches
     */
    private function newest(callable $matches): ?KeyRotation
    {
        $rotations = array_filter($this->rotations, $matches);
        krsort($rotations, \SORT_STRING);

        return array_first($rotations);
    }
}
