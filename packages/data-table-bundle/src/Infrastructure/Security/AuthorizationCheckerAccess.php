<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Infrastructure\Security;

use Override;
use Phil\DataTableBundle\Application\Contract\TableAccess;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * The attributes asked of Symfony Security's voters.
 */
final readonly class AuthorizationCheckerAccess implements TableAccess
{
    public function __construct(
        private AuthorizationCheckerInterface $checker,
    ) {
    }

    #[Override]
    public function isGranted(string $attribute, array|object|null $subject = null): bool
    {
        return $this->checker->isGranted($attribute, $subject);
    }
}
