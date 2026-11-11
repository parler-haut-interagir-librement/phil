<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplate\Tests\Unit;

use Phil\CommerceProductTemplate\Artwork;
use Phil\CommerceProductTemplate\AxisValue;
use Phil\CommerceProductTemplate\Combination;
use Phil\CommerceProductTemplate\Design;
use Phil\CommerceProductTemplate\Exception\InvalidDefinition;
use Phil\CommerceProductTemplate\Label;
use Phil\CommerceProductTemplate\LanguageVersion;
use Phil\CommerceProductTemplate\Tests\Unit\Fixture\Shirts;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Design::class)]
#[CoversClass(Artwork::class)]
final class DesignTest extends TestCase
{
    public function testADesignKeepsItsParts(): void
    {
        $design = Shirts::design();

        self::assertSame('NAQ001', $design->code);
        self::assertSame('The gnome', $design->name->in('en'));
        self::assertSame(['F', 'E'], array_map(static fn (LanguageVersion $language): string => $language->code, $design->languages()));
        self::assertSame('en', $design->language('E')?->locale);
        self::assertNull($design->language('D'));
        self::assertCount(4, $design->artworks);
        self::assertSame(['unisex'], $design->templates);
        self::assertSame(['version' => ['O', 'C']], $design->allowed);
    }

    public function testADesignIsMadeOnItsTemplatesOrOnAnyWhenItNamesNone(): void
    {
        self::assertTrue(Shirts::design()->isMadeOn('unisex'));
        self::assertFalse(Shirts::design()->isMadeOn('fitted'));
        self::assertTrue(Design::of('A', Label::of(['en' => 'A']))->isMadeOn('fitted'));
        self::assertSame(['a', 'b'], Design::of('A', Label::of(['en' => 'A']), templates: ['a', 'a', 'b'])->templates);
    }

    public function testADesignAllowsTheValuesItNamesAndEveryValueOfOtherAxes(): void
    {
        $design = Shirts::design();
        $value = static fn (string $code): AxisValue => AxisValue::of($code, Label::of(['en' => $code]));

        self::assertTrue($design->allows(new Combination(['version' => $value('O'), 'size' => $value('M')])));
        self::assertTrue($design->allows(new Combination(['version' => $value('C')])));
        self::assertFalse($design->allows(new Combination(['size' => $value('M'), 'version' => $value('L')])));
        self::assertTrue($design->allows(new Combination(['size' => $value('M')])));
    }

    public function testAnArtworkKeepsWhereItGoes(): void
    {
        $artwork = Artwork::of('front', 'art/a.png', 'chest', 'F', ['size' => ['M', 'L']]);

        self::assertSame(['front', 'art/a.png', 'chest', 'F', ['size' => ['M', 'L']]], [$artwork->face, $artwork->path, $artwork->zone, $artwork->language, $artwork->when]);
        self::assertSame(9 + 1, $artwork->specificity());
        self::assertSame(0, Artwork::of('front', 'a.png')->specificity());
        self::assertSame(2, Artwork::of('front', 'a.png', when: ['size' => ['M'], 'colour' => ['BK']])->specificity());
        self::assertSame(9, Artwork::of('front', 'a.png', language: 'F')->specificity());
    }

    public function testAnArtworkAppliesToItsLanguageAndCombinations(): void
    {
        $value = static fn (string $code): AxisValue => AxisValue::of($code, Label::of(['en' => $code]));
        $medium = new Combination(['size' => $value('M')]);
        $small = new Combination(['size' => $value('S')]);

        self::assertTrue(Artwork::of('front', 'a.png')->appliesTo($medium, null));
        self::assertTrue(Artwork::of('front', 'a.png')->appliesTo($medium, 'F'));
        self::assertTrue(Artwork::of('front', 'a.png', language: 'F')->appliesTo($medium, 'F'));
        self::assertFalse(Artwork::of('front', 'a.png', language: 'F')->appliesTo($medium, 'E'));
        self::assertFalse(Artwork::of('front', 'a.png', language: 'F')->appliesTo($medium, null));
        self::assertTrue(Artwork::of('front', 'a.png', when: ['size' => ['M']])->appliesTo($medium, null));
        self::assertFalse(Artwork::of('front', 'a.png', when: ['size' => ['M']])->appliesTo($small, null));
    }

    public function testTheSlotOfAnArtworkIgnoresTheOrderOfItsCondition(): void
    {
        $first = Artwork::of('front', 'a.png', 'chest', 'F', ['size' => ['M', 'L'], 'colour' => ['BK']]);
        $second = Artwork::of('front', 'b.png', 'chest', 'F', ['colour' => ['BK'], 'size' => ['L', 'M']]);

        self::assertSame('front|chest|F|{"colour":["BK"],"size":["L","M"]}', $first->slot());
        self::assertSame($first->slot(), $second->slot());
        self::assertSame('front|||[]', Artwork::of('front', 'a.png')->slot());
    }

