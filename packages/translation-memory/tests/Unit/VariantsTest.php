<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Tests\Unit;

use Phil\TranslationMemory\Exception\InvalidLanguage;
use Phil\TranslationMemory\LanguageTag;
use Phil\TranslationMemory\Segment;
use Phil\TranslationMemory\SegmentState;
use Phil\TranslationMemory\Variants;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Variants::class)]
#[CoversClass(InvalidLanguage::class)]
final class VariantsTest extends TestCase
{
    public function testCanadianFrenchAndEnglishInheritByDefault(): void
    {
        $variants = new Variants();

        self::assertSame(['fr-CA', 'en-CA'], $variants->variants());
        self::assertSame('fr', $variants->parentOf(LanguageTag::of('fr-ca'))?->value);
        self::assertSame('en', $variants->parentOf(LanguageTag::of('en-CA'))?->value);
        self::assertNull($variants->parentOf(LanguageTag::of('pt-BR')));
        self::assertNull($variants->parentOf(LanguageTag::of('fr')));
        self::assertSame(['fr-CA', 'fr'], $this->tags($variants->chain(LanguageTag::of('fr-CA'))));
        self::assertSame(['pt-BR'], $this->tags($variants->chain(LanguageTag::of('pt-BR'))));
        self::assertSame([], Variants::none()->variants());
        self::assertSame(['fr-CA'], $this->tags(Variants::none()->chain(LanguageTag::of('fr-CA'))));
    }

    public function testFollowsAChainOfVariants(): void
    {
        $variants = new Variants(['zh-hant-tw', LanguageTag::of('zh-Hant'), 'pt-BR']);

        self::assertSame(['zh-Hant-TW', 'zh-Hant', 'pt-BR'], $variants->variants());
        self::assertSame(['zh-Hant-TW', 'zh-Hant', 'zh'], $this->tags($variants->chain(LanguageTag::of('zh-Hant-TW'))));
    }

    public function testRefusesAPrimaryLanguageAsAVariant(): void
    {
        $this->expectException(InvalidLanguage::class);
        $this->expectExceptionMessageIsOrContains('"fr" is a primary language');

        new Variants(['fr-CA', 'fr']);
    }

    public function testAVariantTakesTheUsableTargetsOfItsParentWhereItHasNone(): void
    {
        $own = [
            new Segment('a', 'Hello', SegmentState::Translated, 'Allô'),
            new Segment('b', 'Goodbye'),
            new Segment('c', 'Yes', SegmentState::Suggested, 'Ouais'),
            new Segment('d', 'No', SegmentState::Stale, 'Nan', 'Nope'),
            new Segment('e', 'Car'),
        ];
        $parent = [
            new Segment('a', 'Hello', SegmentState::Reviewed, 'Bonjour'),
            new Segment('b', 'Goodbye', SegmentState::Translated, 'Au revoir'),
            new Segment('c', 'Yes', SegmentState::Reviewed, 'Oui'),
            new Segment('d', 'No', SegmentState::Translated, 'Non'),
            new Segment('e', 'Truck', SegmentState::Translated, 'Camion'),
            new Segment('f', 'Bike', SegmentState::Translated, 'Vélo'),
            new Segment('g', 'Boat', SegmentState::Suggested, 'Bateau'),
        ];

        self::assertSame(
            [['a', 'Allô'], ['b', 'Au revoir'], ['c', 'Oui'], ['d', 'Non'], ['e', null], ['f', 'Vélo']],
            array_map(static fn (Segment $segment): array => [$segment->key, $segment->target], new Variants()->inherit($own, $parent)),
        );
        self::assertSame([['f', 'Vélo']], array_map(static fn (Segment $segment): array => [$segment->key, $segment->target], new Variants()->inherit([], [$parent[5], $parent[6]])));
        self::assertSame($own, new Variants()->inherit($own, []));
    }

    /**
     * @param list<LanguageTag> $tags
     *
     * @return list<string>
     */
    private function tags(array $tags): array
    {
        return array_map(static fn (LanguageTag $tag): string => $tag->value, $tags);
    }
}
