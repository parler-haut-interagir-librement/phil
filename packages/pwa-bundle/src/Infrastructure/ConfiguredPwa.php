<?php

declare(strict_types=1);

namespace Phil\PwaBundle\Infrastructure;

use Phil\PwaBundle\Application\ManifestReader;
use Phil\PwaBundle\Domain\ValueObject\Manifest;
use Phil\PwaBundle\Domain\ValueObject\WorkerSettings;

/**
 * The manifest and the settings of the service worker of the configuration, read once when the service is built.
 */
final readonly class ConfiguredPwa
{
    private Manifest $manifest;

    private WorkerSettings $worker;

    /**
     * @param array<mixed> $manifest
     * @param array<mixed> $worker
     */
    public function __construct(array $manifest, array $worker, ManifestReader $reader = new ManifestReader())
    {
        $this->manifest = $reader->read($manifest);
        $this->worker = $reader->worker($worker);
    }

    public function manifest(): Manifest
    {
        return $this->manifest;
    }

    public function worker(): WorkerSettings
    {
        return $this->worker;
    }
}
