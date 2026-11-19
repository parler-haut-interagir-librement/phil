<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Tests\Unit\Application;

use Phil\LicensingBundle\Application\AttributedResource;
use Phil\LicensingBundle\Application\AttributionPage;
use Phil\LicensingBundle\Domain\Catalog\LicenseCatalog;
use Phil\LicensingBundle\Domain\Catalog\ResourceKind;
use Phil\LicensingBundle\Domain\Model\LicenseDeclaration;
use Phil\LicensingBundle\Domain\ValueObject\Attribution;
use Phil\LicensingBundle\Domain\ValueObject\License;
use Phil\LicensingBundle\Tests\Unit\Support\Declarations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AttributionPage::class)]
#[CoversClass(AttributedResource::class)]
final class AttributionPageTest extends TestCase
{
    public function testThePageOfADeclaration(): void
    {
        $catalog = new LicenseCatalog();
        $declaration = Declarations::of(LicenseCatalog::OGL);
        $declaration->describe('© 2026 Example Press', 'Setting', 'Logos', 'Chapter 3', Declarations::at());
        $declaration->includeLicenseText('Terms', Declarations::at());
        $declaration->attribute([
            Attribution::of(ResourceKind::Font, 'Serif', LicenseCatalog::CC0),
            Attribution::of(ResourceKind::Image, 'Cover', LicenseCatalog::CC_BY, notice: '© Jane'),
            Attribution::of(ResourceKind::Text, 'SRD', LicenseCatalog::OGL, notice: '© 2000 Example Games'),
            Attribution::of(ResourceKind::Image, 'Back', 'nope', notice: '© Jane'),
            Attribution::of(ResourceKind::Other, 'Icons', LicenseCatalog::CC_BY),
            Attribution::of(ResourceKind::Map, 'Map', LicenseCatalog::CC_BY_SA),
        ], Declarations::at());

        $page = AttributionPage::of($declaration, $catalog);

        self::assertTrue($page->subject->equals($declaration->subject()));
        self::assertSame('ogl-1.0a', $page->licenseCode);
        self::assertSame($catalog->get('ogl-1.0a')->code, $page->license?->code);
        self::assertSame('© 2026 Example Press', $page->notice);
        self::assertSame('Setting', $page->reservedMaterial);
        self::assertSame('Logos', $page->productIdentity);
        self::assertSame('Chapter 3', $page->designatedContent);
        self::assertSame('Terms', $page->licenseText);
        self::assertSame(['SRD', 'Cover', 'Back', 'Map', 'Serif', 'Icons'], array_map(static fn (AttributedResource $resource): string => $resource->attribution->title, $page->resources));
        self::assertSame(['ogl-1.0a', 'cc-by-4.0', null, 'cc-by-sa-4.0', 'cc0-1.0', 'cc-by-4.0'], array_map(static fn (AttributedResource $resource): ?string => $resource->license?->code, $page->resources));
        self::assertSame(['© 2026 Example Press', '© 2000 Example Games', '© Jane'], $page->notices);
        self::assertSame(['ogl-1.0a', 'cc-by-4.0', 'cc-by-sa-4.0', 'cc0-1.0'], array_map(static fn (License $license): string => $license->code, $page->licenses));
        self::assertSame(['Cover', 'Back'], array_map(static fn (AttributedResource $resource): string => $resource->attribution->title, $page->resourcesOf(ResourceKind::Image)));
        self::assertSame([], AttributionPage::of(Declarations::of(LicenseCatalog::CC0), $catalog)->resourcesOf(ResourceKind::Image));

        $alone = Declarations::of(LicenseCatalog::CC0);
        $alone->attribute([Attribution::of(ResourceKind::Image, 'Cover', LicenseCatalog::CC_BY, 'Jane')], Declarations::at());
        self::assertSame(['cc0-1.0', 'cc-by-4.0'], array_map(static fn (License $license): string => $license->code, AttributionPage::of($alone, $catalog)->licenses));
    }

    public function testAnUnknownLicenseAndNoNotice(): void
    {
        $declaration = LicenseDeclaration::declare(Declarations::complete()->subject(), License::of('house', 'House', []), Declarations::at());
        $declaration->attribute([Attribution::of(ResourceKind::Image, 'Cover', LicenseCatalog::CC0, notice: '© Jane')], Declarations::at());

        $page = AttributionPage::of($declaration, new LicenseCatalog());

        self::assertSame('house', $page->licenseCode);
        self::assertNull($page->license);
        self::assertSame(['© Jane'], $page->notices);
        self::assertSame(['cc0-1.0'], array_map(static fn (License $license): string => $license->code, $page->licenses));
    }
}
