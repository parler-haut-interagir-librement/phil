<?php

declare(strict_types=1);

namespace Phil\EditLockBundle\Tests\Functional\App;

use Override;
use Phil\EditLockBundle\Application\HolderProvider;
use Phil\EditLockBundle\Domain\ValueObject\Holder;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The writer the request names in `X-User`, standing for the application's security.
 */
final readonly class HeaderHolder implements HolderProvider
{
    public function __construct(
        private RequestStack $requests,
    ) {
    }

    #[Override]
    public function holder(): ?Holder
    {
        $id = $this->requests->getCurrentRequest()?->headers->get('X-User');

        return null === $id ? null : Holder::of($id, ucfirst($id));
    }
}
