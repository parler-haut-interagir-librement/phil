<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Anonymization;

/**
 * Which database a client speaks to — its platform, host and name — for the guard and for messages. A SQLite
 * database has no host, and its name is its file.
 */
final readonly class DatabaseIdentity
{
    public function __construct(
        public string $platform,
        public ?string $host,
        public string $name,
    ) {
    }

    public function describe(): string
    {
        return null === $this->host ? \sprintf('%s database %s', $this->platform, $this->name) : \sprintf('%s database %s on %s', $this->platform, $this->name, $this->host);
    }
}
