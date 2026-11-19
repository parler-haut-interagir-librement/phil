<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Tests\Unit\Support;

use Override;
use Phil\LicensingBundle\Domain\Contract\LicenseDeclarationRepository;
use Phil\LicensingBundle\Domain\Model\LicenseDeclaration;
use Phil\LicensingBundle\Domain\ValueObject\SubjectReference;

/**
 * Declarations kept in an array, by subject.
 */
final class InMemoryDeclarations implements LicenseDeclarationRepository
{
    /** @var array<string, LicenseDeclaration> */
    private array $declarations = [];

    #[Override]
    public function declarationOf(SubjectReference $subject): ?LicenseDeclaration
    {
        return $this->declarations[$subject->type."\0".$subject->id] ?? null;
    }

    #[Override]
    public function add(LicenseDeclaration $declaration): void
    {
        $this->declarations[$declaration->subject()->type."\0".$declaration->subject()->id] = $declaration;
    }

    #[Override]
    public function remove(LicenseDeclaration $declaration): void
    {
        unset($this->declarations[$declaration->subject()->type."\0".$declaration->subject()->id]);
    }
}
