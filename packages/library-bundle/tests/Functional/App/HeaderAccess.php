<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Tests\Functional\App;

use Override;
use Phil\LibraryBundle\Application\Contract\MemberResolver;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * The application's security and members, as small as the tests need: the role a request has is its `X-Role` header,
 * the member it is for its `X-Member` header.
 */
final readonly class HeaderAccess implements AuthorizationCheckerInterface, MemberResolver
{
    public function __construct(
        private RequestStack $requests,
    ) {
    }

    #[Override]
    public function isGranted(mixed $attribute, mixed $subject = null, ?AccessDecision $accessDecision = null): bool
    {
        return $attribute === $this->requests->getCurrentRequest()?->headers->get('X-Role');
    }

    #[Override]
    public function current(): ?string
    {
        return $this->requests->getCurrentRequest()?->headers->get('X-Member');
    }
}
