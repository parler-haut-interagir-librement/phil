<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Application\Training;

use Override;
use Phil\ReportingBundle\Application\Contract\ReportDefinition;
use Phil\ReportingBundle\Application\Report\ReportRequest;

/**
 * The hours and costs of training by period and by organisational unit, from the training source.
 *
 * Its parameters: `step` — `month`, `quarter` or `year` — cuts the period; `include_below`, true by default, counts
 * in a unit's row the activities of the units below it; `unit` keeps a unit and the units below it alone; `locale`
 * names the language of the headings.
 */
final readonly class TrainingHoursReport implements ReportDefinition
{
    public function __construct(
        private TrainingLedger $ledger,
        private TrainingHeadings $headings = new TrainingHeadings(),
    ) {
    }

    #[Override]
    public static function key(): string
    {
        return 'training_hours';
    }

    #[Override]
    public function title(): string
    {
        return $this->headings->text('training.title');
    }

    #[Override]
    public function columns(ReportRequest $request): array
    {
        return $this->headings->texts([
            'training.start',
            'training.end',
            'training.unit',
            'training.unit_name',
            'training.level',
            'training.activities',
            'training.hours',
            'training.costs',
            'training.eligible_costs',
            'training.currency',
        ], $request);
    }

    #[Override]
    public function rows(ReportRequest $request): iterable
    {
        $step = TrainingParameters::step($request);
        $below = TrainingParameters::flag($request, 'include_below', true);
        $within = TrainingParameters::text($request, 'unit');
        $amounts = $this->ledger->amounts();
        $noUnit = $this->headings->text('training.no_unit', $request);

        foreach ($this->ledger->byUnit(self::key(), $request->period, $step, $below, $within) as $totals) {
            $unit = $totals->unit();

            yield [
                $totals->period->start->format('Y-m-d'),
                TrainingParameters::lastDay($totals->period->end),
                $unit->id ?? '',
                $unit->name ?? $noUnit,
                \count($totals->path),
                $totals->activities,
                fdiv($totals->hundredthsOfHours, 100),
                $amounts->decimal($totals->cost),
                $amounts->decimal($totals->eligibleCost),
                $amounts->currency,
            ];
        }
    }
}
