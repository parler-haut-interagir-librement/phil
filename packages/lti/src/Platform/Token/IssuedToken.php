<?php

declare(strict_types=1);

namespace Phil\Lti\Platform\Token;

use SensitiveParameter;

/**
 * The answer of the token endpoint: the value of the token, given once, its lifetime and its scopes.
 */
final readonly class IssuedToken
{
    /**
     * @param list<string> $scopes
     */
    public function __construct(
        #[SensitiveParameter]
        public string $value,
        public int $expiresIn,
        public array $scopes,
    ) {
    }

    /**
     * @return array{access_token: string, token_type: 'Bearer', expires_in: int, scope: string}
     */
    public function toArray(): array
    {
        return ['access_token' => $this->value, 'token_type' => 'Bearer', 'expires_in' => $this->expiresIn, 'scope' => implode(' ', $this->scopes)];
    }
}
