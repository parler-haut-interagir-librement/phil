<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Infrastructure\Check;

use Closure;
use Override;
use Phil\MonitoringBundle\Domain\Contract\HealthCheck;
use Phil\MonitoringBundle\Domain\ValueObject\CheckOutcome;

/**
 * A local directory exists and is writable, degraded when its disk has less free space than its threshold — read by
 * `disk_free_space()`, or the function given in its place. The summary never names the path.
 */
final readonly class FilesystemCheck implements HealthCheck
{
    private const int MEGABYTE = 1_048_576;

    /**
     * @param Closure(string): (float|false) $freeSpace the free bytes of the disk of a directory
     */
    public function __construct(
        private string $directory,
        private string $name,
        private ?int $minFreeMegabytes = null,
        private Closure $freeSpace = disk_free_space(...),
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
        clearstatcache(true, $this->directory);

        if (!is_dir($this->directory)) {
            return CheckOutcome::down('Missing.');
        }

        if (!is_writable($this->directory)) {
            return CheckOutcome::down('Not writable.');
        }

        if (null === $this->minFreeMegabytes) {
            return CheckOutcome::up('Writable.');
        }

        $free = intdiv((int) ($this->freeSpace)($this->directory), self::MEGABYTE);

        if ($free < $this->minFreeMegabytes) {
            return CheckOutcome::degraded(\sprintf('Writable, %d MB free, under %d.', $free, $this->minFreeMegabytes));
        }

        return CheckOutcome::up(\sprintf('Writable, %d MB free.', $free));
    }
}
