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
use Phil\LicensingBundle\Infrastructure\Translation\LicenseNames;
use Phil\LicensingBundle\Infrastructure\Ujo\AttributionDocument;
use Phil\LicensingBundle\Tests\Unit\Support\Declarations;
use Phil\LicensingBundle\Tests\Unit\Support\Twig;
use Phil\Ujo\Block;
use Phil\Ujo\Document;
use Phil\Ujo\Ujo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AttributionDocument::class)]
final class AttributionDocumentTest extends TestCase
{
    public function testTheDocumentOfACompleteDeclaration(): void
    {
        $document = $this->document(Declarations::complete());

        self::assertSame([
            ['phil-licensing-1', 'header', ['text' => 'License', 'level' => 2]],
            ['phil-licensing-2', 'paragraph', ['text' => 'This work is published under the ORC License. <a href="https://paizo.com/orclicense">https://paizo.com/orclicense</a>']],
            ['phil-licensing-3', 'paragraph', ['text' => 'License reference: Library of Congress TX 9-307-067.']],
            ['phil-licensing-4', 'header', ['text' => 'Reserved Material', 'level' => 3]],
            ['phil-licensing-5', 'paragraph', ['text' => 'The names of the setting, its maps and its art.']],
            ['phil-licensing-6', 'header', ['text' => 'Attributions', 'level' => 2]],
            ['phil-licensing-7', 'header', ['text' => 'Text sources', 'level' => 3]],
            ['phil-licensing-8', 'list', ['style' => 'unordered', 'meta' => [], 'items' => [
                ['content' => 'Example Reference Document, by Example Games. License: ORC License. <a href="https://paizo.com/orclicense">https://paizo.com/orclicense</a>', 'meta' => [], 'items' => []],
            ]]],
            ['phil-licensing-9', 'header', ['text' => 'Images', 'level' => 3]],
            ['phil-licensing-10', 'list', ['style' => 'unordered', 'meta' => [], 'items' => [
                ['content' => '<a href="https://example.com/cover">Cover &lt;art&gt;</a>, by Jane Doe. License: Creative Commons Attribution 4.0 International. <a href="https://creativecommons.org/licenses/by/4.0/">https://creativecommons.org/licenses/by/4.0/</a><br>Changes: Cropped', 'meta' => [], 'items' => []],
            ]]],
            ['phil-licensing-11', 'header', ['text' => 'Fonts', 'level' => 3]],
            ['phil-licensing-12', 'list', ['style' => 'unordered', 'meta' => [], 'items' => [
                ['content' => 'Example Serif. License: CC0 1.0 Universal. <a href="https://creativecommons.org/publicdomain/zero/1.0/">https://creativecommons.org/publicdomain/zero/1.0/</a>', 'meta' => [], 'items' => []],
            ]]],
            ['phil-licensing-13', 'header', ['text' => 'Copyright notices', 'level' => 2]],
            ['phil-licensing-14', 'paragraph', ['text' => '© 2026 Example Press']],
            ['phil-licensing-15', 'paragraph', ['text' => '© 2025 Example Games<br>All rights reserved.']],
            ['phil-licensing-16', 'paragraph', ['text' => '© 2024 Jane Doe']],
        ], $this->summary($document->blocks));

        // The document is valid UJO, and keeps its texts once normalized.
        $ujo = new Ujo();
        self::assertCount(\count($document->blocks), $ujo->decode($ujo->encode($document))->blocks);
        self::assertStringContainsString('Cover &lt;art&gt;', $ujo->toHtml($document));
    }

    public function testEverySectionOfTheDocument(): void
    {
        $declaration = LicenseDeclaration::declare(SubjectReference::of('book', '1'), License::of('house', 'House', []), Declarations::at());
        $declaration->describe(null, null, "Logos & 'names'", "Chapter 3\r\nChapter 4", Declarations::at());
        $declaration->includeLicenseText("Line 1\n\n  \n  Line <2>  \rLine 3", Declarations::at());
        $declaration->attribute([Attribution::of(ResourceKind::Map, 'Map', 'house'), Attribution::of(ResourceKind::Other, 'Icons', LicenseCatalog::CUSTOM)], Declarations::at());

        self::assertSame([
            ['phil-licensing-1', 'header', ['text' => 'Licence', 'level' => 2]],
            ['phil-licensing-2', 'paragraph', ['text' => 'Cette œuvre nomme la licence « house », absente du catalogue.']],
            ['phil-licensing-3', 'header', ['text' => 'Contenu offert sous la licence', 'level' => 3]],
            ['phil-licensing-4', 'paragraph', ['text' => 'Chapter 3<br>Chapter 4']],
            ['phil-licensing-5', 'header', ['text' => 'Identité du produit', 'level' => 3]],
            ['phil-licensing-6', 'paragraph', ['text' => 'Logos &amp; &apos;names&apos;']],
            ['phil-licensing-7', 'header', ['text' => 'Attributions', 'level' => 2]],
            ['phil-licensing-8', 'header', ['text' => 'Cartes', 'level' => 3]],
            ['phil-licensing-9', 'list', ['style' => 'unordered', 'meta' => [], 'items' => [['content' => 'Map. Licence : house.', 'meta' => [], 'items' => []]]]],
            ['phil-licensing-10', 'header', ['text' => 'Autres ressources', 'level' => 3]],
            ['phil-licensing-11', 'list', ['style' => 'unordered', 'meta' => [], 'items' => [['content' => 'Icons. Licence : Licence propre.', 'meta' => [], 'items' => []]]]],
            ['phil-licensing-12', 'header', ['text' => 'Texte de la licence', 'level' => 2]],
            ['phil-licensing-13', 'paragraph', ['text' => 'Line 1']],
            ['phil-licensing-14', 'paragraph', ['text' => 'Line &lt;2&gt;']],
            ['phil-licensing-15', 'paragraph', ['text' => 'Line 3']],
        ], $this->summary($this->document($declaration, 'fr')->blocks));

        self::assertSame([
            ['phil-licensing-1', 'header', ['text' => 'License', 'level' => 2]],
            ['phil-licensing-2', 'paragraph', ['text' => 'This work is published under the Custom license.']],
        ], $this->summary($this->document(Declarations::of(LicenseCatalog::CUSTOM))->blocks));
    }

    private function document(LicenseDeclaration $declaration, ?string $locale = null): Document
    {
        $catalog = new LicenseCatalog();

        return new AttributionDocument(new LicenseNames($catalog, Twig::translator()))->of(AttributionPage::of($declaration, $catalog), $locale);
    }

    /**
     * @param list<Block> $blocks
     *
     * @return list<array{string, string, array<array-key, mixed>}>
     */
    private function summary(array $blocks): array
    {
        return array_map(static fn (Block $block): array => [$block->id, $block->type, $block->data], $blocks);
    }
}
