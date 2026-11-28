<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Tests\Functional\App;

use Override;
use SensitiveParameter;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

/**
 * Refuses every access token: the `access_token` authenticator of the test application only serves the test client,
 * which signs a user in with `loginUser()` and the attributes of a token.
 */
final readonly class RefusingAccessTokenHandler implements AccessTokenHandlerInterface
{
    #[Override]
    public function getUserBadgeFrom(#[SensitiveParameter] string $accessToken): UserBadge
    {
        throw new BadCredentialsException('The test application accepts no access token.');
    }
}
