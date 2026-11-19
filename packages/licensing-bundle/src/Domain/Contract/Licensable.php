<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Domain\Contract;

use Phil\LicensingBundle\Domain\ValueObject\SubjectReference;

/**
 * An object of the application that a declaration may be attached to: the export guard, its voter and the Twig
 * functions take it in place of a subject reference.
 */
interface Licensable
{
    public function licenseSubject(): SubjectReference;
}
