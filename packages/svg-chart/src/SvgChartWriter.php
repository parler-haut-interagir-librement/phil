<?php

declare(strict_types=1);

namespace Phil\SvgChart;

use Closure;
use Phil\SvgChart\Exception\InvalidChart;
use Phil\SvgChart\Internal\Scale;

/**
 * A chart as a standalone SVG image, the same bytes for the same chart: the title above, the legend below, bars or
 * lines over a value axis with round ticks, or the slices of a pie or a doughnut. Every mark carries a `<title>` with
 * its label and value, which browsers show on hover; the image carries `role="img"`, the chart's title as its own,
 * and a `<desc>` that reads every value for screen readers.
 *
 * Text is measured by an estimate of its width, so labels too dense for their band are shown one in two, or fewer.
 */
final readonly class SvgChartWriter
{
    /** The Okabe and Ito colours, told apart by every common colour vision. */
    public const array PALETTE = ['#0072b2', '#e69f00', '#009e73', '#cc79a7', '#56b4e9', '#d55e00', '#f0e442', '#000000'];

    private const int FONT = 12;

    private const int LEGEND_ROW = 18;

    /**
     * @param list<string> $palette the colours of the series, or of the slices, in turn
     *
     * @throws InvalidChart when the size is out of 100 to 4000 pixels or the palette is empty
     */
    public function __construct(
        private int $width = 640,
        private int $height = 400,
        private array $palette = self::PALETTE,
        private string $font = 'system-ui, sans-serif',
    ) {
        foreach (['width' => $width, 'height' => $height] as $name => $size) {
            if ($size < 100 || $size > 4000) {
                throw InvalidChart::at($name, 'from 100 to 4000 pixels is expected');
            }
        }

        if ([] === $palette || !array_all($palette, static fn (string $colour): bool => 1 === preg_match('/^#[0-9a-fA-F]{6}$/D', $colour))) {
            throw InvalidChart::at('palette', 'at least one colour written #rrggbb is expected');
        }

        if (1 !== preg_match('/^[\w ,\-]{1,100}$/uD', $font)) {
            throw InvalidChart::at('font', 'a list of font families, by letters, digits, spaces, commas and dashes, is expected');
        }
    }

    public function write(Chart $chart): string
    {
        $top = '' === $chart->title ? 12 : 36;
        $legend = $this->legend($chart);
        $bottom = $this->height - 8 - \count($legend) * self::LEGEND_ROW;
        $body = $chart->type->hasAxes() ? $this->axes($chart, $top, $bottom) : $this->circle($chart, $top, $bottom);

        return \sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$d %2$d" role="img" font-family="%3$s" font-size="%4$d"><title>%5$s</title><desc>%6$s</desc><rect width="%1$d" height="%2$d" fill="#ffffff"/>%7$s%8$s%9$s</svg>',
            $this->width,
            $this->height,
            $this->escape($this->font),
            self::FONT,
            $this->escape('' === $chart->title ? $this->summaryTitle($chart) : $chart->title),
            $this->escape($this->description($chart)),
            '' === $chart->title ? '' : \sprintf('<text x="%s" y="24" text-anchor="middle" font-size="16" font-weight="bold" fill="#222222">%s</text>', self::number($this->width / 2), $this->escape($chart->title)),
            $body,
            $this->drawLegend($legend, $bottom + 8),
        );
    }

    private function axes(Chart $chart, int $top, int $bottom): string
    {
        $values = [];

        foreach ($chart->datasets as $dataset) {
            foreach ($dataset->values as $value) {
                if (null !== $value) {
                    $values[] = $value;
                }
            }
        }

        $bars = ChartType::Bar === $chart->type;
        // Bars stand on zero; lines span their values, from zero when there are none.
        /** @var non-empty-list<float> $values bars add zero, and so does a chart without values */
        $values = [...$values, ...$bars || [] === $values ? [0.0] : []];
        $scale = Scale::covering(min($values), max($values));
        /** @var non-empty-list<int> $widths one per tick, and a scale has at least one */
        $widths = array_map(fn (float $tick): int => $this->measure($scale->label($tick)), $scale->ticks);
        $left = 10 + max($widths) + 6;
        $right = $this->width - 12;
        $plotBottom = $bottom - 22;
        $y = static fn (float $value): float => $plotBottom - $scale->fraction($value) * ($plotBottom - $top);
        $svg = '<g stroke="#dddddd" stroke-width="1">';
        $labels = '<g fill="#444444" text-anchor="end">';

        foreach ($scale->ticks as $tick) {
            $svg .= \sprintf('<line x1="%s" y1="%2$s" x2="%3$s" y2="%2$s"/>', $left, self::number($y($tick)), $right);
            $labels .= \sprintf('<text x="%s" y="%s">%s</text>', $left - 6, self::number($y($tick) + 4), $scale->label($tick));
        }

        $count = max(1, \count($chart->labels));
        $band = ($right - $left) / $count;
        $svg .= '</g>'.$labels.'</g>'.$this->categories($chart->labels, $left, $band, $plotBottom + 16);
        $baseline = $y(max($scale->min, min($scale->max, 0.0)));

        foreach ($chart->datasets as $index => $dataset) {
            $colour = $dataset->colour ?? $this->palette[$index % \count($this->palette)];
            $svg .= $bars
                ? $this->bars($chart, $dataset, $index, $colour, $left, $band, $y, $baseline)
                : $this->line($chart, $dataset, $colour, $left, $band, $y);
        }

        return $svg.\sprintf('<line x1="%1$s" y1="%2$s" x2="%3$s" y2="%2$s" stroke="#888888" stroke-width="1"/>', $left, self::number($baseline), $right);
    }

    /**
     * @param list<string> $labels
     */
    private function categories(array $labels, float $left, float $band, float $y): string
    {
        $widest = max([0, ...array_map($this->measure(...), $labels)]);
        $every = max(1, (int) ceil(($widest + 8) / $band));
        $svg = '<g fill="#444444" text-anchor="middle">';

        foreach ($labels as $i => $label) {
            if (0 === $i % $every) {
                $svg .= \sprintf('<text x="%s" y="%s">%s</text>', self::number($left + ($i + 0.5) * $band), self::number($y), $this->escape($label));
            }
        }

        return $svg.'</g>';
    }

    /**
     * @param Closure(float): float $y
     */
    private function bars(Chart $chart, Dataset $dataset, int $index, string $colour, float $left, float $band, Closure $y, float $baseline): string
    {
        $width = $band * 0.8 / \count($chart->datasets);
        $svg = \sprintf('<g fill="%s">', $colour);

        foreach ($dataset->values as $i => $value) {
            if (null !== $value) {
                $top = min($y($value), $baseline);
                $svg .= \sprintf(
                    '<rect x="%s" y="%s" width="%s" height="%s"><title>%s</title></rect>',
                    self::number($left + $i * $band + $band * 0.1 + $index * $width),
                    self::number($top),
                    self::number($width),
                    self::number(abs($y($value) - $baseline)),
                    $this->escape($this->point($dataset, $chart->labels[$i], $value)),
                );
            }
        }

        return $svg.'</g>';
    }

    /**
     * @param Closure(float): float $y
     */
    private function line(Chart $chart, Dataset $dataset, string $colour, float $left, float $band, Closure $y): string
    {
        $path = '';
        $points = '';
        $drawing = false;

        foreach ($dataset->values as $i => $value) {
            if (null === $value) {
                $drawing = false;

                continue;
            }

            $x = self::number($left + ($i + 0.5) * $band);
            $py = self::number($y($value));
            $path .= ($drawing ? 'L' : 'M').$x.' '.$py;
            $drawing = true;
            $points .= \sprintf('<circle cx="%s" cy="%s" r="3"><title>%s</title></circle>', $x, $py, $this->escape($this->point($dataset, $chart->labels[$i], $value)));
        }

        return \sprintf('<path d="%s" fill="none" stroke="%2$s" stroke-width="2"/><g fill="%2$s">%3$s</g>', $path, $colour, $points);
    }

    /**
     * The slices of the first series, from the top, clockwise; a negative or missing value counts as nothing.
     */
    private function circle(Chart $chart, int $top, int $bottom): string
    {
        $dataset = $chart->datasets[0] ?? new Dataset('', []);
        $values = array_map(static fn (?float $value): float => max(0.0, $value ?? 0.0), $dataset->values);
        $total = array_sum($values);
        $cx = $this->width / 2;
        $cy = ($top + $bottom) / 2;
        $radius = max(1.0, min($this->width - 24, $bottom - $top) / 2 - 4);
        $inner = ChartType::Doughnut === $chart->type ? $radius * 0.55 : 0.0;

        if ($total <= 0.0) {
            return \sprintf('<circle cx="%s" cy="%s" r="%s" fill="none" stroke="#dddddd"/>', self::number($cx), self::number($cy), self::number($radius));
        }

        $svg = '<g stroke="#ffffff" stroke-width="1">';
        $angle = 0.0;

        foreach ($values as $i => $value) {
            if ($value <= 0.0) {
                continue;
            }

            $sweep = $value / $total * 2 * \M_PI;
            $colour = $dataset->colours[$i] ?? $this->palette[$i % \count($this->palette)];
            $svg .= \sprintf('<path d="%s" fill="%s"><title>%s</title></path>', $this->slice($cx, $cy, $radius, $inner, $angle, $sweep, $value >= $total), $colour, $this->escape($this->point($dataset, $chart->labels[$i], $value, $value / $total)));
            $angle += $sweep;
        }

        return $svg.'</g>';
    }

    private function slice(float $cx, float $cy, float $radius, float $inner, float $from, float $sweep, bool $whole): string
    {
        // A whole circle is two half arcs: an arc cannot end where it starts.
        if ($whole) {
            $ring = \sprintf('M%1$s %2$sA%3$s %3$s 0 1 1 %1$s %4$sA%3$s %3$s 0 1 1 %1$s %2$sZ', self::number($cx), self::number($cy - $radius), self::number($radius), self::number($cy + $radius));

            return $inner > 0.0 ? $ring.\sprintf('M%1$s %2$sA%3$s %3$s 0 1 0 %1$s %4$sA%3$s %3$s 0 1 0 %1$s %2$sZ', self::number($cx), self::number($cy - $inner), self::number($inner), self::number($cy + $inner)) : $ring;
        }

        $point = static fn (float $r, float $a): string => self::number($cx + $r * sin($a)).' '.self::number($cy - $r * cos($a));
        $large = $sweep > \M_PI ? 1 : 0;
        $outer = \sprintf('M%sA%s %2$s 0 %d 1 %s', $point($radius, $from), self::number($radius), $large, $point($radius, $from + $sweep));

        if ($inner <= 0.0) {
            return $outer.\sprintf('L%s %sZ', self::number($cx), self::number($cy));
        }

        return $outer.\sprintf('L%sA%s %2$s 0 %d 0 %sZ', $point($inner, $from + $sweep), self::number($inner), $large, $point($inner, $from));
    }

    /**
     * The entries of the legend, in rows that fit the width: the series of a chart with axes when it has more than
     * one or a named one, the labels of a pie or a doughnut.
     *
     * @return list<list<array{string, string}>>
     */
    private function legend(Chart $chart): array
    {
        $entries = [];

        if ($chart->type->hasAxes()) {
            if (\count($chart->datasets) > 1 || '' !== ($chart->datasets[0]->label ?? '')) {
                foreach ($chart->datasets as $i => $dataset) {
                    $entries[] = [$dataset->label, $dataset->colour ?? $this->palette[$i % \count($this->palette)]];
                }
            }
        } else {
            foreach ($chart->labels as $i => $label) {
                $entries[] = [$label, $chart->datasets[0]->colours[$i] ?? $this->palette[$i % \count($this->palette)]];
            }
        }

        $rows = [];
        $row = [];
        $used = 0;

        foreach ($entries as $entry) {
            $width = 22 + $this->measure($entry[0]);

            if ([] !== $row && $used + $width > $this->width - 24) {
                $rows[] = $row;
                $row = [];
                $used = 0;
            }

            $row[] = $entry;
            $used += $width;
        }

        return [] === $row ? $rows : [...$rows, $row];
    }

    /**
     * @param list<list<array{string, string}>> $rows
     */
    private function drawLegend(array $rows, int $top): string
    {
        $svg = '';

        foreach ($rows as $r => $row) {
            $width = array_sum(array_map(fn (array $entry): int => 22 + $this->measure($entry[0]), $row)) - 8;
            $x = ($this->width - $width) / 2;
            $y = $top + $r * self::LEGEND_ROW;

            foreach ($row as [$label, $colour]) {
                $svg .= \sprintf('<rect x="%s" y="%d" width="10" height="10" fill="%s"/><text x="%s" y="%d" fill="#444444">%s</text>', self::number($x), $y, $colour, self::number($x + 14), $y + 9, $this->escape($label));
                $x += 22 + $this->measure($label);
            }
        }

        return '' === $svg ? '' : '<g>'.$svg.'</g>';
    }

    private function point(Dataset $dataset, string $label, float $value, ?float $share = null): string
    {
        $text = ('' === $dataset->label ? '' : $dataset->label.', ').$label.': '.$this->value($value);

        return null === $share ? $text : $text.' ('.$this->value(round($share * 100, 1)).' %)';
    }

    private function description(Chart $chart): string
    {
        $parts = [];

        foreach ($chart->datasets as $dataset) {
            $points = [];

            foreach ($dataset->values as $i => $value) {
                $points[] = $chart->labels[$i].' '.(null === $value ? '—' : $this->value($value));
            }

            $parts[] = ('' === $dataset->label ? '' : $dataset->label.': ').implode(', ', $points);
        }

        return ucfirst($chart->type->value).' chart. '.implode('; ', $parts).'.';
    }

    private function summaryTitle(Chart $chart): string
    {
        return ucfirst($chart->type->value).' chart';
    }

    /**
     * An estimate of the width of a text in pixels: 0.6 em for each character.
     */
    private function measure(string $text): int
    {
        return (int) ceil(mb_strlen($text) * self::FONT * 0.6);
    }

    private function value(float $value): string
    {
        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
    }

    /**
     * A coordinate, to the hundredth of a pixel.
     */
    private static function number(float|int $value): string
    {
        $text = rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');

        return '-0' === $text ? '0' : $text;
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, \ENT_QUOTES | \ENT_SUBSTITUTE | \ENT_XML1, 'UTF-8');
    }
}
