<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Tests\Unit\Infrastructure;

use Phil\LicensingBundle\Application\AttributionPage;
use Phil\LicensingBundle\Domain\Catalog\LicenseCatalog;
use Phil\LicensingBundle\Domain\Catalog\ResourceKind;
use Phil\LicensingBundle\Domain\Model\LicenseDeclaration;
use Phil\LicensingBundle\Domain\ValueObject\Attribution;
use Phil\LicensingBundle\Domain\ValueObject\License;
use Phil\LicensingBundle\Domain\ValueObject\SubjectReference;
use Phil\LicensingBundle\Infrastructure\StructuredData\LicenseStructuredData;
use Phil\LicensingBundle\Tests\Unit\Support\Declarations;
use Phil\StructuredData\Graph;
use Phil\StructuredData\JsonLd;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LicenseStructuredData::class)]
final class LicenseStructuredDataTest extends TestCase
{
    public function testTheLicensingOfAWork(): void
    {
        $declaration = Declarations::complete();
        $declaration->attribute([...$declaration->attributions(), Attribution::of(ResourceKind::Map, 'Map', 'nope'), Attribution::of(ResourceKind::Other, 'Icons', LicenseCatalog::CC0)], Declarations::at());

        $page = AttributionPage::of($declaration, new LicenseCatalog());
        $structured = new LicenseStructuredData();

        self::assertSame(
            '{"@context":"https://schema.org","@graph":[{"@type":"Book","@id":"https://example.com/book/42","license":"https://paizo.com/orclicense","copyrightNotice":"© 2026 Example Press","isBasedOn":['
            .'{"@type":"CreativeWork","name":"Example Reference Document","author":{"@type":"Person","name":"Example Games"},"license":"https://paizo.com/orclicense","copyrightNotice":"© 2025 Example Games\nAll rights reserved."},'
            .'{"@type":"ImageObject","name":"Cover \\u003Cart\\u003E","author":{"@type":"Person","name":"Jane Doe"},"url":"https://example.com/cover","license":"https://creativecommons.org/licenses/by/4.0/","copyrightNotice":"© 2024 Jane Doe"},'
            .'{"@type":"Map","name":"Map"},'
            .'{"@type":"CreativeWork","name":"Example Serif","license":"https://creativecommons.org/publicdomain/zero/1.0/"},'
            .'{"@type":"CreativeWork","name":"Icons","license":"https://creativecommons.org/publicdomain/zero/1.0/"}]}]}',
            JsonLd::encode(Graph::of($structured->node($page, 'Book', 'https://example.com/book/42'))),
        );
        self::assertSame(['license', 'copyrightNotice', 'isBasedOn'], array_keys($structured->properties($page)));
        self::assertSame('CreativeWork', $structured->node(AttributionPage::of(Declarations::of(LicenseCatalog::CC0), new LicenseCatalog()))->types[0]);
        self::assertNull($structured->node(AttributionPage::of(Declarations::of(LicenseCatalog::CC0), new LicenseCatalog()))->id);

        $unknown = LicenseDeclaration::declare(SubjectReference::of('book', '1'), License::of('house', 'House', []), Declarations::at());
        self::assertNull($structured->properties(AttributionPage::of($unknown, new LicenseCatalog()))['license']);
    }
}
