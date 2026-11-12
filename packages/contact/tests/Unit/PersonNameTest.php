<?php

declare(strict_types=1);

namespace Phil\Contact\Tests\Unit;

use Phil\Contact\Exception\InvalidContact;
use Phil\Contact\NameConvention;
use Phil\Contact\NameConventions;
use Phil\Contact\NameOrder;
use Phil\Contact\PersonName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PersonName::class)]
#[CoversClass(NameConvention::class)]
#[CoversClass(NameConventions::class)]
#[CoversClass(NameOrder::class)]
#[CoversClass(InvalidContact::class)]
final class PersonNameTest extends TestCase
{
    /**
     * Real names, as their cultures write and sort them.
     *
     * @return iterable<string, array{PersonName, string, string, ?string}>
     */
    public static function names(): iterable
    {
        yield 'French, de dropped' => [PersonName::of('Charles', 'Gaulle', 'de', culture: 'fr'), 'Charles de Gaulle', 'Gaulle, Charles de', 'Gaulle'];
        yield 'French, de dropped and La kept' => [PersonName::of('Jean', 'Fontaine', 'de La', culture: 'fr-FR'), 'Jean de La Fontaine', 'La Fontaine, Jean de', 'La Fontaine'];
        yield 'French, du kept with a capital' => [PersonName::of('Joachim', 'Bellay', 'du', culture: 'fr'), 'Joachim du Bellay', 'Du Bellay, Joachim', 'du Bellay'];
        yield "French, d' elided" => [PersonName::of('Jean', 'Ormesson', "d'", culture: 'fr'), "Jean d'Ormesson", "Ormesson, Jean d'", 'Ormesson'];
        yield 'French, d’ elided' => [PersonName::of('Jean', 'Ormesson', 'd’', culture: 'fr_CA'), 'Jean d’Ormesson', 'Ormesson, Jean d’', 'Ormesson'];
        yield 'French, a dropped word inside another' => [PersonName::of('Anne', 'Lys', 'des', culture: 'fr'), 'Anne des Lys', 'Des Lys, Anne', 'des Lys'];
        yield 'Dutch of the Netherlands' => [PersonName::of('Vincent', 'Gogh', 'van', culture: 'nl-NL'), 'Vincent van Gogh', 'Gogh, Vincent van', 'Gogh'];
        yield 'Dutch of Belgium' => [PersonName::of('Herman', 'Rompuy', 'Van', culture: 'nl-BE'), 'Herman Van Rompuy', 'Van Rompuy, Herman', 'Van Rompuy'];
        yield 'Dutch without a country' => [PersonName::of('Vincent', 'Gogh', 'van', culture: 'nl'), 'Vincent van Gogh', 'Gogh, Vincent van', 'Gogh'];
        yield 'German' => [PersonName::of('Ursula', 'Leyen', 'von der', culture: 'de'), 'Ursula von der Leyen', 'Leyen, Ursula von der', 'Leyen'];
        yield 'Spanish, two family names' => [PersonName::of('Gabriel', 'García', secondFamily: 'Márquez', culture: 'es'), 'Gabriel García Márquez', 'García Márquez, Gabriel', 'García'];
        yield 'Spanish, de dropped and las kept' => [PersonName::of('Bartolomé', 'Casas', 'de las', culture: 'es'), 'Bartolomé de las Casas', 'Las Casas, Bartolomé de', 'las Casas'];
        yield 'Portuguese' => [PersonName::of('Vasco', 'Gama', 'da', culture: 'pt'), 'Vasco da Gama', 'Gama, Vasco da', 'Gama'];
        yield 'Italian' => [PersonName::of('Alessandro', 'Piero', 'Del', culture: 'it'), 'Alessandro Del Piero', 'Del Piero, Alessandro', 'Del Piero'];
        yield 'English' => [PersonName::of('Martin', 'Buren', 'Van', culture: 'en-US'), 'Martin Van Buren', 'Van Buren, Martin', 'Van Buren'];
        yield 'English, generation' => [PersonName::of('Martin', 'King', additional: ['Luther'], generation: 'Jr.', culture: 'en'), 'Martin Luther King Jr.', 'King, Martin Luther, Jr.', 'King'];
        yield 'no culture' => [PersonName::of('Ada', 'Lovelace', additional: ['Augusta']), 'Ada Augusta Lovelace', 'Lovelace, Ada Augusta', 'Lovelace'];
        yield 'Chinese, its characters' => [PersonName::of('泽东', '毛', culture: 'zh'), '毛泽东', '毛, 泽东', '毛'];
        yield 'Chinese, romanised' => [PersonName::of('Zedong', 'Mao', culture: 'zh-Hans'), 'Mao Zedong', 'Mao, Zedong', 'Mao'];
        yield 'Japanese, its characters' => [PersonName::of('駿', '宮崎', culture: 'ja'), '宮崎駿', '宮崎, 駿', '宮崎'];
        yield 'Korean, its characters' => [PersonName::of('연아', '김', culture: 'ko'), '김연아', '김, 연아', '김'];
        yield 'Korean, romanised family name' => [PersonName::of('연아', 'Kim', culture: 'ko'), 'Kim 연아', 'Kim, 연아', 'Kim'];
        yield 'Hungarian' => [PersonName::of('Béla', 'Bartók', culture: 'hu'), 'Bartók Béla', 'Bartók, Béla', 'Bartók'];
        yield 'Vietnamese, by the given name' => [PersonName::of('Giáp', 'Võ', additional: ['Nguyên'], culture: 'vi'), 'Võ Nguyên Giáp', 'Giáp, Võ Nguyên', 'Võ'];
        yield 'Icelandic, by the given name' => [PersonName::of('Björk', 'Guðmundsdóttir', culture: 'is'), 'Björk Guðmundsdóttir', 'Björk, Guðmundsdóttir', 'Guðmundsdóttir'];
        yield 'Icelandic, with additional names' => [PersonName::of('Jón', 'Sigurðsson', additional: ['Páll'], culture: 'is'), 'Jón Páll Sigurðsson', 'Jón, Páll Sigurðsson', 'Sigurðsson'];
        yield 'Icelandic, a family name only' => [PersonName::of(family: 'Guðmundsdóttir', culture: 'is'), 'Guðmundsdóttir', 'Guðmundsdóttir', 'Guðmundsdóttir'];
        yield 'a given name only' => [PersonName::of('Madonna'), 'Madonna', 'Madonna', null];
        yield 'a family name only, its particle dropped' => [PersonName::of(family: 'Gaulle', particle: 'de', culture: 'fr'), 'de Gaulle', 'Gaulle, de', 'Gaulle'];
    }

