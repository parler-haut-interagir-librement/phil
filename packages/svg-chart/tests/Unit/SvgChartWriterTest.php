<?php

declare(strict_types=1);

namespace Phil\SvgChart\Tests\Unit;

use Phil\SvgChart\Chart;
use Phil\SvgChart\ChartType;
use Phil\SvgChart\Dataset;
use Phil\SvgChart\Exception\InvalidChart;
use Phil\SvgChart\Internal\Scale;
use Phil\SvgChart\SvgChartWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The golden images under Fixture/ were looked at when they were written.
 */
#[CoversClass(SvgChartWriter::class)]
#[CoversClass(Scale::class)]
#[CoversClass(ChartType::class)]
#[CoversClass(InvalidChart::class)]
final class SvgChartWriterTest extends TestCase
{
    /**
     * @return iterable<array{string}>
     */
    public static function types(): iterable
    {
        yield ['bar'];
        yield ['line'];
        yield ['pie'];
        yield ['doughnut'];
    }

    #[DataProvider('types')]
    public function testDrawsTheGoldenCharts(string $type): void
    {
        $chart = Chart::fromConfig([
            'type' => $type,
            'data' => [
                'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May'],
                'datasets' => [
                    ['label' => 'Sales', 'data' => [12, 19, 3, 5, -2]],
                    ['label' => 'Costs', 'data' => [7, 11, 5, null, 9.5]],
                ],
            ],
            'options' => ['plugins' => ['title' => ['text' => 'Quarterly & more']]],
        ]);

        self::assertStringEqualsFile(__DIR__.'/Fixture/'.$type.'.svg', new SvgChartWriter()->write($chart));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function cases(): iterable
    {
        /** @var array<string, array{int, int, array<string, mixed>}> $cases */
        $cases = require __DIR__.'/Fixture/cases.php';

        foreach (array_keys($cases) as $name) {
            yield $name => [$name];
        }
    }

    #[DataProvider('cases')]
    public function testDrawsTheGoldenCases(string $name): void
    {
        /** @var array<string, array{int, int, array<string, mixed>}> $cases */
        $cases = require __DIR__.'/Fixture/cases.php';
        [$width, $height, $config] = $cases[$name];

        self::assertStringEqualsFile(__DIR__.'/Fixture/cases/'.$name.'.svg', new SvgChartWriter($width, $height)->write(Chart::fromConfig($config)));
    }

    public function testThinsLabelsAtTheEdgeOfTheirBand(): void
    {
        $chart = new Chart(ChartType::Bar, array_fill(0, 10, 'aaaaa'), [new Dataset('', array_fill(0, 10, 10.0))]);

        // Labels 36 pixels wide, 8 apart, in bands of 44 then 43 pixels.
        self::assertSame(10, substr_count(new SvgChartWriter(483, 300)->write($chart), '>aaaaa<'));
        self::assertSame(5, substr_count(new SvgChartWriter(473, 300)->write($chart), '>aaaaa<'));
    }

    public function testWrapsTheLegendAtTheEdge(): void
    {
        $rows = static function (int $width, int $series): int {
            $svg = new SvgChartWriter($width, 300)->write(new Chart(ChartType::Bar, ['x'], array_fill(0, $series, new Dataset('aaaaa', [1.0]))));
            preg_match_all('/<rect x="[\d.]+" y="(\d+)" width="10" height="10"/', $svg, $entries);

            return \count(array_unique($entries[1]));
        };

        // Each entry is 58 pixels; a row holds the width less 24.
        self::assertSame(2, $rows(139, 2));
        self::assertSame(1, $rows(140, 2));
        self::assertSame(2, $rows(140, 3));
        self::assertSame(4, $rows(139, 4));
    }

    public function testKeepsAPieVisibleWhenTheLegendFillsThePage(): void
    {
        $labels = array_map(static fn (int $i): string => 'Label number '.$i, range(1, 12));
        $svg = new SvgChartWriter(100, 100)->write(new Chart(ChartType::Pie, $labels, [new Dataset('', array_fill(0, 12, 0.0))], 'Title'));

        self::assertMatchesRegularExpression('/<circle cx="50" cy="-?[\d.]+" r="1" fill="none"/', $svg);
    }

    public function testNamesAnUntitledChartByItsKind(): void
    {
        $svg = new SvgChartWriter()->write(new Chart(ChartType::Line, ['a'], [new Dataset('', [1.0])]));

        self::assertStringContainsString('<title>Line chart</title><desc>Line chart. a 1.</desc>', $svg);
        self::assertStringNotContainsString('font-size="16"', $svg);
        self::assertStringNotContainsString('<g><rect', $svg, 'a single unnamed series has no legend');
    }

    public function testDrawsAChartWithoutValues(): void
    {
        $svg = new SvgChartWriter()->write(new Chart(ChartType::Line, [], []));

        self::assertStringContainsString('<desc>Line chart. .</desc>', $svg);
        self::assertStringContainsString('>0.0</text>', $svg);
        self::assertStringContainsString('>1.0</text>', $svg);
        self::assertStringContainsString('<g fill="#444444" text-anchor="middle"></g>', $svg);
    }

