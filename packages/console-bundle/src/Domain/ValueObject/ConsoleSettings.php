<?php

declare(strict_types=1);

namespace Phil\ConsoleBundle\Domain\ValueObject;

use Phil\ConsoleBundle\Domain\SummaryMode;
use Phil\ConsoleBundle\Domain\TimestampFormat;

/**
 * The bundle's configuration of the output: timestamps without the option, their format, the summary and the default
 * debug channels.
 */
final readonly class ConsoleSettings
{
    /**
     * @param list<string> $debugChannels The channels `--debug-phil` prints without a value; empty for all
     */
    public function __construct(
        public bool $timestamps = false,
        public TimestampFormat $format = TimestampFormat::Iso,
        public SummaryMode $summary = SummaryMode::Verbose,
        public array $debugChannels = [],
    ) {
    }
}
