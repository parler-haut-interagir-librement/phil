<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Application\Exception;

use Phil\LicensingBundle\Domain\Exception\LicensingException;
use Phil\LicensingBundle\Domain\ValueObject\ComplianceReport;
use Phil\LicensingBundle\Domain\ValueObject\SubjectReference;
use Phil\LicensingBundle\Domain\ValueObject\Violation;
use RuntimeException;

/**
 * An export refused: the subject has no declaration, or its declaration does not give what its licenses require.
 */
final class ExportBlocked extends RuntimeException implements LicensingException
{
    public function __construct(
        public readonly SubjectReference $subject,
        public readonly ComplianceReport $report,
    ) {
        $codes = array_map(static fn (Violation $violation): string => $violation->code->value, $report->violations);

        parent::__construct(\sprintf('The export of %s "%s" is blocked by its licensing: %s.', $subject->type, $subject->id, implode(', ', array_unique($codes))));
    }
}
