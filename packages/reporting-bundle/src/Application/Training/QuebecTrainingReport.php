<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Application\Training;

use Override;
use Phil\ReportingBundle\Application\Contract\PayrollSource;
use Phil\ReportingBundle\Application\Contract\ReportDefinition;
use Phil\ReportingBundle\Application\Exception\InvalidReportJob;
use Phil\ReportingBundle\Application\Report\ReportRequest;
use Phil\ReportingBundle\Domain\Exception\InvalidFigures;

/**
 * The template of Quebec's Act to promote workforce skills development and recognition: by period, the training hours,
 * the costs and the eligible costs, the payroll, the investment of 1 % of it the Act requires, the eligible costs as a
 * share of the payroll, the shortfall, and whether the payroll is above the threshold that makes the Act apply.
 *
 * Its parameters: `step` — `month`, `quarter` or `year` — cuts the period, a calendar year for the Act; `payroll`, in
 * minor units, gives the payroll of a report of one period, instead of the payroll source; `locale` names the language
 * of the headings.
 */
final readonly class QuebecTrainingReport implements ReportDefinition
{
    /** The payroll above which the Act applies, in cents: two million dollars. */
    public const int THRESHOLD = 200_000_000;

    /** The largest payroll, in minor units: ten thousand billion dollars. */
    public const int MAX_PAYROLL = 1_000_000_000_000_000;

    public function __construct(
        private TrainingLedger $ledger,
        private TrainingHeadings $headings = new TrainingHeadings(),
        private ?PayrollSource $payrolls = null,
        private int $threshold = self::THRESHOLD,
    ) {
    }

    #[Override]
    public static function key(): string
    {
        return 'training_quebec_1_percent';
    }

    #[Override]
    public function title(): string
    {
        return $this->headings->text('training.quebec.title');
    }

    #[Override]
    public function columns(ReportRequest $request): array
    {
        return $this->headings->texts([
            'training.start',
            'training.end',
            'training.activities',
            'training.hours',
            'training.costs',
            'training.eligible_costs',
            'training.quebec.payroll',
            'training.quebec.required',
            'training.quebec.ratio',
            'training.quebec.shortfall',
            'training.quebec.above_threshold',
            'training.currency',
        ], $request);
    }

    /**
     * @throws InvalidReportJob when `payroll` is given to a report of more than one period
     */
    #[Override]
    public function rows(ReportRequest $request): iterable
    {
        $given = TrainingParameters::amount($request, 'payroll');
        $amounts = $this->ledger->amounts();
        $periods = $this->ledger->overall($request->period, TrainingParameters::step($request));

        if (null !== $given && 1 !== \count($periods)) {
            throw InvalidReportJob::because('the parameter "payroll" is the payroll of a report of one period');
        }

        foreach ($periods as $totals) {
            $payroll = $this->payroll($given ?? $this->payrolls?->payroll($totals->period));
            $required = null === $payroll ? null : intdiv($payroll + 50, 100);

            yield [
                $totals->period->start->format('Y-m-d'),
                TrainingParameters::lastDay($totals->period->end),
                $totals->activities,
                fdiv($totals->hundredthsOfHours, 100),
                $amounts->decimal($totals->cost),
                $amounts->decimal($totals->eligibleCost),
                null === $payroll ? null : $amounts->decimal($payroll),
                null === $required ? null : $amounts->decimal($required),
                null === $payroll || 0 === $payroll ? null : round($totals->eligibleCost / $payroll * 100, 2),
                null === $required ? null : $amounts->decimal(max(0, $required - $totals->eligibleCost)),
                null === $payroll ? null : $payroll > $this->threshold,
                $amounts->currency,
            ];
        }
    }

    /**
     * @throws InvalidFigures when the payroll is negative or beyond its bound
     */
    private function payroll(?int $payroll): ?int
    {
        if (null !== $payroll && ($payroll < 0 || $payroll > self::MAX_PAYROLL)) {
            throw InvalidFigures::because(\sprintf('a payroll is from 0 to %d', self::MAX_PAYROLL));
        }

        return $payroll;
    }
}
