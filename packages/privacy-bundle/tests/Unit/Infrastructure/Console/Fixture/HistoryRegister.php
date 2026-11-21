<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Infrastructure\Console\Fixture;

use Override;
use Phil\PrivacyBundle\Application\Contract\IncidentHistory;
use Phil\PrivacyBundle\Domain\Incident\Incident;
use Phil\PrivacyBundle\Domain\Incident\IncidentRevision;

/**
 * A register of one line with its corrections.
 */
final class HistoryRegister extends OneIncidentRegister implements IncidentHistory
{
    /**
     * @param list<IncidentRevision> $revisions
     */
    public function __construct(
        Incident $incident,
        private readonly array $revisions,
    ) {
        parent::__construct($incident);
    }

    #[Override]
    public function revisions(string $id): array
    {
        return $this->revisions;
    }
}
