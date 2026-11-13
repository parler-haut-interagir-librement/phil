<?php

declare(strict_types=1);

namespace Phil\CheckinBundle\Application\Contract;

use Phil\Checkin\Credential;

/**
 * What the admission rules may read of a credential beyond what `phil/checkin` knows of it: the product of a ticket,
 * the holder's age band, a signed waiver. The application answers from its own records, by the credential's
 * identifier; the rules read the answer as `attributes`.
 */
interface CredentialAttributes
{
    /**
     * @return array<string, mixed> the attributes by name; an empty array for a credential the application describes no further
     */
    public function of(Credential $credential): array;
}
