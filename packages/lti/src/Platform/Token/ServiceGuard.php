<?php

declare(strict_types=1);

namespace Phil\Lti\Platform\Token;

use Phil\Lti\Contract\AccessTokens;
use Phil\Lti\Exception\AccessDenied;

/**
 * Checks the bearer token of a call to a service of the platform, and the scope the call needs.
 */
final readonly class ServiceGuard
{
    public function __construct(
        private AccessTokens $tokens,
    ) {
    }

    /**
     * @param string|null  $authorization the `Authorization` header of the request
     * @param list<string> $scopes        the scopes any of which allows the call
     *
     * @throws AccessDenied
     */
    public function authorize(?string $authorization, array $scopes): AccessToken
    {
        if (null === $authorization || 1 !== preg_match('/^Bearer ([A-Za-z0-9._~+\/-]{16,512}=*)$/D', $authorization, $match)) {
            throw AccessDenied::invalidToken();
        }

        $token = $this->tokens->find(AccessToken::hash($match[1])) ?? throw AccessDenied::invalidToken();

        foreach ($scopes as $scope) {
            if ($token->grants($scope)) {
                return $token;
            }
        }

        throw AccessDenied::insufficientScope($scopes[0] ?? '');
    }
}
