<?php

declare(strict_types=1);

namespace Phil\SvgChart\Tests\Unit;

use Phil\SvgChart\Chart;
use Phil\SvgChart\ChartType;
use Phil\SvgChart\Dataset;
use Phil\SvgChart\Exception\InvalidChart;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Chart::class)]
#[CoversClass(Dataset::class)]
#[CoversClass(ChartType::class)]
#[CoversClass(InvalidChart::class)]
final class ChartTest extends TestCase
{
    public function testReadsAChartJsConfiguration(): void
    {
        $chart = Chart::fromConfig([
            'type' => 'pie',
            'data' => [
                'labels' => ['Red', 2024, 1.5],
                'datasets' => [
                    ['label' => 'Votes', 'data' => [3, 4.5, null], 'backgroundColor' => ['#FF0000', '#00ff00', '#0000ff']],
                    ['data' => [1, 2, 3], 'backgroundColor' => '#ABCDEF'],
                    ['label' => null, 'data' => [1, 2, 3], 'backgroundColor' => '#000000', 'borderColor' => '#111111'],
                ],
            ],
            'options' => ['responsive' => true, 'plugins' => ['title' => ['display' => true, 'text' => 'Poll']]],
        ]);

        self::assertSame(ChartType::Pie, $chart->type);
        self::assertSame(['Red', '2024', '1.5'], $chart->labels);
        self::assertSame('Poll', $chart->title);
        self::assertEquals(new Dataset('Votes', [3.0, 4.5, null], null, ['#ff0000', '#00ff00', '#0000ff']), $chart->datasets[0]);
        self::assertSame([3.0, 4.5, null], $chart->datasets[0]->values);
        self::assertEquals(new Dataset('', [1.0, 2.0, 3.0], '#abcdef'), $chart->datasets[1]);
        self::assertEquals(new Dataset('', [1.0, 2.0, 3.0], '#111111'), $chart->datasets[2]);
    }

