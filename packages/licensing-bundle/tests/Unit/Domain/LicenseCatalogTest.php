<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Tests\Unit\Domain;

use Phil\LicensingBundle\Domain\Catalog\LicenseCatalog;
use Phil\LicensingBundle\Domain\Catalog\Requirement;
use Phil\LicensingBundle\Domain\Exception\InvalidLicensing;
use Phil\LicensingBundle\Domain\Exception\UnknownLicense;
use Phil\LicensingBundle\Domain\ValueObject\License;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LicenseCatalog::class)]
#[CoversClass(License::class)]
#[CoversClass(UnknownLicense::class)]
final class LicenseCatalogTest extends TestCase
{
    public function testTheBuiltInLicensesAndWhatTheyRequire(): void
    {
        $catalog = new LicenseCatalog();
        $summary = [];

        foreach ($catalog->all() as $license) {
            $summary[$license->code] = [
                $license->name,
                $license->url,
                $license->reference,
                $license->spdx,
                array_map(static fn (Requirement $requirement): string => $requirement->value, $license->requirements),
            ];
        }

        self::assertSame([
            'orc' => ['ORC License', 'https://paizo.com/orclicense', 'Library of Congress TX 9-307-067', null, ['attribution', 'license_notice', 'share_alike', 'reserved_material']],
            'cc-by-4.0' => ['Creative Commons Attribution 4.0 International', 'https://creativecommons.org/licenses/by/4.0/', null, 'CC-BY-4.0', ['attribution', 'license_notice', 'copyright_notice', 'indicate_changes']],
            'cc-by-sa-4.0' => ['Creative Commons Attribution-ShareAlike 4.0 International', 'https://creativecommons.org/licenses/by-sa/4.0/', null, 'CC-BY-SA-4.0', ['attribution', 'license_notice', 'copyright_notice', 'share_alike', 'indicate_changes']],
            'cc-by-nc-4.0' => ['Creative Commons Attribution-NonCommercial 4.0 International', 'https://creativecommons.org/licenses/by-nc/4.0/', null, 'CC-BY-NC-4.0', ['attribution', 'license_notice', 'copyright_notice', 'indicate_changes', 'non_commercial']],
            'cc-by-nc-sa-4.0' => ['Creative Commons Attribution-NonCommercial-ShareAlike 4.0 International', 'https://creativecommons.org/licenses/by-nc-sa/4.0/', null, 'CC-BY-NC-SA-4.0', ['attribution', 'license_notice', 'copyright_notice', 'share_alike', 'indicate_changes', 'non_commercial']],
            'cc-by-nd-4.0' => ['Creative Commons Attribution-NoDerivatives 4.0 International', 'https://creativecommons.org/licenses/by-nd/4.0/', null, 'CC-BY-ND-4.0', ['attribution', 'license_notice', 'copyright_notice', 'indicate_changes', 'no_derivatives']],
            'cc-by-nc-nd-4.0' => ['Creative Commons Attribution-NonCommercial-NoDerivatives 4.0 International', 'https://creativecommons.org/licenses/by-nc-nd/4.0/', null, 'CC-BY-NC-ND-4.0', ['attribution', 'license_notice', 'copyright_notice', 'indicate_changes', 'non_commercial', 'no_derivatives']],
            'cc0-1.0' => ['CC0 1.0 Universal', 'https://creativecommons.org/publicdomain/zero/1.0/', null, 'CC0-1.0', []],
            'ogl-1.0a' => ['Open Game License 1.0a', 'https://opengamingfoundation.org/ogl.html', null, null, ['full_text', 'copyright_notice', 'share_alike', 'product_identity', 'designated_content']],
            'custom' => ['Custom license', null, null, null, ['license_notice']],
        ], $summary);
        self::assertEquals(LicenseCatalog::builtIn(), $catalog->all());
    }

    public function testTheApplicationsLicensesComeAfter(): void
    {
        $house = License::of('house', 'House license', [Requirement::Attribution, Requirement::Attribution], 'https://example.com/license');
        $catalog = new LicenseCatalog([$house]);

        self::assertSame($house, $catalog->get('house'));
        self::assertSame($house, $catalog->find('house'));
        self::assertSame($house, $catalog->all()[10]);
        self::assertCount(11, $catalog->all());
        self::assertSame([Requirement::Attribution], $house->requirements);
        self::assertNull($catalog->find('nope'));
    }

    public function testACodeIsInTheCatalogOnce(): void
    {
        $this->expectExceptionObject(InvalidLicensing::because('The license "orc" is in the catalog already.'));

        new LicenseCatalog([License::of('orc', 'Another', [])]);
    }

    public function testAnUnknownCodeIsRefused(): void
    {
        $this->expectExceptionObject(UnknownLicense::named('nope'));
        $this->expectExceptionMessageIsOrContains('The license "nope" is not in the catalog.');

        new LicenseCatalog()->get('nope');
    }

    public function testALicenseSaysWhatItRequiresAndWhereItIs(): void
    {
        $license = License::of('house', ' House ', [Requirement::ShareAlike, Requirement::Attribution], reference: ' Registry 12 ', spdx: ' LicenseRef-House ');

        self::assertSame('House', $license->name);
        self::assertSame('Registry 12', $license->reference);
        self::assertSame('LicenseRef-House', $license->spdx);
        self::assertSame([Requirement::Attribution, Requirement::ShareAlike], $license->requirements);
        self::assertTrue($license->requires(Requirement::ShareAlike));
        self::assertFalse($license->requires(Requirement::FullText));
        self::assertTrue($license->isLocated());
        self::assertTrue(License::of('a', 'A', [], 'https://example.com')->isLocated());
        self::assertFalse(License::of('a', 'A', [])->isLocated());
    }

    public function testALicenseIsChecked(): void
    {
        foreach ([
            static fn (): License => License::of('A', 'A', []),
            static fn (): License => License::of('a', ' ', []),
            static fn (): License => License::of('a', str_repeat('a', 256), []),
            static fn (): License => License::of('a', 'A', [], 'ftp://example.com'),
            static fn (): License => License::of('a', 'A', [], reference: str_repeat('a', 256)),
            static fn (): License => License::of('a', 'A', [], spdx: str_repeat('a', 65)),
        ] as $index => $license) {
            try {
                $license();
                self::fail(\sprintf('License %d was accepted.', $index));
            } catch (InvalidLicensing) {
                self::addToAssertionCount(1);
            }
        }

        self::assertSame(str_repeat('a', 255), License::of('a', str_repeat('a', 255), [], reference: str_repeat('a', 255), spdx: str_repeat('a', 64))->name);
    }
}
