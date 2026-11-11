<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Infrastructure\Check;

use League\Flysystem\FilesystemOperator;
use Override;
use Phil\MonitoringBundle\Domain\Contract\HealthCheck;
use Phil\MonitoringBundle\Domain\ValueObject\CheckOutcome;

/**
 * A Flysystem storage keeps a file: a probe is written, read back and deleted at the root of the storage. A storage
 * that cannot be reached throws, and the check counts as down.
 *
 * Each probe has a name of its own, `PROBE` followed by a random suffix, so that checks run at the same time never read
 * or delete one another's probe; it is deleted even when it cannot be read back.
 */
final readonly class FlysystemCheck implements HealthCheck
{
    public const string PROBE = '.phil-monitoring-probe';

    public function __construct(
        private FilesystemOperator $storage,
        private string $name,
    ) {
    }

    #[Override]
    public function name(): string
    {
        return $this->name;
    }

    #[Override]
    public function check(): CheckOutcome
    {
        $token = \sprintf('%016x', random_int(\PHP_INT_MIN, \PHP_INT_MAX));
        $probe = self::PROBE.'-'.$token;
        $this->storage->write($probe, $token);

        try {
            $kept = $this->storage->read($probe);
        } finally {
            $this->storage->delete($probe);
        }

        return $token === $kept ? CheckOutcome::up('Keeps a file.') : CheckOutcome::down('Loses the file it wrote.');
    }
}
