<?php

declare(strict_types=1);

namespace Phil\Slug\Tests\Unit;

use Phil\Slug\InvalidSlug;
use Phil\Slug\LeadingWordsRemoved;
use Phil\Slug\Slug;
use Phil\Slug\Slugger;
use Phil\Slug\Sluggers;
use Phil\Slug\TransliteratingSlugger;
use Phil\Slug\UnknownSlugger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(TransliteratingSlugger::class)]
#[CoversClass(LeadingWordsRemoved::class)]
#[CoversClass(Sluggers::class)]
#[CoversClass(UnknownSlugger::class)]
#[CoversClass(Slug::class)]
#[CoversClass(InvalidSlug::class)]
final class SluggersTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function texts(): iterable
    {
        yield 'accents' => ['Jeux de rôle', 'jeux-de-role'];
        yield 'an apostrophe' => ["L'Ordre du Corbeau", 'l-ordre-du-corbeau'];
        yield 'ligatures and dashes' => ['Ça va? Œuvre — été', 'ca-va-oeuvre-ete'];
        yield 'surrounding spaces' => ['  Hello  World  ', 'hello-world'];
        yield 'digits' => ['Saison 2', 'saison-2'];
    }

    #[DataProvider('texts')]
    public function testATextIsTransliteratedInLowercaseWords(string $text, string $slug): void
    {
        self::assertSame($slug, new TransliteratingSlugger()->slug($text)->value);
    }

    public function testTheLocaleChoosesTheTransliteration(): void
    {
        self::assertSame(['strasse-uber', 'strasse-ueber'], [new TransliteratingSlugger()->slug('Straße über')->value, new TransliteratingSlugger('de')->slug('Straße über')->value]);
    }

    public function testALongSlugIsCutAtAWord(): void
    {
        self::assertSame('abcdef', new TransliteratingSlugger(null, 10)->slug('abcdef ghijkl')->value);
        self::assertSame('abcdef-ghi', new TransliteratingSlugger(null, 10)->slug('abcdef ghi jkl')->value);
        self::assertSame('abcdef-ghi', new TransliteratingSlugger(null, 10)->slug('abcdef ghi')->value);
        self::assertSame('abcde', new TransliteratingSlugger(null, 5)->slug('abcdefgh ij')->value);
        self::assertSame('abcde', new TransliteratingSlugger(null, 5)->slug('abcde fgh')->value);
        self::assertSame('a', new TransliteratingSlugger(null, 1)->slug('ab')->value);
        self::assertSame('abcd', new TransliteratingSlugger(null, 4)->slug('abcde fg')->value);
        self::assertSame(255, \strlen(new TransliteratingSlugger()->slug(str_repeat('a', 300))->value));
        self::assertSame(str_repeat('a', 254), new TransliteratingSlugger()->slug(str_repeat('a', 254).' b')->value);
    }

    public function testATextWithNothingToSlugIsRefused(): void
    {
        $this->expectException(InvalidSlug::class);
        $this->expectExceptionMessageIsOrContains('" ?! " holds no letter or digit to make a slug of.');

        new TransliteratingSlugger()->slug(' ?! ');
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function badLengths(): iterable
    {
        yield 'zero' => [0];
        yield 'beyond a slug' => [256];
    }

    #[DataProvider('badLengths')]
    public function testTheMaximumLengthIsThatOfASlug(int $length): void
    {
        $this->expectException(InvalidSlug::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The maximum length of a slug is between 1 and 255; %d is not.', $length));

        new TransliteratingSlugger(null, $length);
    }

    public function testALeadingWordIsDroppedOnce(): void
    {
        $slugger = new LeadingWordsRemoved(new TransliteratingSlugger(), ['le', 'la', 'les', 'l', 'the', 'gn']);

        self::assertSame(
            ['ordre', 'guild', 'les-terres', 'les', 'gardiens-du-seuil', 'legende'],
            array_map(static fn (string $text): string => $slugger->slug($text)->value, ["L'Ordre", 'The Guild', 'GN Les Terres', 'Les', 'Gardiens du seuil', 'Légende']),
        );
    }

    public function testADecoratorChangesTheSlugOfTheSluggerItWraps(): void
    {
        $inner = new class implements Slugger {
            public function slug(string $text): Slug
            {
                return Slug::fromString('the-'.$text);
            }
        };

        self::assertSame('x', new LeadingWordsRemoved($inner, ['the'])->slug('x')->value);
    }

    public function testALeadingWordIsCheckedWithoutASlugger(): void
    {
        self::assertSame(['l', 'gn2'], [LeadingWordsRemoved::checkWord('l'), LeadingWordsRemoved::checkWord('gn2')]);
        $this->expectException(InvalidSlug::class);
        $this->expectExceptionMessageIsOrContains('"La" is not one word of lowercase ASCII letters and digits.');

        LeadingWordsRemoved::checkWord('La');
    }

    public function testALeadingWordIsOneSlugWord(): void
    {
        $this->expectException(InvalidSlug::class);
        $this->expectExceptionMessageIsOrContains('"l\'" is not one word of lowercase ASCII letters and digits.');

        new LeadingWordsRemoved(new TransliteratingSlugger(), ['le', "l'"]);
    }

    public function testALeadingWordEndsWithItsLastLetter(): void
    {
        $this->expectException(InvalidSlug::class);

        new LeadingWordsRemoved(new TransliteratingSlugger(), ["le\n"]);
    }

    public function testEveryLeadingWordIsChecked(): void
    {
        $this->expectException(InvalidSlug::class);
        $this->expectExceptionMessageIsOrContains('"The" is not one word of lowercase ASCII letters and digits.');

        new LeadingWordsRemoved(new TransliteratingSlugger(), ['The']);
    }

    public function testSluggersAreFoundByName(): void
    {
        $default = new TransliteratingSlugger();
        $names = new LeadingWordsRemoved($default, ['the']);
        $sluggers = new Sluggers(['default' => $default, 'names' => $names], 'default');

        self::assertSame([$default, $default, $names], [$sluggers->get(), $sluggers->get('default'), $sluggers->get('names')]);
        self::assertSame(['the-guild', 'guild'], [$sluggers->slug('The Guild')->value, $sluggers->slug('The Guild', 'names')->value]);
        self::assertSame(['default', 'names'], $sluggers->names());
    }

    public function testAnUnknownNameIsRefused(): void
    {
        $this->expectException(UnknownSlugger::class);
        $this->expectExceptionMessageIsOrContains('No slugger is named "titles"; the sluggers are: default, names.');

        new Sluggers(['default' => new TransliteratingSlugger(), 'names' => new TransliteratingSlugger()], 'default')->get('titles');
    }

    public function testTheDefaultIsOneOfTheSluggers(): void
    {
        $this->expectException(UnknownSlugger::class);
        $this->expectExceptionMessageIsOrContains('No slugger is named "default"; the sluggers are: names.');

        new Sluggers(['names' => new TransliteratingSlugger()], 'default');
    }
}
