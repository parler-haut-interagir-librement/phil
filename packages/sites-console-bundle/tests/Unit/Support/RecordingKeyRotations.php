<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Tests\Unit\Support;

use Override;
use Phil\SitesConsoleBundle\Domain\Contract\KeyRotationRepository;
use Phil\SitesConsoleBundle\Domain\Model\KeyRotation;
use Phil\SitesConsoleBundle\Domain\Status\RotationPhase;

/**
 * A store of rotations that notes the phase of each rotation at the moment it is saved.
 */
final class RecordingKeyRotations implements KeyRotationRepository
{
    /** @var list<RotationPhase> */
    public array $phases = [];

    public function __construct(
        private readonly KeyRotationRepository $inner,
    ) {
    }

    #[Override]
    public function get(string $id): ?KeyRotation
    {
        return $this->inner->get($id);
    }

    #[Override]
    public function save(KeyRotation $rotation): void
    {
        $this->phases[] = $rotation->phase();
        $this->inner->save($rotation);
    }

    #[Override]
    public function unfinished(): ?KeyRotation
    {
        return $this->inner->unfinished();
    }

    #[Override]
    public function latestTo(string $nextKey): ?KeyRotation
    {
        return $this->inner->latestTo($nextKey);
    }
}
