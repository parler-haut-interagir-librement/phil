<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Tests\Unit\Application;

use Closure;
use Phil\ReportingBundle\Application\Exception\InvalidReportJob;
use Phil\ReportingBundle\Application\Report\ReportRequest;
use Phil\ReportingBundle\Application\Training\CurrencyAmounts;
use Phil\ReportingBundle\Application\Training\QuebecTrainingReport;
use Phil\ReportingBundle\Application\Training\TrainingHeadings;
use Phil\ReportingBundle\Application\Training\TrainingHoursReport;
use Phil\ReportingBundle\Application\Training\TrainingLedger;
use Phil\ReportingBundle\Application\Training\TrainingParameters;
use Phil\ReportingBundle\Domain\Exception\InvalidFigures;
use Phil\ReportingBundle\Domain\Training\TrainingActivity;
use Phil\ReportingBundle\Tests\Unit\Fixture\Payrolls;
use Phil\ReportingBundle\Tests\Unit\Fixture\RecordingTranslator;
use Phil\ReportingBundle\Tests\Unit\Fixture\Trainings;
use Phil\ReportingBundle\Tests\Unit\Fixture\Units;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(TrainingHoursReport::class)]
#[CoversClass(QuebecTrainingReport::class)]
#[CoversClass(TrainingHeadings::class)]
#[CoversClass(TrainingParameters::class)]
#[CoversClass(CurrencyAmounts::class)]
final class TrainingReportsTest extends TestCase
{
    public function testTheHoursAndCostsByQuarterAndUnit(): void
    {
        $report = new TrainingHoursReport(new TrainingLedger($this->trainings(), new Units()));
        $request = self::request(['step' => 'quarter']);

        self::assertSame('training_hours', TrainingHoursReport::key());
        self::assertSame('Training hours and costs', $report->title());
        self::assertSame(['Start', 'End', 'Unit', 'Unit name', 'Level', 'Activities', 'Hours', 'Costs', 'Eligible costs', 'Currency'], $report->columns($request));
        self::assertSame([
            ['2026-01-01', '2026-03-31', 'hq', 'Company', 1, 1, 1.5, 100.0, 80.0, 'CAD'],
            ['2026-01-01', '2026-03-31', 'ops', 'Operations', 2, 1, 1.5, 100.0, 80.0, 'CAD'],
            ['2026-01-01', '2026-03-31', 't1', 'Field', 3, 1, 1.5, 100.0, 80.0, 'CAD'],
            ['2026-07-01', '2026-09-30', '', 'No unit', 0, 1, 12.25, 1234.56, 1000.01, 'CAD'],
        ], iterator_to_array($report->rows($request), false));

        self::assertSame([
            ['2026-01-01', '2026-12-31', 't1', 'Field', 3, 1, 1.5, 100.0, 80.0, 'CAD'],
            ['2026-01-01', '2026-12-31', '', 'No unit', 0, 1, 12.25, 1234.56, 1000.01, 'CAD'],
        ], iterator_to_array($report->rows(self::request(['include_below' => false])), false));

        self::assertSame([
            ['2026-01-01', '2026-12-31', 'ops', 'Operations', 2, 1, 1.5, 100.0, 80.0, 'CAD'],
            ['2026-01-01', '2026-12-31', 't1', 'Field', 3, 1, 1.5, 100.0, 80.0, 'CAD'],
        ], iterator_to_array($report->rows(self::request(['unit' => 'ops', 'include_below' => true])), false));
    }

