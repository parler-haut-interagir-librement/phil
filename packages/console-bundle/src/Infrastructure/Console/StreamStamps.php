<?php

declare(strict_types=1);

namespace Phil\ConsoleBundle\Infrastructure\Console;

use DateTimeImmutable;
use Phil\ConsoleBundle\Domain\TimestampFormat;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

/**
 * Attaches the timestamps to the streams of an output — the standard output and, for a console output, the error
 * output, each with its own stamper — and detaches them.
 *
 * The time is added to the bytes of the stream, after the formatter: the widths of tables and blocks stay those of their
 * text, the messages hidden by the verbosity never reach the stream, and the time is coloured only on a decorated
 * output. An output that writes to no stream gets no time.
 */
final readonly class StreamStamps
{
    public function __construct(
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return list<resource> The filters attached, to detach at the end of the run
     */
    public function attach(OutputInterface $output, TimestampFormat $format, DateTimeImmutable $start): array
    {
        if (!\in_array(TimestampFilter::NAME, stream_get_filters(), true)) {
            stream_filter_register(TimestampFilter::NAME, TimestampFilter::class);
        }

        $outputs = [$output];

        if ($output instanceof ConsoleOutputInterface) {
            $outputs[] = $output->getErrorOutput();
        }

        $filters = [];
        $streams = [];

        foreach ($outputs as $one) {
            if (!$one instanceof StreamOutput || \in_array($one->getStream(), $streams, true)) {
                continue;
            }

            $streams[] = $one->getStream();
            $stamper = new LineStamper($format, $this->clock, $start, $one->isDecorated());
            $filter = stream_filter_append($one->getStream(), TimestampFilter::NAME, \STREAM_FILTER_WRITE, $stamper);

            if (\is_resource($filter)) {
                $filters[] = $filter;
            }
        }

        return $filters;
    }

    /**
     * @param list<resource> $filters
     */
    public function detach(array $filters): void
    {
        foreach ($filters as $filter) {
            if (\is_resource($filter)) {
                stream_filter_remove($filter);
            }
        }
    }
}
