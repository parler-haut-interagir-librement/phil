<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Application;

use LogicException;
use Phil\ApiKeysBundle\Domain\Model\ApiToken;
use SensitiveParameter;

/**
 * A token just created, and its text: the only moment the text exists outside the client. Show it once, never store
 * it, never log it; the row keeps a keyed hash. It cannot be serialized.
 */
final readonly class IssuedToken
{
    public function __construct(
        public ApiToken $token,
        #[SensitiveParameter]
        public string $plainText,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['id' => $this->token->id(), 'plainText' => '[redacted]'];
    }

    /**
     * @return never
     */
    public function __serialize(): array
    {
        throw new LogicException('An issued token is not serialized: it holds a secret.');
    }
}
