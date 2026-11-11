<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Application\View;

use Phil\ReportingBundle\Domain\ValueObject\Series;
use Phil\ReportingBundle\Domain\ValueObject\SeriesSet;
use Phil\SvgChart\Chart;
use Phil\SvgChart\ChartType;
use Phil\SvgChart\Dataset;
use Phil\SvgChart\SvgChartWriter;

/**
 * A set of series drawn for a page: as an SVG image by phil/svg-chart — `role="img"`, a title, and a description that
 * reads every value —, or as the rows of horizontal bars, each value with its share of the largest one.
 */
final readonly class ChartDrawing
{
    private SvgChartWriter $writer;

    public function __construct(int $width = 640, int $height = 320)
    {
        $this->writer = new SvgChartWriter($width, $height);
    }

    public function svg(SeriesSet $set, ChartType $type, string $title): string
    {
        return $this->writer->write(new Chart(
            $type,
            $set->labels,
            array_map(static fn (Series $series): Dataset => new Dataset($series->label, $series->values), $set->series),
            mb_substr($title, 0, Chart::MAX_TEXT),
        ));
    }

    /**
     * One row per label, one bar per series: its value, and its length in whole hundredths of the largest magnitude,
     * which the stylesheet reads from `data-share`.
     *
     * @return list<array{label: string, bars: list<array{series: string, value: float|null, share: int, negative: bool}>}>
     */
    public function bars(SeriesSet $set): array
    {
        $largest = $set->largest();
        $rows = [];

        foreach ($set->labels as $i => $label) {
            $bars = [];

            foreach ($set->series as $series) {
                $value = $series->values[$i];
                $bars[] = [
                    'series' => $series->label,
                    'value' => $value,
                    'share' => 0.0 === $largest ? 0 : (int) round(abs($value ?? 0.0) / $largest * 100),
                    'negative' => $value < 0,
                ];
            }

            $rows[] = ['label' => $label, 'bars' => $bars];
        }

        return $rows;
    }
}
