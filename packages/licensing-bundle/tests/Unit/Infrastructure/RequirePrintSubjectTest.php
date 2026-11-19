<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Tests\Unit\Infrastructure;

use Phil\LicensingBundle\Application\Exception\PrintSubjectRequired;
use Phil\LicensingBundle\Domain\Exception\LicensingException;
use Phil\LicensingBundle\Infrastructure\PagedMedia\RequirePrintSubject;
use Phil\LicensingBundle\Tests\Unit\Support\Twig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RequirePrintSubject::class)]
#[CoversClass(PrintSubjectRequired::class)]
final class RequirePrintSubjectTest extends TestCase
{
    public function testAPrintThatNamesNoWorkIsRefused(): void
    {
        try {
            new RequirePrintSubject()();
        } catch (PrintSubjectRequired $printSubjectRequired) {
            self::assertInstanceOf(LicensingException::class, $printSubjectRequired);
            self::assertSame('A print must name its work with a PrintSubject, so that its licensing is checked; a document without a license names a subject type declared in phil_licensing.printing.unlicensed_subjects.', $printSubjectRequired->getMessage());
            self::assertSame('This print names no work, so its license cannot be checked. Print it under the work it is made of; a document without a license names a type declared unlicensed.', $printSubjectRequired->trans(Twig::translator()));
            self::assertSame('Cette impression ne nomme aucune œuvre : sa licence ne peut pas être vérifiée. Imprimez-la sous l’œuvre dont elle est faite ; un document sans licence nomme un type déclaré sans licence.', $printSubjectRequired->trans(Twig::translator(), 'fr'));
        }
    }
}