    #[DataProvider('names')]
    public function testANameIsWrittenAndSortedAsItsCultureDoes(PersonName $name, string $display, string $sortKey, ?string $sortFamily): void
    {
        self::assertSame([$display, $sortKey, $sortFamily], [$name->display(), $name->sortKey(), $name->sortFamily()]);
    }

    public function testTheFormalNameAndTheParts(): void
    {
        $name = PersonName::of(' Ada ', ' Lovelace ', additional: [' Augusta ', ' '], secondFamily: ' King ', honorific: ' Dr. ', generation: ' II ', credential: ' PhD ', culture: ' en_GB ');

        self::assertSame(['Ada', 'Lovelace', null, ['Augusta'], 'King', 'Dr.', 'II', 'PhD', 'en-GB', null], [$name->given, $name->family, $name->particle, $name->additional, $name->secondFamily, $name->honorific, $name->generation, $name->credential, $name->culture, $name->order]);
        self::assertSame('Dr. Ada Augusta Lovelace King II, PhD', $name->formal());
        self::assertSame('Ada Augusta Lovelace King II', $name->display());
        self::assertSame(['Lovelace King', 'Lovelace'], [$name->familyName(), $name->surname()]);
        self::assertSame('Ada', PersonName::of('Ada')->formal());
        self::assertSame([null, null], [PersonName::of('Ada')->familyName(), PersonName::of('Ada')->surname()]);
        self::assertSame("d'Ormesson", PersonName::of('Jean', 'Ormesson', "d'")->surname());
    }

