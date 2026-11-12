<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatement;

use DateTimeImmutable;
use Phil\AccessibilityCheck\Severity;
use Phil\AccessibilityCheck\SuccessCriterion;
use Phil\AccessibilityStatement\Audit\AuditResult;

/**
 * Writes the next version of a statement from the latest audit. The current version gives everything an audit does
 * not know — the target, the feedback contact, the alternatives, the scope, the enforcement procedure, the organisation
 * — and the non-conformities written by hand, kept as they are.
 *
 * The non-conformities the previous audit found are replaced by those of this one: one per success criterion of the
 * target that a finding of `$minimum` severity or graver fails, in the order of the criteria, with the number of
 * findings across the pages. A criterion the previous audit found already keeps what was written of it — its reason,
 * description, alternative and date of fix —; a criterion a non-conformity written by hand names is left to it; a
 * criterion outside the target is not a non-conformity of the statement. The status is the one the non-conformities
 * support, and the evaluation is the audit's.
 */
final readonly class StatementGenerator
{
    public function __construct(
        private Severity $minimum = Severity::Error,
    ) {
    }

    /**
     * @param ?int $version the number of the new version; one more than the current by default
     */
    public function generate(Statement $current, AuditResult $audit, DateTimeImmutable $publishedAt, ?int $version = null): Statement
    {
        $manual = array_filter($current->nonConformities, static fn (NonConformity $nonConformity): bool => !$nonConformity->fromAudit);
        $named = array_map(static fn (NonConformity $nonConformity): string => $nonConformity->criterion->value, $manual);
        $previous = [];

        foreach ($current->nonConformities as $nonConformity) {
            if ($nonConformity->fromAudit) {
                $previous[$nonConformity->criterion->value] = $nonConformity;
            }
        }

        $found = [];

        foreach ($this->occurrences($current->target, $audit) as $criterion => $occurrences) {
            if (\in_array($criterion, $named, true)) {
                continue;
            }

            $known = $previous[$criterion] ?? null;
            $found[] = new NonConformity(
                SuccessCriterion::from($criterion),
                $known->reason ?? NonConformityReason::NonCompliance,
                $known?->description,
                $known?->alternative,
                $known?->fixBy,
                $occurrences,
                fromAudit: true,
            );
        }

        $nonConformities = [...$manual, ...$found];

        return new Statement(
            $version ?? $current->version + 1,
            $publishedAt,
            $current->target,
            ConformanceStatus::derive($current->target, $nonConformities),
            $current->feedback,
            $nonConformities,
            $current->alternatives,
            $current->scope,
            $current->enforcement,
            new Evaluation($audit->evaluatedAt, $audit->method, \count($audit->pages), $audit->reportUrl),
            $current->entity,
        );
    }

    /**
     * The findings of the audit counted by criterion of the target, in the order of the criteria.
     *
     * @return array<string, int>
     */
    private function occurrences(ConformanceTarget $target, AuditResult $audit): array
    {
        $counts = [];

        foreach ($audit->pages as $page) {
            foreach ($page->report->findings($this->minimum) as $finding) {
                $counts[$finding->criterion->value] = ($counts[$finding->criterion->value] ?? 0) + 1;
            }
        }

        $ordered = [];

        foreach ($target->criteria() as $criterion) {
            if (isset($counts[$criterion->value])) {
                $ordered[$criterion->value] = $counts[$criterion->value];
            }
        }

        return $ordered;
    }
}
