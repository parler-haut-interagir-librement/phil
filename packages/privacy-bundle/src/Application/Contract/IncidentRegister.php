<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application\Contract;

use Phil\PrivacyBundle\Domain\Incident\Incident;

/**
 * Where the register of confidentiality incidents is kept.
 */
interface IncidentRegister
{
    /**
     * Adds the incident, or replaces the line of the same identifier.
     */
    public function save(Incident $incident): void;

    public function find(string $id): ?Incident;

    /**
     * @return list<Incident> the most recently discovered first
     */
    public function all(): array;
}
