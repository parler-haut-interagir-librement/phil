<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application\Contract;

use Phil\PrivacyBundle\Domain\Incident\NoticeOrganisation;

/**
 * Where the notices of a confidentiality incident read who gives them: the organisation, its enterprise number and the
 * person to contact. phil/legal-notices provides one from the identity of the site; without one, those rubrics are left
 * to complete.
 */
interface NoticeOrganisationSource
{
    public function organisation(): NoticeOrganisation;
}
