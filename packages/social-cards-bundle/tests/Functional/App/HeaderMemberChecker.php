<?php

declare(strict_types=1);

namespace Phil\SocialCardsBundle\Tests\Functional\App;

use Override;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * The authorization checker of the test application: a request that carries `X-Test-Member: 1` is a signed-in
 * member, granted `IS_AUTHENTICATED` and nothing else.
 */
final readonly class HeaderMemberChecker implements AuthorizationCheckerInterface
{
    public function __construct(
        private RequestStack $requests,
    ) {
    }

    #[Override]
    public function isGranted(mixed $attribute, mixed $subject = null, ?AccessDecision $accessDecision = null): bool
    {
        return 'IS_AUTHENTICATED' === $attribute && '1' === $this->requests->getCurrentRequest()?->headers->get('X-Test-Member');
    }
}
