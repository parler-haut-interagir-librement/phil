<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Tests\Functional\App;

use Phil\LicensingBundle\Application\Event\ExportChecking;
use Phil\LicensingBundle\Domain\Compliance\ViolationCode;
use Phil\LicensingBundle\Domain\ValueObject\Violation;

/**
 * A rule of the application: the books of the series "draft" are never exported.
 */
final class AttributionPageListener
{
    public function __invoke(ExportChecking $event): void
    {
        if (str_starts_with($event->subject->id, 'draft-')) {
            $event->addViolation(new Violation(ViolationCode::MissingCredit, resourceTitle: 'Attribution page'));
        }
    }
}
