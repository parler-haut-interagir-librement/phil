<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Application;

use Phil\UserSecretsBundle\Domain\Exception\InvalidSecret;
use Phil\UserSecretsBundle\Domain\ValueObject\Owner;

/**
 * A task run for an owner, the only thing that can read a secret: it reads that owner's secrets and no one else's,
 * and says what for — the purpose goes to the audit trail.
 *
 * The application builds it where it already knows whose task it runs: the signed-in person's request, a message
 * handler whose message names the person.
 */
final readonly class SecretAccess
{
    public const int MAX_PURPOSE = 255;

    private function __construct(
        public Owner $owner,
        public string $purpose,
    ) {
    }

    /**
     * @throws InvalidSecret when the purpose is blank or longer than 255 bytes
     */
    public static function forTaskOf(Owner $owner, string $purpose): self
    {
        if ('' === trim($purpose) || \strlen($purpose) > self::MAX_PURPOSE) {
            throw InvalidSecret::because(\sprintf('A task names its purpose in 1 to %d bytes.', self::MAX_PURPOSE));
        }

        return new self($owner, $purpose);
    }
}
