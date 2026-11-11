<?php

declare(strict_types=1);

namespace Phil\ConsoleBundle\Domain\ValueObject;

/**
 * What a command cost: its duration, the memory it added, the process's peak, and its exit code.
 */
final readonly class RunSummary
{
    private const array UNITS = ['B', 'KiB', 'MiB', 'GiB', 'TiB'];

    public function __construct(
        public string $command,
        public float $seconds,
        public int $memoryDelta,
        public int $memoryPeak,
        public int $exitCode,
    ) {
    }

    public function text(): string
    {
        return \sprintf(
            '%s: exit code %d in %.3f s, memory %s%s, peak %s.',
            $this->command,
            $this->exitCode,
            $this->seconds,
            $this->memoryDelta < 0 ? '-' : '+',
            $this->bytes(abs($this->memoryDelta)),
            $this->bytes($this->memoryPeak),
        );
    }

    private function bytes(int $bytes): string
    {
        $unit = 0;
        $value = $bytes;

        while ($value >= 1024 && $unit < 4) {
            $value /= 1024;
            ++$unit;
        }

        return 0 === $unit ? \sprintf('%d B', $bytes) : \sprintf('%.1f %s', $value, self::UNITS[$unit]);
    }
}
