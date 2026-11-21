<?php

declare(strict_types=1);

namespace Phil\ImportWizard\Import;

use Phil\ImportWizard\Contract\ImportTarget;
use Phil\ImportWizard\Exception\InvalidProfile;
use Phil\ImportWizard\Exception\PlanChanged;
use Phil\ImportWizard\Plan\CellError;
use Phil\ImportWizard\Plan\ImportPlan;
use Phil\ImportWizard\Plan\PlannedRow;
use Phil\ImportWizard\Plan\Reason;
use Phil\ImportWizard\Plan\RowAction;
use Phil\ImportWizard\Plan\RowRejection;

/**
 * Writes a plan through its target, batch by batch, in the order of the plan. A row whose record of the file it refers
 * to was refused — by the dry run or by the target — is refused in turn and never reaches the target. It keeps no
 * state between calls.
 */
final readonly class Importer
{
    public function __construct(private int $batchSize = 500)
    {
        if ($batchSize < 1) {
            throw InvalidProfile::because('A batch holds at least one row.');
        }
    }

    /**
     * @param string|null $previewed the fingerprint of the plan the person confirmed, when the plan was made again
     *                               since: a plan that differs is not written
     *
     * @throws PlanChanged when the plan is not the one previewed, or not of the target's type
     */
    public function commit(ImportPlan $plan, ImportTarget $target, ?string $previewed = null): ImportResult
    {
        if (null !== $previewed && !hash_equals($plan->fingerprint, $previewed)) {
            throw PlanChanged::sincePreview();
        }

        if ($target->type() !== $plan->profile->type) {
            throw PlanChanged::forTarget($plan->profile->type, $target->type());
        }

        $rejections = [];

        foreach ($plan->rejections as $rejection) {
            $rejections[$rejection->line] = $rejection;
        }

        $counts = [RowAction::Create->value => 0, RowAction::Update->value => 0];
        $batches = 0;
        $pending = [];

        foreach ($plan->rows as $row) {
            if ([] !== $pending && ([] !== array_intersect($row->dependsOn, array_keys($pending)) || \count($pending) >= $this->batchSize)) {
                $this->flush($plan, $target, ++$batches, $pending, $rejections, $counts);
            }

            $refused = array_filter($row->dependsOn, static fn (int $line): bool => isset($rejections[$line]));
            $column = array_key_first($refused);

            if (null === $column) {
                $pending[$row->line] = $row;
            } else {
                $rejections[$row->line] = new RowRejection($row->line, $row->cells, [new CellError($column, Reason::ReferenceRejected, null, $refused[$column])]);
            }
        }

        if ([] !== $pending) {
            $this->flush($plan, $target, ++$batches, $pending, $rejections, $counts);
        }

        ksort($rejections);

        return new ImportResult($counts[RowAction::Create->value], $counts[RowAction::Update->value], $batches, array_values($rejections));
    }

    /**
     * @param non-empty-array<int, PlannedRow> $pending
     * @param array<int, RowRejection>         $rejections
     * @param array<string, int>               $counts
     *
     * @param-out array<int, PlannedRow> $pending
     */
    private function flush(ImportPlan $plan, ImportTarget $target, int $number, array &$pending, array &$rejections, array &$counts): void
    {
        $errors = $target->write(new Batch($plan->profile->type, $number, array_values($pending)));

        foreach ($pending as $line => $row) {
            if (isset($errors[$line])) {
                $rejections[$line] = new RowRejection($line, $row->cells, $errors[$line]);
            } else {
                ++$counts[$row->action->value];
            }
        }

        $pending = [];
    }
}
