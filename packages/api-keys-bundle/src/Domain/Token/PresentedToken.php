<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Domain\Token;

use LogicException;
use Override;
use SensitiveParameter;
use Stringable;

/**
 * A token as a client sends it, its shape and checksum checked: the identifier that finds its row, and the secret the
 * row's keyed hash is checked against. It prints as its identifier alone and cannot be serialized, so the secret does
 * not end in a log or a cache.
 */
final readonly class PresentedToken implements Stringable
{
    public function __construct(
        public string $id,
        #[SensitiveParameter]
        public string $secret,
    ) {
    }

    #[Override]
    public function __toString(): string
    {
        return $this->id;
    }

    /**
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['id' => $this->id, 'secret' => '[redacted]'];
    }

    /**
     * @return never
     */
    public function __serialize(): array
    {
        throw new LogicException('A presented token is not serialized: it holds a secret.');
    }
}
