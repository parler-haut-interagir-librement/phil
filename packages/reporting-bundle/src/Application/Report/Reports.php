<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Application\Report;

use Generator;
use Phil\ReportingBundle\Application\Contract\ReportDefinition;
use Phil\ReportingBundle\Application\Contract\ReportFormat;
use Phil\ReportingBundle\Application\Exception\InvalidReportJob;
use Phil\ReportingBundle\Application\Exception\NotFound;
use Phil\ReportingBundle\Application\Exception\ReportTooLarge;
use Symfony\Contracts\Service\ServiceProviderInterface;

/**
 * The reports and the formats by their keys, and a report written in a format to a stream, row by row, stopped at the
 * configured number of rows.
 */
final readonly class Reports
{
    /**
     * @param ServiceProviderInterface<ReportDefinition> $definitions
     * @param ServiceProviderInterface<ReportFormat>     $formats
     */
    public function __construct(
        private ServiceProviderInterface $definitions,
        private ServiceProviderInterface $formats,
        private int $maxRows = 100_000,
    ) {
    }

    /**
     * @throws NotFound when no report has that key
     */
    public function definition(string $key): ReportDefinition
    {
        return $this->definitions->has($key) ? $this->definitions->get($key) : throw NotFound::of('report', $key);
    }

    /**
     * @throws NotFound when no format has that key
     */
    public function format(string $key): ReportFormat
    {
        return $this->formats->has($key) ? $this->formats->get($key) : throw NotFound::of('report format', $key);
    }

    /**
     * The keys of the reports, in order.
     *
     * @return list<string>
     */
    public function keys(): array
    {
        $keys = array_map(strval(...), array_keys($this->definitions->getProvidedServices()));
        sort($keys);

        return $keys;
    }

    /**
     * The name of the file: the report's key, the first and the last day of the period, and the format's extension —
     * `sales-20261001-20261031.csv`.
     */
    public function filename(string $report, string $format, ReportRequest $request): string
    {
        return \sprintf(
            '%s-%s-%s.%s',
            $report,
            $request->period->start->format('Ymd'),
            $request->period->end->modify('-1 second')->format('Ymd'),
            $this->format($format)->extension(),
        );
    }

    /**
     * Writes the report in the format to the stream and gives its number of rows.
     *
     * @throws NotFound         when the report or the format is unknown
     * @throws ReportTooLarge   at the first row beyond the configured number
     * @throws InvalidReportJob at the first row without one cell per column
     */
    public function write(string $report, string $format, ReportRequest $request, ReportStream $stream): int
    {
        $definition = $this->definition($report);
        $writer = $this->format($format);
        $columns = $definition->columns($request);
        $rows = $this->bounded($report, \count($columns), $definition->rows($request));

        $writer->write($stream, $columns, $rows);

        return $rows->getReturn();
    }

    /**
     * @param iterable<list<bool|float|int|string|null>> $rows
     *
     * @return Generator<int, list<bool|float|int|string|null>, mixed, int>
     */
    private function bounded(string $report, int $columns, iterable $rows): Generator
    {
        $count = 0;

        foreach ($rows as $row) {
            if ($count === $this->maxRows) {
                throw ReportTooLarge::of($report, $this->maxRows);
            }

            if (\count($row) !== $columns) {
                throw InvalidReportJob::because(\sprintf('the row %d of the report "%s" has %d cells for %d columns', $count + 1, $report, \count($row), $columns));
            }

            ++$count;

            yield $row;
        }

        return $count;
    }
}