    public function testAnOrderGivenWinsOverTheCulture(): void
    {
        $hungarian = PersonName::of('Béla', 'Bartók', culture: 'hu', order: NameOrder::GivenFirst);
        $english = PersonName::of('Ada', 'Lovelace', additional: ['Augusta'], order: NameOrder::FamilyFirst);

        self::assertSame(['Béla Bartók', NameOrder::GivenFirst], [$hungarian->display(), $hungarian->displayOrder()]);
        self::assertSame(['Lovelace Augusta Ada', NameOrder::FamilyFirst], [$english->display(), $english->displayOrder()]);
        self::assertSame('Ada Lovelace', PersonName::of('Ada', 'Lovelace', culture: 'hu')->display(new NameConvention()));
        self::assertSame('Giáp, Võ Nguyên', PersonName::of('Giáp', 'Võ', additional: ['Nguyên'], order: NameOrder::FamilyFirst)->sortKey(new NameConvention(sortsByGiven: true)));
        self::assertSame('Giáp, Nguyên Võ', PersonName::of('Giáp', 'Võ', additional: ['Nguyên'])->sortKey(new NameConvention(sortsByGiven: true)));
    }

    public function testTheConventionOfTheCultureOrTheOneGiven(): void
    {
        $convention = new NameConvention(NameOrder::FamilyFirst);

        self::assertSame($convention, PersonName::of('Ada', culture: 'fr')->convention($convention));
        self::assertEquals(NameConventions::for('fr'), PersonName::of('Ada', culture: 'fr')->convention());
        self::assertEquals(new NameConvention(), NameConventions::for(null));
        self::assertEquals(new NameConvention(), NameConventions::for('tlh'));
        self::assertSame(['', ' ', ' '], [PersonName::of('泽东', '毛', culture: 'zh')->separator(), PersonName::of('Zedong', '毛', culture: 'zh')->separator(), PersonName::of('泽东', '毛')->separator()]);
        self::assertSame('毛 泽东', PersonName::of('泽东', '毛', culture: 'zh')->display(new NameConvention(NameOrder::FamilyFirst)));
        self::assertSame('毛泽东 二世', PersonName::of('泽东', '毛', generation: '二世', culture: 'zh')->display());
    }

    public function testAConventionKeepsWhatAParticleDoesNotDrop(): void
    {
        $french = new NameConvention(droppedParticles: ['de', "d'"]);

        self::assertSame(['', 'La', 'du', '', 'Dela', 'des', ''], [$french->sortedParticle('de'), $french->sortedParticle('de La'), $french->sortedParticle('du'), $french->sortedParticle("D'"), $french->sortedParticle('Dela'), $french->sortedParticle('des'), new NameConvention(dropsWholeParticle: true)->sortedParticle('van der')]);
        self::assertSame('van der', new NameConvention()->sortedParticle('van der'));
        self::assertSame("d'", new NameConvention(droppedParticles: ['de'])->sortedParticle("d'"));
    }

    public function testEquality(): void
    {
        $name = PersonName::of('Ada', 'Lovelace', culture: 'en');

        self::assertTrue($name->equals(PersonName::of('Ada', 'Lovelace', culture: 'en')));

        foreach ([
            PersonName::of('Augusta', 'Lovelace', culture: 'en'),
            PersonName::of('Ada', 'King', culture: 'en'),
            PersonName::of('Ada', 'Lovelace', 'de', culture: 'en'),
            PersonName::of('Ada', 'Lovelace', additional: ['Augusta'], culture: 'en'),
            PersonName::of('Ada', 'Lovelace', secondFamily: 'King', culture: 'en'),
            PersonName::of('Ada', 'Lovelace', honorific: 'Dr.', culture: 'en'),
            PersonName::of('Ada', 'Lovelace', generation: 'II', culture: 'en'),
            PersonName::of('Ada', 'Lovelace', credential: 'PhD', culture: 'en'),
            PersonName::of('Ada', 'Lovelace', culture: 'fr'),
            PersonName::of('Ada', 'Lovelace', culture: 'en', order: NameOrder::GivenFirst),
        ] as $other) {
            self::assertFalse($name->equals($other), $other->formal());
        }
    }

