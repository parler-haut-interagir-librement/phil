<?php

declare(strict_types=1);

namespace Phil\SvgChart;

/**
 * The kinds of chart drawn: bars grouped by label, lines through the values, and the shares of a whole as a pie or a
 * doughnut.
 */
enum ChartType: string
{
    case Bar = 'bar';
    case Line = 'line';
    case Pie = 'pie';
    case Doughnut = 'doughnut';

    /**
     * Whether the values stand on axes, rather than as shares of a circle.
     */
    public function hasAxes(): bool
    {
        return self::Bar === $this || self::Line === $this;
    }
}
