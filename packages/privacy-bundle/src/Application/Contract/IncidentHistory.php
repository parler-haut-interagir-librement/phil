<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application\Contract;

use Phil\PrivacyBundle\Domain\Incident\IncidentRevision;

/**
 * The corrections of the register of incidents: what each line held before it was corrected. A register that keeps
 * them implements this beside `IncidentRegister`.
 */
interface IncidentHistory
{
    /**
     * @return list<IncidentRevision> the oldest correction first
     */
    public function revisions(string $id): array;
}