    public function testReadsAMinimalConfiguration(): void
    {
        $chart = Chart::fromConfig(['type' => 'line', 'data' => []]);

        self::assertSame([], $chart->labels);
        self::assertSame([], $chart->datasets);
        self::assertSame('', $chart->title);
        self::assertSame('', Chart::fromConfig(['type' => 'bar', 'data' => [], 'options' => ['plugins' => ['title' => ['text' => 3]]]])->title);
        self::assertSame('', Chart::fromConfig(['type' => 'bar', 'data' => [], 'options' => ['plugins' => 'x']])->title);
        self::assertSame('', Chart::fromConfig(['type' => 'bar', 'data' => [], 'options' => 'x'])->title);
        self::assertSame('', Chart::fromConfig(['type' => 'bar', 'data' => [], 'options' => ['plugins' => ['title' => 'x']]])->title);
        self::assertTrue(ChartType::Bar->hasAxes());
        self::assertTrue(ChartType::Line->hasAxes());
        self::assertFalse(ChartType::Pie->hasAxes());
        self::assertFalse(ChartType::Doughnut->hasAxes());
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function invalidConfigs(): iterable
    {
        $labels = ['labels' => ['a']];

        yield 'no type' => [['data' => []], '"type" is invalid: one of bar, line, pie and doughnut is expected'];
        yield 'unknown type' => [['type' => 'radar', 'data' => []], '"type" is invalid'];
        yield 'type not a text' => [['type' => 1, 'data' => []], '"type" is invalid'];
        yield 'no data' => [['type' => 'bar'], '"data" is invalid: an object is expected'];
        yield 'data a list' => [['type' => 'bar', 'data' => [1]], '"data" is invalid: an object is expected'];
        yield 'labels not a list' => [['type' => 'bar', 'data' => ['labels' => ['x' => 'a']]], '"data.labels" is invalid: a list is expected'];
        yield 'labels a text' => [['type' => 'bar', 'data' => ['labels' => 'a']], '"data.labels" is invalid: a list is expected'];
        yield 'too many labels' => [['type' => 'bar', 'data' => ['labels' => array_fill(0, 501, 'a')]], '"data.labels" is invalid: more than 500 items'];
        yield 'label not a text' => [['type' => 'bar', 'data' => ['labels' => [true]]], '"data.labels.0" is invalid: a text is expected'];
        yield 'too many series' => [['type' => 'bar', 'data' => ['datasets' => array_fill(0, 25, ['data' => []])]], '"data.datasets" is invalid: more than 24 items'];
        yield 'series not an object' => [['type' => 'bar', 'data' => ['datasets' => ['a']]], '"data.datasets.0" is invalid: an object is expected'];
        yield 'value not a number' => [['type' => 'bar', 'data' => $labels + ['datasets' => [['data' => ['1']]]]], '"data.datasets.0.data.0" is invalid: a number or null is expected'];
        yield 'value infinite' => [['type' => 'bar', 'data' => $labels + ['datasets' => [['data' => [\INF]]]]], '"data.datasets.0.data.0" is invalid'];
        yield 'value NaN' => [['type' => 'bar', 'data' => $labels + ['datasets' => [['data' => [\NAN]]]]], '"data.datasets.0.data.0" is invalid'];
        yield 'colour misshaped' => [['type' => 'bar', 'data' => $labels + ['datasets' => [['data' => [1], 'backgroundColor' => 'red']]]], '"data.datasets.0.backgroundColor" is invalid: a colour written #rrggbb is expected'];
        yield 'border misshaped' => [['type' => 'bar', 'data' => $labels + ['datasets' => [['data' => [1], 'borderColor' => '#12345']]]], '"data.datasets.0.borderColor" is invalid'];
        yield 'colour with a line feed' => [['type' => 'bar', 'data' => $labels + ['datasets' => [['data' => [1], 'borderColor' => "#123456\n"]]]], '"data.datasets.0.borderColor" is invalid'];
        yield 'colour not a text' => [['type' => 'pie', 'data' => $labels + ['datasets' => [['data' => [1], 'backgroundColor' => [1]]]]], '"data.datasets.0.backgroundColor.0" is invalid'];
        yield 'series label not a text' => [['type' => 'bar', 'data' => $labels + ['datasets' => [['label' => [], 'data' => [1]]]]], '"data.datasets.0.label" is invalid'];
        yield 'series data not a list' => [['type' => 'bar', 'data' => $labels + ['datasets' => [['data' => 'x']]]], '"data.datasets.0.data" is invalid: a list is expected'];
        yield 'colours not a list' => [['type' => 'pie', 'data' => $labels + ['datasets' => [['data' => [1], 'backgroundColor' => ['a' => '#000000']]]]], '"data.datasets.0.backgroundColor" is invalid: a list is expected'];
        yield 'series label too long' => [['type' => 'bar', 'data' => $labels + ['datasets' => [['label' => str_repeat('a', 201), 'data' => [1]]]]], '"data.datasets.0.label" is invalid: longer than 200 characters'];
        yield 'values not matching labels' => [['type' => 'bar', 'data' => $labels + ['datasets' => [['data' => [1, 2]]]]], '"data.datasets.0.data" is invalid: 2 values for 1 labels'];
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[DataProvider('invalidConfigs')]
    public function testRefusesAMisshapedConfiguration(array $config, string $message): void
    {
        $this->expectException(InvalidChart::class);
        $this->expectExceptionMessageIsOrContains($message);

        Chart::fromConfig($config);
    }

    public function testRefusesMoreThanTheLimits(): void
    {
        $refused = 0;

        foreach ([
            static fn (): Chart => new Chart(ChartType::Bar, array_fill(0, 501, 'a'), []),
            static fn (): Chart => new Chart(ChartType::Bar, [], array_fill(0, 25, new Dataset('', []))),
            static fn (): Chart => new Chart(ChartType::Bar, [str_repeat('é', 201)], []),
            static fn (): Chart => new Chart(ChartType::Bar, [], [], str_repeat('é', 201)),
            static fn (): Chart => new Chart(ChartType::Bar, [], [new Dataset(str_repeat('é', 201), [])]),
        ] as $chart) {
            try {
                $chart();
            } catch (InvalidChart) {
                ++$refused;
            }
        }

        self::assertSame(5, $refused);
        self::assertCount(500, Chart::fromConfig(['type' => 'bar', 'data' => ['labels' => array_fill(0, 500, 'a'), 'datasets' => array_fill(0, 24, ['data' => array_fill(0, 500, 1)])]])->labels);
        self::assertCount(500, new Chart(ChartType::Bar, array_fill(0, 500, str_repeat('é', 200)), array_fill(0, 24, new Dataset(str_repeat('é', 200), array_fill(0, 500, 1.0))), str_repeat('é', 200))->labels);
    }

    public function testRefusesAColourBuiltDirectlyThatIsNotRrggbb(): void
    {
        foreach ([
            'data.datasets.1.colour' => static fn (): Chart => new Chart(ChartType::Bar, ['a'], [new Dataset('', [1.0], '#ABCDEF'), new Dataset('', [1.0], '#fff" onload="alert(1)')]),
            'data.datasets.0.colours.1' => static fn (): Chart => new Chart(ChartType::Pie, ['a', 'b'], [new Dataset('', [1.0, 2.0], null, ['#000000', '#fff"><script>alert(1)</script>'])]),
        ] as $path => $chart) {
            try {
                $chart();
                self::fail(\sprintf('The colour at %s was expected to be refused.', $path));
            } catch (InvalidChart $refused) {
                self::assertStringContainsString($path, $refused->getMessage());
                self::assertStringContainsString('a colour written #rrggbb is expected', $refused->getMessage());
            }
        }

        self::assertSame(['#000000', '#FFFFFF'], new Chart(ChartType::Pie, ['a', 'b'], [new Dataset('', [1.0, 2.0], '#123abc', ['#000000', '#FFFFFF'])])->datasets[0]->colours);
    }
}