    /**
     * @return iterable<string, array{callable(): mixed, string}>
     */
    public static function refusedArtworks(): iterable
    {
        yield 'a face with a capital' => [static fn (): Artwork => Artwork::of('Front', 'a.png'), '"Front" is not the code of a face: up to 32 lowercase letters, digits or "_", starting with a letter.'];
        yield 'a path outside the store' => [static fn (): Artwork => Artwork::of('front', '/a.png'), '"/a.png" is not the path of an artwork: up to 512 characters, segments of letters, digits, ".", "_" or "-", relative, never "." nor "..".'];
        yield 'a zone with a capital' => [static fn (): Artwork => Artwork::of('front', 'a.png', 'Chest'), '"Chest" is not the code of a print zone: up to 32 lowercase letters, digits or "_", starting with a letter.'];
        yield 'a long language' => [static fn (): Artwork => Artwork::of('front', 'a.png', language: 'ABCDEFGHI'), '"ABCDEFGHI" is not the code of a language version: 1 to 8 letters, digits, ".", "_" or "-", starting with a letter or digit.'];
        yield 'a condition of no list' => [static fn (): Artwork => Artwork::of('front', 'a.png', when: ['size' => []]), 'The condition of the artwork a.png names, by axis code, a list of one value code or more.'];
    }

    /**
     * @param callable(): mixed $build
     */
    #[DataProvider('refusedArtworks')]
    public function testAnArtworkRefusesWhatIsNotOne(callable $build, string $message): void
    {
        $this->expectExceptionObject(InvalidDefinition::because($message));

        $build();
    }

    /**
     * @return iterable<string, array{callable(): mixed, string}>
     */
    public static function refusedDesigns(): iterable
    {
        $name = Label::of(['en' => 'Gnome']);
        $languages = array_map(static fn (int $index): LanguageVersion => LanguageVersion::of('L'.$index, 'en'), range(1, 9));
        $artworks = array_map(static fn (int $index): Artwork => Artwork::of('front', 'a'.$index.'.png', when: ['size' => ['S'.$index]]), range(1, 201));

        yield 'a code with a space' => [static fn (): Design => Design::of('NAQ 001', $name), '"NAQ 001" is not the code of a design: 1 to 32 letters, digits, ".", "_" or "-", starting with a letter or digit.'];
        yield 'nine languages' => [static fn (): Design => Design::of('A', $name, $languages), 'The design A has at most 8 language versions.'];
        yield 'a language twice' => [static fn (): Design => Design::of('A', $name, [LanguageVersion::of('F', 'fr'), LanguageVersion::of('F', 'fr_CA')]), 'The design A lists the language F twice.'];
        yield 'too many artworks' => [static fn (): Design => Design::of('A', $name, artworks: $artworks), 'The design A has at most 200 artworks.'];
        yield 'an artwork of another language' => [static fn (): Design => Design::of('A', $name, [LanguageVersion::of('F', 'fr')], [Artwork::of('front', 'a.png', language: 'E')]), 'The artwork a.png belongs to the language E, which its design is not made in.'];
        yield 'two artworks in one slot' => [static fn (): Design => Design::of('A', $name, [], [Artwork::of('front', 'a.png'), Artwork::of('front', 'b.png')]), 'The design A has two artworks for the same zone, language and combinations: a.png and b.png.'];
        yield 'a template code with a space' => [static fn (): Design => Design::of('A', $name, templates: ['a b']), '"a b" is not the code of a template: 1 to 32 letters, digits, ".", "_" or "-", starting with a letter or digit.'];
        yield 'an allowed axis of nothing' => [static fn (): Design => Design::of('A', $name, allowed: ['size' => []]), 'The values the design A allows names, by axis code, a list of one value code or more.'];
    }

    /**
     * @param callable(): mixed $build
     */
    #[DataProvider('refusedDesigns')]
    public function testADesignRefusesWhatIsNotOne(callable $build, string $message): void
    {
        $this->expectExceptionObject(InvalidDefinition::because($message));

        $build();
    }

    public function testADesignTakesItsLimits(): void
    {
        $languages = array_map(static fn (int $index): LanguageVersion => LanguageVersion::of('L'.$index, 'en'), range(1, 8));
        $artworks = array_map(static fn (int $index): Artwork => Artwork::of('front', 'a'.$index.'.png', when: ['size' => ['S'.$index]]), range(1, 200));
        $design = Design::of(str_repeat('A', 32), Label::of(['en' => 'x']), $languages, $artworks);

        self::assertCount(8, $design->languages());
        self::assertCount(200, $design->artworks);
        self::assertSame([], Design::of('A', Label::of(['en' => 'A']))->languages());
    }
}
