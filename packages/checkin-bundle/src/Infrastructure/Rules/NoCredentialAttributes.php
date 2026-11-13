<?php

declare(strict_types=1);

namespace Phil\CheckinBundle\Infrastructure\Rules;

use Override;
use Phil\Checkin\Credential;
use Phil\CheckinBundle\Application\Contract\CredentialAttributes;

/**
 * No attributes: the rules read only the credential, the checkpoint and the time.
 */
final readonly class NoCredentialAttributes implements CredentialAttributes
{
    #[Override]
    public function of(Credential $credential): array
    {
        return [];
    }
}
