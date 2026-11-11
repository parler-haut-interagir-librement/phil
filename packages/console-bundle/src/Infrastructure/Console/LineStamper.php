<?php

declare(strict_types=1);

namespace Phil\ConsoleBundle\Infrastructure\Console;

use DateTimeImmutable;
use Phil\ConsoleBundle\Domain\TimestampFormat;
use Psr\Clock\ClockInterface;

/**
 * Puts the time before each line of one output stream, as its bytes are written: a line written in several parts gets
 * one time, at its start. It belongs to one run of one command.
 */
final class LineStamper
{
    private bool $atLineStart = true;

    public function __construct(
        private readonly TimestampFormat $format,
        private readonly ClockInterface $clock,
        private readonly DateTimeImmutable $start,
        private readonly bool $decorated,
    ) {
    }

    public function stamp(string $data): string
    {
        $stamped = '';
        $prefix = null;

        $lines = preg_split('/(?<=\n)/', $data, -1, \PREG_SPLIT_NO_EMPTY);

        foreach (false === $lines ? [] : $lines as $line) {
            if ($this->atLineStart) {
                $stamped .= $prefix ??= $this->prefix();
            }

            $stamped .= $line;
            $this->atLineStart = str_ends_with($line, "\n");
        }

        return $stamped;
    }

    private function prefix(): string
    {
        $time = '['.$this->format->render($this->clock->now(), $this->start).']';

        return ($this->decorated ? "\033[90m".$time."\033[39m" : $time).' ';
    }
}
