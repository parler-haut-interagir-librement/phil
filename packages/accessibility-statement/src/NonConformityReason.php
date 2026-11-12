<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatement;

/**
 * Why a success criterion is not met.
 */
enum NonConformityReason: string
{
    /** The content does not meet the criterion yet. */
    case NonCompliance = 'non-compliance';

    /** Meeting the criterion would be a burden out of proportion to its benefit, assessed and stated. */
    case DisproportionateBurden = 'disproportionate-burden';

    /** The content concerned lies outside the scope of the statement, such as archives or third-party content. */
    case OutOfScope = 'out-of-scope';

    /**
     * Whether a non-conformity for this reason keeps the content from full conformance: content outside the scope
     * does not.
     */
    public function countsAgainstConformance(): bool
    {
        return self::OutOfScope !== $this;
    }
}