    public function testTheAmountsAreWrittenInTheDigitsOfTheCurrency(): void
    {
        $report = new TrainingHoursReport(new TrainingLedger(new Trainings([new TrainingActivity(Trainings::on('2026-02-01'), 1, 500, 499)], 'JPY')));

        self::assertSame([['2026-01-01', '2026-12-31', '', 'No unit', 0, 1, 0.01, 500.0, 499.0, 'JPY']], iterator_to_array($report->rows(self::request()), false));

        $amounts = new CurrencyAmounts('KWD');
        self::assertSame([3, 1.234, 'KWD'], [$amounts->fractionDigits, $amounts->decimal(1_234), $amounts->currency]);
        self::assertSame([2, 12.34], [new CurrencyAmounts('CAD')->fractionDigits, new CurrencyAmounts('CAD')->decimal(1_234)]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidCurrencies(): iterable
    {
        yield 'lowercase' => ['cad'];
        yield 'two letters' => ['CA'];
        yield 'four letters' => ['CADX'];
        yield 'a line after it' => ["CAD\n"];
        yield 'a line before it' => ["\nCAD"];
    }

    #[DataProvider('invalidCurrencies')]
    public function testACurrencyIsThreeCapitalLetters(string $currency): void
    {
        $this->expectExceptionObject(InvalidFigures::because(\sprintf('"%s" is not an ISO 4217 currency code', $currency)));

        new CurrencyAmounts($currency);
    }

    public function testTheOnePercentOfPayroll(): void
    {
        $report = new QuebecTrainingReport(new TrainingLedger($this->quebec()));

        self::assertSame('training_quebec_1_percent', QuebecTrainingReport::key());
        self::assertSame('Workforce skills development: 1% of payroll (Quebec)', $report->title());
        self::assertSame([
            'Start', 'End', 'Activities', 'Hours', 'Costs', 'Eligible costs', 'Payroll', 'Required investment (1%)',
            'Eligible costs over payroll (%)', 'Shortfall', 'Payroll above the threshold', 'Currency',
        ], $report->columns(self::request()));

        // A payroll whose 1 % is rounded half up: 250 000.50 $ requires 2 500.01 $.
        self::assertSame(
            [['2026-01-01', '2026-12-31', 2, 15.0, 2000.0, 1500.0, 250000.5, 2500.01, 0.6, 1000.01, false, 'CAD']],
            iterator_to_array($report->rows(self::request(['payroll' => 25_000_050])), false),
        );
        self::assertSame([[1.49, 0.01], [1.5, 0.02]], [$this->required($report, 149), $this->required($report, 150)]);

        // Eligible costs above the 1 %: no shortfall.
        self::assertSame(
            [['2026-01-01', '2026-12-31', 2, 15.0, 2000.0, 1500.0, 100.0, 1.0, 1500.0, 0.0, false, 'CAD']],
            iterator_to_array($report->rows(self::request(['payroll' => 10_000])), false),
        );

        // No payroll: nothing to compare with.
        self::assertSame(
            [['2026-01-01', '2026-12-31', 2, 15.0, 2000.0, 1500.0, null, null, null, null, null, 'CAD']],
            iterator_to_array($report->rows(self::request()), false),
        );

        // A payroll of zero: no share of it.
        self::assertSame(
            [['2026-01-01', '2026-12-31', 2, 15.0, 2000.0, 1500.0, 0.0, 0.0, null, 0.0, false, 'CAD']],
            iterator_to_array($report->rows(self::request(['payroll' => 0])), false),
        );

        $third = new QuebecTrainingReport(new TrainingLedger(new Trainings([new TrainingActivity(Trainings::on('2026-02-01'), 1, 1, 1)])));
        self::assertSame(33.33, iterator_to_array($third->rows(self::request(['payroll' => 3])), false)[0][8]);
    }

    public function testTheThresholdOfTheAct(): void
    {
        $report = new QuebecTrainingReport(new TrainingLedger($this->quebec()));

        self::assertFalse(iterator_to_array($report->rows(self::request(['payroll' => 200_000_000])), false)[0][10]);
        self::assertTrue(iterator_to_array($report->rows(self::request(['payroll' => 200_000_001])), false)[0][10]);

        $lower = new QuebecTrainingReport(new TrainingLedger($this->quebec()), threshold: 100);
        self::assertFalse(iterator_to_array($lower->rows(self::request(['payroll' => 100])), false)[0][10]);
        self::assertTrue(iterator_to_array($lower->rows(self::request(['payroll' => 101])), false)[0][10]);
    }

    public function testThePayrollOfEachMonthFromItsSource(): void
    {
        $report = new QuebecTrainingReport(new TrainingLedger($this->quebec()), payrolls: new Payrolls(['2026-03-01' => 300_000_000, '2026-04-01' => 1_000_000_000_000_000]));
        $rows = iterator_to_array($report->rows(self::request(['step' => 'month'])), false);

        self::assertCount(12, $rows);
        self::assertSame(['2026-01-01', '2026-01-31', 0, 0.0, 0.0, 0.0, null, null, null, null, null, 'CAD'], $rows[0]);
        self::assertSame(['2026-03-01', '2026-03-31', 1, 10.0, 1500.0, 1200.0, 3_000_000.0, 30_000.0, 0.04, 28_800.0, true, 'CAD'], $rows[2]);
        self::assertSame(['2026-04-01', '2026-04-30', 0, 0.0, 0.0, 0.0, 10_000_000_000_000.0, 100_000_000_000.0, 0.0, 100_000_000_000.0, true, 'CAD'], $rows[3]);
        self::assertSame(['2026-09-01', '2026-09-30', 1, 5.0, 500.0, 300.0, null, null, null, null, null, 'CAD'], $rows[8]);

        // The payroll of the request wins over the source's.
        $year = new QuebecTrainingReport(new TrainingLedger($this->quebec()), payrolls: new Payrolls(['2026-01-01' => 1]));
        self::assertSame(100.0, iterator_to_array($year->rows(self::request(['payroll' => 10_000])), false)[0][6]);
        self::assertSame(0.01, iterator_to_array($year->rows(self::request()), false)[0][6]);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function payrollsOutOfBounds(): iterable
    {
        yield 'negative' => [-1];
        yield 'too large' => [1_000_000_000_000_001];
    }

    #[DataProvider('payrollsOutOfBounds')]
    public function testAPayrollOutOfBoundsIsRefused(int $payroll): void
    {
        $report = new QuebecTrainingReport(new TrainingLedger($this->quebec()), payrolls: new Payrolls(['2026-01-01' => $payroll]));

        $this->expectExceptionObject(InvalidFigures::because('a payroll is from 0 to 1000000000000000'));

        iterator_to_array($report->rows(self::request()));
    }

    public function testThePayrollOfTheRequestIsForOnePeriod(): void
    {
        $report = new QuebecTrainingReport(new TrainingLedger($this->quebec()));

        $this->expectExceptionObject(InvalidReportJob::because('the parameter "payroll" is the payroll of a report of one period'));

        iterator_to_array($report->rows(self::request(['payroll' => 100, 'step' => 'quarter'])));
    }

    /**
     * @return iterable<string, array{Closure(): mixed, string}>
     */
    public static function invalidParameters(): iterable
    {
        $hours = new TrainingHoursReport(new TrainingLedger(new Trainings()));
        $quebec = new QuebecTrainingReport(new TrainingLedger(new Trainings()));
        $rows = static fn (TrainingHoursReport|QuebecTrainingReport $report, array $parameters): Closure => static fn (): array => iterator_to_array($report->rows(self::request($parameters)));

        yield 'an unknown step' => [$rows($hours, ['step' => 'week']), 'the parameter "step" is "month", "quarter" or "year"'];
        yield 'a step as a number' => [$rows($quebec, ['step' => 3]), 'the parameter "step" is "month", "quarter" or "year"'];
        yield 'a flag as a text' => [$rows($hours, ['include_below' => 'yes']), 'the parameter "include_below" is true or false'];
        yield 'a unit as a number' => [$rows($hours, ['unit' => 5]), 'the parameter "unit" is a text'];
        yield 'a negative payroll' => [$rows($quebec, ['payroll' => -1]), 'the parameter "payroll" is a whole number of zero or more'];
        yield 'a payroll with decimals' => [$rows($quebec, ['payroll' => 1.5]), 'the parameter "payroll" is a whole number of zero or more'];
        yield 'a payroll as a text' => [$rows($quebec, ['payroll' => '100']), 'the parameter "payroll" is a whole number of zero or more'];
    }

    /**
     * @param Closure(): mixed $run
     */
    #[DataProvider('invalidParameters')]
    public function testAParameterOutOfShapeIsRefused(Closure $run, string $problem): void
    {
        $this->expectExceptionObject(InvalidReportJob::because($problem));

        $run();
    }

    public function testTheHeadingsInTheLanguageOfTheRequest(): void
    {
        $translator = new RecordingTranslator();
        $headings = new TrainingHeadings($translator);
        $report = new TrainingHoursReport(new TrainingLedger(new Trainings([new TrainingActivity(Trainings::on('2026-02-01'), 100)])), $headings);

        self::assertSame('Heures et coûts de formation', $report->title());
        self::assertSame(['training.title', 'phil_reporting', null], $translator->calls[0]);
        self::assertSame(['Start', 'End', 'Unit', 'Unit name', 'Level', 'Activities', 'Heures', 'Costs', 'Eligible costs', 'Currency'], $report->columns(self::request(['locale' => 'fr_CA'])));
        self::assertSame(['training.hours', 'phil_reporting', 'fr_CA'], $translator->calls[7]);
        self::assertSame('No unit', iterator_to_array($report->rows(self::request(['locale' => 'fr'])), false)[0][3]);
        self::assertSame(['training.no_unit', 'phil_reporting', 'fr'], array_last($translator->calls));

        $headings->text('training.unit', self::request());
        self::assertSame(['training.unit', 'phil_reporting', null], array_last($translator->calls));

        foreach (['fra', 'zh-Hant', 'sr_Latn_RS', 'de-Latn-DE-1996', 'es-419', 'en_abcdefgh'] as $locale) {
            $headings->text('training.unit', self::request(['locale' => $locale]));
            self::assertSame(['training.unit', 'phil_reporting', $locale], array_last($translator->calls));
        }

        self::assertSame(TrainingHeadings::ENGLISH['training.quebec.title'], new TrainingHeadings()->text('training.quebec.title', self::request(['locale' => 'fr'])));
    }

    /**
     * @return iterable<string, array{bool|float|int|string}>
     */
    public static function invalidLocales(): iterable
    {
        yield 'one letter' => ['f'];
        yield 'four letters' => ['fran'];
        yield 'a subtag of one letter' => ['fr_C'];
        yield 'a subtag of nine letters' => ['en_abcdefghi'];
        yield 'four subtags' => ['de-Latn-DE-1996-ab'];
        yield 'a path' => ['../fr'];
        yield 'a line after it' => ["fr\n"];
        yield 'a line before it' => ["\nfr"];
        yield 'a number' => [1];
    }

    #[DataProvider('invalidLocales')]
    public function testALocaleOutOfShapeIsRefused(bool|float|int|string $locale): void
    {
        $this->expectExceptionObject(InvalidReportJob::because('the parameter "locale" is not a locale'));

        new TrainingHeadings(new RecordingTranslator())->text('training.unit', self::request(['locale' => $locale]));
    }

    public function testTheEnglishHeadingsAreTheEnglishTranslations(): void
    {
        $catalogue = require \dirname(__DIR__, 3).'/translations/phil_reporting.en.php';
        self::assertIsArray($catalogue);

        self::assertSame(TrainingHeadings::ENGLISH, array_intersect_key($catalogue, TrainingHeadings::ENGLISH));

        $french = require \dirname(__DIR__, 3).'/translations/phil_reporting.fr.php';
        self::assertIsArray($french);
        self::assertSame(array_keys($catalogue), array_keys($french));
    }

    /**
     * @return array{float, float} the payroll and the investment required, in dollars
     */
    private function required(QuebecTrainingReport $report, int $payroll): array
    {
        $row = iterator_to_array($report->rows(self::request(['payroll' => $payroll])), false)[0];
        self::assertIsFloat($row[6]);
        self::assertIsFloat($row[7]);

        return [$row[6], $row[7]];
    }

    private function trainings(): Trainings
    {
        return new Trainings([
            new TrainingActivity(Trainings::on('2026-08-15'), 1_225, 123_456, 100_001),
            new TrainingActivity(Trainings::on('2026-02-10'), 150, 10_000, 8_000, 't1'),
        ]);
    }

    private function quebec(): Trainings
    {
        return new Trainings([
            new TrainingActivity(Trainings::on('2026-03-01'), 1_000, 150_000, 120_000, 't1'),
            new TrainingActivity(Trainings::on('2026-09-01'), 500, 50_000, 30_000),
        ]);
    }

    /**
     * @param array<string, bool|float|int|string> $parameters
     */
    private static function request(array $parameters = []): ReportRequest
    {
        return new ReportRequest(Trainings::year(), $parameters);
    }
}