    /**
     * @return iterable<string, array{callable(): PersonName, string}>
     */
    public static function refusals(): iterable
    {
        yield 'no name' => [static fn (): PersonName => PersonName::of(' ', honorific: 'Dr.'), 'A person name has a given name or a family name.'];
        yield 'a particle alone' => [static fn (): PersonName => PersonName::of('Ada', particle: 'de'), 'The particle of a person name goes with a family name.'];
        yield 'too many additional names' => [static fn (): PersonName => PersonName::of('Ada', additional: array_fill(0, 11, 'A')), 'A person name has 10 additional names or fewer.'];
        yield 'a part too long' => [static fn (): PersonName => PersonName::of(str_repeat('é', 256)), 'The given name of a contact card is 255 characters or fewer.'];
        yield 'a line break' => [static fn (): PersonName => PersonName::of("Ada\nLovelace"), 'The given name of a person name is UTF-8 text without control characters.'];
        yield 'a broken text' => [static fn (): PersonName => PersonName::of(family: "Love\xC3"), 'The family name of a person name is UTF-8 text without control characters.'];
        yield 'an additional name with a tab' => [static fn (): PersonName => PersonName::of('Ada', additional: ["Au\tgusta"]), 'The additional name of a person name is UTF-8 text without control characters.'];
        yield 'no language tag' => [static fn (): PersonName => PersonName::of('Ada', culture: 'français'), '"français" is not a language tag.'];
        yield 'a language tag too long' => [static fn (): PersonName => PersonName::of('Ada', culture: 'fr'.str_repeat('-abcdefgh', 4)), '"fr-abcdefgh-abcdefgh-abcdefgh-abcdefgh" is not a language tag.'];
        yield 'a subtag too long' => [static fn (): PersonName => PersonName::of('Ada', culture: 'fr-abcdefghi'), '"fr-abcdefghi" is not a language tag.'];
    }

    /**
     * @param callable(): PersonName $name
     */
    #[DataProvider('refusals')]
    public function testARefusedName(callable $name, string $message): void
    {
        $this->expectException(InvalidContact::class);
        $this->expectExceptionMessageIsOrContains($message);

        $name();
    }

    public function testTheLimits(): void
    {
        self::assertCount(10, PersonName::of('Ada', additional: array_fill(0, 10, 'A'))->additional);
        self::assertCount(1, PersonName::of('Ada', additional: ['A', ...array_fill(0, 12, ' ')])->additional);
        self::assertSame(255, mb_strlen((string) PersonName::of(str_repeat('é', 255))->given));
        self::assertSame('fr-abcdefgh-abcdefgh-abcdefgh-abcde', PersonName::of('Ada', culture: 'fr-abcdefgh-abcdefgh-abcdefgh-abcde')->culture);
        self::assertSame('tlh', PersonName::of('Ada', culture: 'tlh')->culture);
        self::assertNull(PersonName::of('Ada', culture: ' ')->culture);
    }

    public function testAnAdditionalNameLeftOutLeavesAList(): void
    {
        self::assertSame(['B', 'C'], PersonName::of('Ada', additional: [' ', 'B', '', 'C'])->additional);
    }

    public function testASortKeyByTheGivenNameKeepsEveryAdditionalName(): void
    {
        $name = PersonName::of('Nguyên', 'Giáp', additional: ['Võ', 'Văn'], culture: 'vi');

        self::assertSame('Nguyên, Giáp Võ Văn', $name->sortKey());
        self::assertSame('Nguyên, Võ Văn Giáp', PersonName::of('Nguyên', 'Giáp', additional: ['Võ', 'Văn'], culture: 'vi', order: NameOrder::GivenFirst)->sortKey());
    }

    public function testASortKeyKeepsTheCapitalOfWhatItKeepsOfAParticleOnly(): void
    {
        self::assertSame('gogh, Vincent', PersonName::of('Vincent', 'gogh')->sortKey());
        self::assertSame("Élan Gaulle, Charles d'", PersonName::of('Charles', 'Gaulle', "d'élan", culture: 'fr')->sortKey());
        self::assertSame('Élan Gaulle, Charles d’', PersonName::of('Charles', 'Gaulle', 'd’élan', culture: 'fr')->sortKey());
    }

    public function testALongLanguageTagIsCutAtFortyCharactersInTheReason(): void
    {
        try {
            PersonName::of('Ada', culture: str_repeat('é', 50));
            self::fail('A language tag that is none was taken.');
        } catch (InvalidContact $invalidContact) {
            self::assertSame(\sprintf('"%s" is not a language tag.', str_repeat('é', 40)), $invalidContact->getMessage());
        }
    }

    public function testADroppedWordInTheConventionIsFoundInAnyCase(): void
    {
        $convention = new NameConvention(droppedParticles: ['état', "d'", 'd’']);

        self::assertSame('du', $convention->sortedParticle('État du'));
        self::assertSame('Ar', $convention->sortedParticle("d'Ar"));
        self::assertSame('Ar', $convention->sortedParticle('d’Ar'));
        self::assertSame('Ar', $convention->sortedParticle('D’Ar'));
    }
}
