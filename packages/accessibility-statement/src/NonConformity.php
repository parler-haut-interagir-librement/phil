<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatement;

use DateTimeImmutable;
use Phil\AccessibilityCheck\SuccessCriterion;
use Phil\AccessibilityStatement\Exception\InvalidStatement;

/**
 * A success criterion the content does not meet: why, what it means for a reader, the alternative offered meanwhile and
 * when it will be met. A non-conformity found by an audit says so, with the number of times the audit met it.
 */
final readonly class NonConformity
{
    /**
     * @param ?LocalizedText     $description what fails and where; the title of the criterion speaks for it when null
     * @param ?LocalizedText     $alternative how a reader reaches the same content or service meanwhile
     * @param ?DateTimeImmutable $fixBy       when the criterion will be met
     * @param int                $occurrences the times an audit met it, 0 when not counted
     * @param bool               $fromAudit   whether the last audit found it, and the next one may lift it
     */
    public function __construct(
        public SuccessCriterion $criterion,
        public NonConformityReason $reason = NonConformityReason::NonCompliance,
        public ?LocalizedText $description = null,
        public ?LocalizedText $alternative = null,
        public ?DateTimeImmutable $fixBy = null,
        public int $occurrences = 0,
        public bool $fromAudit = false,
    ) {
        if ($occurrences < 0) {
            throw InvalidStatement::because('occurrences', \sprintf('The occurrences of a non-conformity are 0 or more; got %d.', $occurrences));
        }
    }

    /**
     * The criteria the non-conformities fail, once each and in the order of their numbers, leaving out those outside
     * the scope.
     *
     * @param list<self> $nonConformities
     *
     * @return list<SuccessCriterion>
     */
    public static function failedCriteria(array $nonConformities): array
    {
        $failed = array_map(
            static fn (self $nonConformity): SuccessCriterion => $nonConformity->criterion,
            array_filter($nonConformities, static fn (self $nonConformity): bool => $nonConformity->reason->countsAgainstConformance()),
        );

        return array_values(array_filter(SuccessCriterion::cases(), static fn (SuccessCriterion $criterion): bool => \in_array($criterion, $failed, true)));
    }
}