    public function testDrawsAnEmptyPieAsARing(): void
    {
        $svg = new SvgChartWriter(200, 200)->write(new Chart(ChartType::Pie, ['a', 'b'], [new Dataset('', [0.0, -3.0])]));

        self::assertStringContainsString('<circle cx="100" cy="', $svg);
        self::assertStringContainsString('fill="none" stroke="#dddddd"/>', $svg);
        self::assertStringNotContainsString('<path', $svg);
    }

    public function testDrawsAWholePieAsTwoHalves(): void
    {
        $pie = new SvgChartWriter(200, 200)->write(new Chart(ChartType::Pie, ['all', 'none'], [new Dataset('', [5.0, null], null, ['#112233'])]));
        $doughnut = new SvgChartWriter(200, 200)->write(new Chart(ChartType::Doughnut, ['all'], [new Dataset('', [5.0])]));

        self::assertMatchesRegularExpression('/<path d="M100 [\d.]+A([\d.]+) \1 0 1 1 100 [\d.]+A\1 \1 0 1 1 100 [\d.]+Z" fill="#112233"><title>all: 5 \(100 %\)<\/title>/', $pie);
        self::assertMatchesRegularExpression('/Z M?M100 [\d.]+A[\d.]+ [\d.]+ 0 1 0 /', str_replace('ZM', 'Z M', $doughnut));
    }

    public function testMarksTheLargeArcOfASliceOverHalf(): void
    {
        $svg = new SvgChartWriter(200, 200)->write(new Chart(ChartType::Pie, ['big', 'small'], [new Dataset('', [3.0, 1.0])]));

        self::assertMatchesRegularExpression('/A[\d.]+ [\d.]+ 0 1 1 [^"]+L100 [\d.]+Z" fill="#0072b2"><title>big: 3 \(75 %\)/', $svg);
        self::assertMatchesRegularExpression('/A[\d.]+ [\d.]+ 0 0 1 [^"]+L100 [\d.]+Z" fill="#e69f00"><title>small: 1 \(25 %\)/', $svg);
    }

    public function testShowsOneLabelInSeveralWhenTheyCrowd(): void
    {
        $labels = array_map(static fn (int $i): string => 'Label '.$i, range(1, 40));
        $svg = new SvgChartWriter()->write(new Chart(ChartType::Bar, $labels, [new Dataset('', array_fill(0, 40, 1.0))]));

        preg_match_all('/>(Label \d+)</', $svg, $shown);

        self::assertSame(['Label 1', 'Label 6', 'Label 11', 'Label 16', 'Label 21', 'Label 26', 'Label 31', 'Label 36'], $shown[1]);
    }

    public function testWrapsTheLegend(): void
    {
        $datasets = array_map(static fn (int $i): Dataset => new Dataset('A long series name '.$i, [1.0]), range(1, 12));
        $svg = new SvgChartWriter(300, 400)->write(new Chart(ChartType::Bar, ['x'], $datasets));

        preg_match_all('/<rect x="[\d.]+" y="(\d+)" width="10" height="10" fill="(#\w+)"/', $svg, $entries);

        self::assertSame(['184', '202', '220', '238', '256', '274', '292', '310', '328', '346', '364', '382'], $entries[1]);
        self::assertSame('#0072b2', $entries[2][0]);
        self::assertSame('#0072b2', $entries[2][8], 'the palette starts over');
    }

    public function testUsesTheColoursGiven(): void
    {
        $svg = new SvgChartWriter(300, 200, ['#AA0000'])->write(new Chart(ChartType::Bar, ['a', 'b'], [new Dataset('one', [1.0, 2.0]), new Dataset('two', [1.0, 2.0], '#00ff00')]));

        self::assertStringContainsString('<g fill="#AA0000"><rect', $svg);
        self::assertStringContainsString('<g fill="#00ff00"><rect', $svg);
        self::assertStringContainsString('<title>two, b: 2</title>', $svg);
    }

    public function testWritesTheFontAndSize(): void
    {
        $svg = new SvgChartWriter(320, 240, SvgChartWriter::PALETTE, 'Inter, sans-serif')->write(new Chart(ChartType::Line, ['a'], [new Dataset('', [1.5])]));

        self::assertStringStartsWith('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 240" role="img" font-family="Inter, sans-serif" font-size="12">', $svg);
        self::assertStringContainsString('<rect width="320" height="240" fill="#ffffff"/>', $svg);
    }

