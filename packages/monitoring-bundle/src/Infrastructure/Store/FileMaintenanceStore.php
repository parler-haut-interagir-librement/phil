<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Infrastructure\Store;

use DateTimeImmutable;
use JsonException;
use Override;
use Phil\MonitoringBundle\Domain\Contract\MaintenanceStore;
use Phil\MonitoringBundle\Domain\ValueObject\MaintenanceState;
use SplFileInfo;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * The maintenance mode as a flag file: the site is in maintenance while the file exists. The file holds the state in
 * JSON; an empty or unreadable file — one an operator made with `touch` — is maintenance since the file's last
 * change, with the configured `Retry-After`. The file is written atomically, and its status read afresh on every
 * request, so that a long-running worker sees it come and go.
 */
final readonly class FileMaintenanceStore implements MaintenanceStore
{
    public function __construct(
        private string $path,
        private int $defaultRetryAfterSeconds,
        private Filesystem $filesystem = new Filesystem(),
    ) {
    }

    #[Override]
    public function current(): ?MaintenanceState
    {
        clearstatcache(true, $this->path);

        if (!is_file($this->path)) {
            return null;
        }

        $fallback = new MaintenanceState(new DateTimeImmutable('@'.new SplFileInfo($this->path)->getMTime()), $this->defaultRetryAfterSeconds);

        try {
            return MaintenanceRecord::decode(json_decode($this->filesystem->readFile($this->path), true, 2, \JSON_THROW_ON_ERROR), $fallback);
        } catch (IOException|JsonException) {
            return $fallback;
        }
    }

    #[Override]
    public function enable(MaintenanceState $state): void
    {
        $this->filesystem->dumpFile($this->path, json_encode(MaintenanceRecord::encode($state), \JSON_THROW_ON_ERROR));
    }

    #[Override]
    public function disable(): void
    {
        $this->filesystem->remove($this->path);
    }
}
