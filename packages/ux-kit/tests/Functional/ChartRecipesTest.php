<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * The two charts: the SVG drawn by the server, and the canvas of Symfony UX Chart.js; each with the table of its
 * values, the reading screen readers get.
 */
#[CoversNothing]
final class ChartRecipesTest extends KitTestCase
{
    public function testABarChartOnAScaleOfPlainSteps(): void
    {
        $html = $this->render('<twig:Chart label="Visits" :labels="[\'Jan\', \'Feb\', \'Mar\']" :series="[{label: \'Visits\', values: [12, 30, 21]}]" :width="300" :height="200" />');

        self::assertStringStartsWith('<figure data-slot="chart" data-type="bar" class="isChart"><figcaption class="isChartCaption">Visits</figcaption><svg class="isChartImage" viewBox="0 0 300 200" width="300" height="200" aria-hidden="true" focusable="false">', $html);
        self::assertSame(['0', '10', '20', '30'], $this->ticks($html));
        self::assertStringContainsString('<line class="isChartGrid" x1="48" x2="292" y1="172" y2="172"></line>', $html);
        self::assertStringContainsString('<text class="isChartTick" x="88.67" y="192" text-anchor="middle">Jan</text>', $html);
        self::assertStringContainsString('<g class="isChartSeries isPrimary" data-series="1"><rect class="isChartBar" x="56.13" y="106.4" width="65.07" height="65.6"></rect><rect class="isChartBar" x="137.47" y="8" width="65.07" height="164"></rect><rect class="isChartBar" x="218.8" y="57.2" width="65.07" height="114.8"></rect></g>', $html);
        self::assertStringContainsString('<line class="isChartAxis" x1="48" x2="292" y1="172" y2="172"></line></svg>', $html);
        self::assertStringNotContainsString('isChartLegend', $html);
        self::assertStringEndsWith('<div class="isVisuallyHidden"><table class="isChartTable"><thead><tr><td></td><th scope="col">Visits</th></tr></thead><tbody><tr><th scope="row">Jan</th><td>12</td></tr><tr><th scope="row">Feb</th><td>30</td></tr><tr><th scope="row">Mar</th><td>21</td></tr></tbody></table></div></figure>', $html);
    }

    public function testALineChartBreaksAtAMissingValueAndShowsItsTable(): void
    {
        $html = $this->render('<twig:Chart type="line" label="Two" :labels="[\'A\', \'B\', \'C\']" :series="[{label: \'X\', values: [1, null, 3]}, {label: \'Y\', values: [-2, 0.5]}]" tableLabel="Values" />');

        self::assertSame(['-2', '0', '2', '4'], $this->ticks($html));
        self::assertStringContainsString('<path class="isChartLine isPrimary" data-series="1" d="M145.33 150 l0 0 M534.67 55.33 l0 0"></path>', $html);
        self::assertStringContainsString('<path class="isChartLine isInfo" data-series="2" d="M145.33 292 l0 0 L340 173.67"></path>', $html);
        self::assertStringContainsString('<line class="isChartAxis" x1="48" x2="632" y1="197.33" y2="197.33"></line>', $html);
        self::assertStringContainsString('<ul class="isChartLegend" aria-hidden="true"><li class="isPrimary" data-series="1"><span class="isChartSwatch"></span>X</li><li class="isInfo" data-series="2"><span class="isChartSwatch"></span>Y</li></ul>', $html);
        self::assertStringContainsString('<details class="isChartData"><summary>Values</summary><table class="isChartTable"><thead><tr><td></td><th scope="col">X</th><th scope="col">Y</th></tr></thead><tbody><tr><th scope="row">A</th><td>1</td><td>-2</td></tr><tr><th scope="row">B</th><td></td><td>0.5</td></tr><tr><th scope="row">C</th><td>3</td><td></td></tr></tbody></table></details>', $html);
    }

    public function testAnUnknownTypeDrawsBars(): void
    {
        self::assertStringContainsString('data-type="bar"', $this->render('<twig:Chart type="pie" label="L" :labels="[\'A\']" :series="[{label: \'X\', values: [1]}]" />'));
    }

