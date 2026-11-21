<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Infrastructure\Console\Fixture;

use Override;
use Phil\PrivacyBundle\Application\Contract\IncidentRegister;
use Phil\PrivacyBundle\Domain\Incident\Incident;

/**
 * A register of one line that counts its saves.
 */
abstract class OneIncidentRegister implements IncidentRegister
{
    public int $saves = 0;

    public function __construct(
        private readonly Incident $incident,
    ) {
    }

    #[Override]
    public function save(Incident $incident): void
    {
        ++$this->saves;
    }

    #[Override]
    public function find(string $id): ?Incident
    {
        return $id === $this->incident->id ? $this->incident : null;
    }

    #[Override]
    public function all(): array
    {
        return [$this->incident];
    }
}
