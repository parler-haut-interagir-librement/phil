<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Tests\Unit\Infrastructure;

use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;

final class TokenFixture
{
    /**
     * @param array<string, mixed> $attributes
     */
    public static function token(array $attributes = [], string $subject = 'subject-1'): PostAuthenticationToken
    {
        $token = new PostAuthenticationToken(new InMemoryUser($subject, null, ['ROLE_USER']), 'main', ['ROLE_USER']);
        $token->setAttributes($attributes);

        return $token;
    }
}
