<?php

declare(strict_types=1);

namespace Phil\AccessCodes;

use DateTimeImmutable;
use Phil\AccessCodes\Exception\InvalidConfiguration;

/**
 * A named batch of codes: its identifier, the name people know it by, how many codes it holds, when it was created,
 * when its codes stop being accepted, and when it was revoked.
 */
final readonly class Batch
{
    public const int MAX_NAME_LENGTH = 120;

    public const int MAX_SIZE = 1_000_000;

    /** The identifier: 1 to 64 letters, digits, hyphens and underscores. */
    public const string ID_PATTERN = '/^[A-Za-z0-9_-]{1,64}$/D';

    /**
     * @throws InvalidConfiguration when the identifier, the name, the size or the expiry is out of bounds
     */
    public function __construct(
        public string $id,
        public string $name,
        public int $size,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $expiresAt = null,
        public ?DateTimeImmutable $revokedAt = null,
    ) {
        if (1 !== preg_match(self::ID_PATTERN, $id)) {
            throw InvalidConfiguration::because('A batch identifier is 1 to 64 letters, digits, hyphens and underscores.');
        }

        if ('' === trim($name) || mb_strlen($name) > self::MAX_NAME_LENGTH || !mb_check_encoding($name, 'UTF-8')) {
            throw InvalidConfiguration::because(\sprintf('A batch name is 1 to %d characters of UTF-8.', self::MAX_NAME_LENGTH));
        }

        if ($size < 1 || $size > self::MAX_SIZE) {
            throw InvalidConfiguration::because(\sprintf('A batch holds 1 to %d codes.', self::MAX_SIZE));
        }

        if ($expiresAt instanceof DateTimeImmutable && $expiresAt <= $createdAt) {
            throw InvalidConfiguration::because('A batch expires after its creation.');
        }
    }

    public function isRevoked(): bool
    {
        return $this->revokedAt instanceof DateTimeImmutable;
    }

    public function isExpiredAt(DateTimeImmutable $now): bool
    {
        return $this->expiresAt instanceof DateTimeImmutable && $this->expiresAt <= $now;
    }
}
