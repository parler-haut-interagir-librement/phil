<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Tests\Unit\Infrastructure;

use Phil\LicensingBundle\Domain\Catalog\LicenseCatalog;
use Phil\LicensingBundle\Domain\Compliance\ViolationCode;
use Phil\LicensingBundle\Domain\ValueObject\License;
use Phil\LicensingBundle\Domain\ValueObject\Violation;
use Phil\LicensingBundle\Infrastructure\Translation\LicenseNames;
use Phil\LicensingBundle\Tests\Unit\Support\Twig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LicenseNames::class)]
final class LicenseNamesTest extends TestCase
{
    public function testANameInTheReadersLanguage(): void
    {
        $house = License::of('house', 'House license', []);
        $names = new LicenseNames(new LicenseCatalog([$house]), Twig::translator('fr'));

        self::assertSame('Licence ORC', $names->name('orc'));
        self::assertSame('ORC License', $names->name('orc', 'en'));
        self::assertSame('Licence propre', $names->name(new LicenseCatalog()->get('custom')));
        self::assertSame('House license', $names->name('house'));
        self::assertSame('House license', $names->name($house, 'en'));
        self::assertSame('nope', $names->name('nope'));
    }

    public function testWithoutATranslator(): void
    {
        $names = new LicenseNames(new LicenseCatalog());

        self::assertSame('ORC License', $names->name('orc'));
        self::assertSame('page.licensed_under ORC', $names->trans('page.licensed_under %license%', ['%license%' => 'ORC']));
        self::assertSame('violation.missing_credit', $names->violation(new Violation(ViolationCode::MissingCredit, 'orc', 0, 'SRD')));
    }

    public function testTheMessageOfAViolation(): void
    {
        $names = new LicenseNames(new LicenseCatalog(), Twig::translator());

        self::assertSame('“Cover” is under the Creative Commons Attribution 4.0 International, which asks for its creators to be credited.', $names->violation(new Violation(ViolationCode::MissingCredit, 'cc-by-4.0', 0, 'Cover')));
        self::assertSame('« Cover » est sous la Licence Creative Commons Attribution 4.0 International, qui demande de créditer ses auteurs.', $names->violation(new Violation(ViolationCode::MissingCredit, 'cc-by-4.0', 0, 'Cover'), 'fr'));
        self::assertSame('The work has no license declaration.', $names->violation(new Violation(ViolationCode::NoDeclaration)));
        self::assertSame('The license “nope” is not in the catalog.', $names->violation(new Violation(ViolationCode::UnknownLicense, 'nope')));
        self::assertSame('Attributions', $names->trans('page.attributions', [], 'en'));
    }
}
