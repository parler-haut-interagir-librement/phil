<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Domain\Contract;

use Phil\LicensingBundle\Domain\Model\LicenseDeclaration;
use Phil\LicensingBundle\Domain\ValueObject\SubjectReference;

/**
 * Where the declarations are kept. Adding or removing one takes effect when the application flushes its unit of work.
 */
interface LicenseDeclarationRepository
{
    public function declarationOf(SubjectReference $subject): ?LicenseDeclaration;

    public function add(LicenseDeclaration $declaration): void;

    public function remove(LicenseDeclaration $declaration): void;
}
