<?php

declare(strict_types=1);

namespace Phil\Backup\Tests\Unit\Fixture;

use Closure;
use Override;
use Phil\Backup\Database\DatabaseClient;
use Phil\Backup\Database\DatabaseIdentity;

/**
 * A client that runs a closure on the file it backs up into, before the client it wraps writes it: what the file is
 * while it is written, or what another backup does at that moment.
 */
final readonly class WatchingClient implements DatabaseClient
{
    /**
     * @param Closure(string): void $watch
     */
    public function __construct(
        private DatabaseClient $client,
        private Closure $watch,
    ) {
    }

    #[Override]
    public function identity(): DatabaseIdentity
    {
        return $this->client->identity();
    }

    #[Override]
    public function extension(): string
    {
        return $this->client->extension();
    }

    #[Override]
    public function backup(string $file): void
    {
        ($this->watch)($file);
        $this->client->backup($file);
    }

    #[Override]
    public function restore(string $file): void
    {
        $this->client->restore($file);
    }

    #[Override]
    public function withDatabase(string $database): DatabaseClient
    {
        return new self($this->client->withDatabase($database), $this->watch);
    }

    #[Override]
    public function check(): void
    {
        $this->client->check();
    }
}
