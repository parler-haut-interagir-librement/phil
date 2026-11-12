<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatement;

/**
 * How far the content meets its conformance target: fully, partly, or not.
 */
enum ConformanceStatus: string
{
    /** Every success criterion of the target is met, except what lies outside the scope of the statement. */
    case Full = 'full';

    /** Some success criteria of the target are not met. */
    case Partial = 'partial';

    /** Most success criteria of the target are not met. */
    case NonConformant = 'non-conformant';

    /**
     * The status the non-conformities support: full when none counts against the target, non-conformant when more
     * than half of the criteria of the target fail, partial otherwise.
     *
     * @param list<NonConformity> $nonConformities
     */
    public static function derive(ConformanceTarget $target, array $nonConformities): self
    {
        $failed = \count(NonConformity::failedCriteria($nonConformities));

        if (0 === $failed) {
            return self::Full;
        }

        return $failed * 2 > \count($target->criteria()) ? self::NonConformant : self::Partial;
    }
}