    public function testBarsOfSeveralSeriesShareTheirBand(): void
    {
        $html = $this->render('<twig:Chart label="L" :labels="[\'A\']" :series="[{label: \'X\', values: [2]}, {label: \'Y\', values: [-1]}]" :width="148" :height="128" />');

        self::assertStringContainsString('<g class="isChartSeries isPrimary" data-series="1"><rect class="isChartBar" x="57.2" y="8" width="36.8" height="61.33"></rect></g>', $html);
        self::assertStringContainsString('<g class="isChartSeries isInfo" data-series="2"><rect class="isChartBar" x="94" y="69.33" width="36.8" height="30.67"></rect></g>', $html);
    }

    public function testAFlatChartHasAStepOfOne(): void
    {
        self::assertSame(['0', '1'], $this->ticks($this->render('<twig:Chart label="Zero" :labels="[\'A\']" :series="[{label: \'X\', values: [0]}]" />')));
    }

    public function testSmallValuesHaveDecimals(): void
    {
        self::assertSame(['0.0', '0.1', '0.2', '0.3'], $this->ticks($this->render('<twig:Chart label="L" :labels="[\'A\']" :series="[{label: \'X\', values: [0.3]}]" />')));
        self::assertSame(['0.00', '0.01', '0.02', '0.03'], $this->ticks($this->render('<twig:Chart label="L" :labels="[\'A\']" :series="[{label: \'X\', values: [0.03]}]" />')));
        self::assertSame(['0.000', '0.001', '0.002', '0.003'], $this->ticks($this->render('<twig:Chart label="L" :labels="[\'A\']" :series="[{label: \'X\', values: [0.003]}]" />')));
    }

    public function testHugeValuesKeepAFewSteps(): void
    {
        self::assertCount(5, $this->ticks($this->render('<twig:Chart label="L" :labels="[\'A\', \'B\']" :series="[{label: \'X\', values: [0, 1e20]}]" />')));
    }

    public function testTheLabelsAndSeriesAreBounded(): void
    {
        $html = $this->render('<twig:Chart label="L" :labels="labels" :series="series" />', [
            'labels' => array_map(static fn (int $day): string => 'd'.$day, range(1, 400)),
            'series' => array_map(static fn (int $series): array => ['label' => 's'.$series, 'values' => range(1, 400)], range(1, 8)),
        ]);

        self::assertSame(366, substr_count($html, '<th scope="row">'));
        self::assertStringContainsString('<th scope="row">d35</th><td>35</td>', $html);
        self::assertStringNotContainsString('>d34<', $html);
        self::assertSame(6, substr_count($html, '<th scope="col">'));
        self::assertSame(12, substr_count($html, 'text-anchor="middle"'));
    }

    public function testAChartJsChartInAFigureWithItsTable(): void
    {
        self::assertSame(
            '<figure data-slot="chart" data-type="chart-js" class="isChart hasChartJs"><figcaption class="isChartCaption">Visits</figcaption><div class="isChartCanvas"><canvas data-controller="symfony--ux-chartjs--chart" aria-hidden="true"></canvas></div><div class="isVisuallyHidden"><table class="isChartTable"><thead><tr><td></td><th scope="col">Visits</th></tr></thead><tbody><tr><th scope="row">Jan</th><td>1</td></tr><tr><th scope="row">Feb</th><td>2</td></tr></tbody></table></div></figure>',
            $this->render('<twig:ChartJs :chart="{data: {labels: [\'Jan\', \'Feb\'], datasets: [{label: \'Visits\', data: [1, 2]}]}}" label="Visits" />'),
        );
        self::assertStringContainsString(
            '<details class="isChartData"><summary>Data</summary><table class="isChartTable"><thead><tr><td></td><th scope="col"></th></tr></thead><tbody><tr><th scope="row">Jan</th><td>1</td></tr></tbody></table></details>',
            $this->render('<twig:ChartJs :chart="{data: {labels: [\'Jan\'], datasets: [{data: [1]}]}}" label="Visits" tableLabel="Data" />'),
        );
        self::assertStringContainsString('<tbody></tbody>', $this->render('<twig:ChartJs :chart="{}" label="Empty" />'));
    }

    /**
     * The values written along the vertical axis, bottom up.
     *
     * @return list<string>
     */
    private function ticks(string $html): array
    {
        preg_match_all('/<text class="isChartTick" x="42" [^>]*>([^<]*)</', $html, $ticks);

        return $ticks[1];
    }
}
