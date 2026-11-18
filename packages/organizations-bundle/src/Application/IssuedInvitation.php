<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Application;

use Phil\OrganizationsBundle\Domain\Model\Invitation;
use SensitiveParameter;

/**
 * An invitation newly created, and its token: shown once to its creator, to send or to put in a link, never kept.
 */
final readonly class IssuedInvitation
{
    /**
     * @param non-empty-string $token
     */
    public function __construct(
        public Invitation $invitation,
        #[SensitiveParameter]
        public string $token,
    ) {
    }
}