    /**
     * @return iterable<array{int, int, list<string>, string, string}>
     */
    public static function invalid(): iterable
    {
        yield [99, 400, SvgChartWriter::PALETTE, 'sans-serif', '"width" is invalid'];
        yield [4001, 400, SvgChartWriter::PALETTE, 'sans-serif', '"width" is invalid'];
        yield [640, 99, SvgChartWriter::PALETTE, 'sans-serif', '"height" is invalid'];
        yield [640, 4001, SvgChartWriter::PALETTE, 'sans-serif', '"height" is invalid'];
        yield [640, 400, [], 'sans-serif', '"palette" is invalid'];
        yield [640, 400, ['#000000', 'red'], 'sans-serif', '"palette" is invalid'];
        yield [640, 400, SvgChartWriter::PALETTE, 'a"b', '"font" is invalid'];
        yield [640, 400, SvgChartWriter::PALETTE, '', '"font" is invalid'];
        yield [640, 400, SvgChartWriter::PALETTE, "sans\n", '"font" is invalid'];
        yield [640, 400, SvgChartWriter::PALETTE, str_repeat('a', 101), '"font" is invalid'];
        yield [640, 400, ['x#000000'], 'sans-serif', '"palette" is invalid'];
        yield [640, 400, ['#0000001'], 'sans-serif', '"palette" is invalid'];
        yield [640, 400, ["#000000\n"], 'sans-serif', '"palette" is invalid'];
    }

    /**
     * @param list<string> $palette
     */
    #[DataProvider('invalid')]
    public function testRefusesOptionsOutOfRange(int $width, int $height, array $palette, string $font, string $message): void
    {
        $this->expectException(InvalidChart::class);
        $this->expectExceptionMessageIsOrContains($message);

        new SvgChartWriter($width, $height, $palette, $font);
    }

    public function testAcceptsFontsInAnyScript(): void
    {
        self::assertStringContainsString('font-family="Équipe Sans, sans-serif"', new SvgChartWriter(320, 240, SvgChartWriter::PALETTE, 'Équipe Sans, sans-serif')->write(new Chart(ChartType::Bar, [], [])));
        self::assertStringContainsString('font-family="'.str_repeat('a', 100).'"', new SvgChartWriter(320, 240, SvgChartWriter::PALETTE, str_repeat('a', 100))->write(new Chart(ChartType::Bar, [], [])));
    }

    public function testAcceptsTheBoundsOfTheSize(): void
    {
        self::assertStringContainsString('viewBox="0 0 100 4000"', new SvgChartWriter(100, 4000)->write(new Chart(ChartType::Bar, [], [])));
    }

    public function testRoundsTheTicksOfTheScale(): void
    {
        $scale = Scale::covering(0.0, 19.0);

        self::assertSame([0.0, 5.0, 10.0, 15.0, 20.0], $scale->ticks);
        self::assertSame(5.0, $scale->step);
        self::assertSame(0.5, $scale->fraction(10.0));
        self::assertSame('15', $scale->label(15.0));

        $small = Scale::covering(0.1, 0.3);
        self::assertSame([0.1, 0.15, 0.2, 0.25, 0.3], $small->ticks);
        self::assertSame('0.15', $small->label(0.15));

        self::assertSame([3.0, 3.2, 3.4, 3.6, 3.8, 4.0], Scale::covering(3.0, 3.0)->ticks);
        self::assertSame([-10.0, 0.0, 10.0, 20.0], Scale::covering(-2.0, 19.0)->ticks);
        self::assertSame([0.0, 20.0, 40.0, 60.0, 80.0, 100.0], Scale::covering(0.0, 90.0)->ticks);
        self::assertSame([0.0, 2.0, 4.0, 6.0, 8.0], Scale::covering(0.0, 7.0)->ticks);
        self::assertSame([0.0, 1.0, 2.0, 3.0], Scale::covering(0.0, 2.9)->ticks);
        self::assertSame([0.0, 50.0, 100.0, 150.0], Scale::covering(0.0, 140.0)->ticks);
        self::assertSame([0.0, 5.0, 10.0, 15.0, 20.0], Scale::covering(0.0, 16.0)->ticks);
        self::assertSame([64.1, 64.2, 64.3, 64.4, 64.5, 64.6, 64.7], Scale::covering(64.189, 64.602)->ticks, 'a span of 5.9999… steps');
        self::assertSame('0', Scale::covering(-0.0, 4.0)->label(Scale::covering(-0.0, 4.0)->ticks[0]));
        self::assertSame([0.0, 0.2, 0.4, 0.6, 0.8, 1.0], Scale::covering(0.0, 1.0)->ticks);
    }

    public function testRoundsToOneTwoOrFive(): void
    {
        foreach ([[1.4, 1.0], [1.5, 2.0], [2.9, 2.0], [3.0, 5.0], [6.9, 5.0], [7.0, 10.0], [70.0, 100.0], [0.25, 0.2]] as [$value, $nice]) {
            self::assertEqualsWithDelta($nice, Scale::nice($value, true), 1e-9, 'nearest '.$value);
        }

        foreach ([[1.0, 1.0], [1.1, 2.0], [2.0, 2.0], [2.1, 5.0], [5.0, 5.0], [5.1, 10.0], [50.0, 50.0]] as [$value, $nice]) {
            self::assertEqualsWithDelta($nice, Scale::nice($value, false), 1e-9, 'above '.$value);
        }
    }
}
