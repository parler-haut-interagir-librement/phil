<?php

declare(strict_types=1);

namespace Phil\UploadScanBundle\Tests\Unit\Fixture;

use Override;
use Phil\UploadScanBundle\Infrastructure\Validator\CleanFileValidator;
use Symfony\Component\Validator\Constraint;

/**
 * Another constraint, sent to the validator of `CleanFile` by mistake.
 */
final class MisdirectedConstraint extends Constraint
{
    #[Override]
    public function validatedBy(): string
    {
        return CleanFileValidator::class;
    }
}
