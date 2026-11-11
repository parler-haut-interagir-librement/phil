<?php

declare(strict_types=1);

namespace Phil\Passwords\Tests\Unit\Strength;

use Phil\Passwords\Exception\InvalidDictionary;
use Phil\Passwords\Strength\Dictionary;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Dictionary::class)]
final class DictionaryTest extends TestCase
{
    public function testRanksFromOneLowercaseTheFirstKept(): void
    {
        $dictionary = Dictionary::of('site', ['Gnome', 'archiviste', 'GNOME', '2026']);

        self::assertSame('site', $dictionary->name);
        self::assertSame(1, $dictionary->rank('gnome'));
        self::assertSame(2, $dictionary->rank('archiviste'));
        self::assertSame(3, $dictionary->rank('2026'));
        self::assertNull($dictionary->rank('Gnome'));
        self::assertSame(3, $dictionary->count());
        self::assertSame(10, $dictionary->maxLength);
        self::assertSame(['gnome' => 1, 'archiviste' => 2, 2026 => 3], $dictionary->ranks());
    }

    public function testEntryLengths(): void
    {
        self::assertSame(64, Dictionary::of('long', [str_repeat('é', 64)])->maxLength);
        self::assertSame(0, Dictionary::of('empty', [])->maxLength);
    }

    public function testANameIsRequired(): void
    {
        $this->expectException(InvalidDictionary::class);

        Dictionary::of('', ['a']);
    }

    public function testAnEmptyEntryIsRefused(): void
    {
        $this->expectExceptionObject(new InvalidDictionary('The entries of the dictionary "site" have 1 to 64 characters.'));

        Dictionary::of('site', ['ok', '']);
    }

    public function testATooLongEntryIsRefused(): void
    {
        $this->expectException(InvalidDictionary::class);

        Dictionary::of('site', [str_repeat('a', 65)]);
    }

    public function testTheBundledDictionaries(): void
    {
        $names = array_map(static fn (Dictionary $dictionary): string => $dictionary->name, Dictionary::bundled());

        self::assertSame(['passwords', 'names', 'surnames', 'english', 'french', 'spanish', 'german'], $names);
        self::assertSame(1, Dictionary::named(Dictionary::PASSWORDS)->rank('123456'));
        self::assertSame(2, Dictionary::named(Dictionary::PASSWORDS)->rank('password'));
        self::assertNotNull(Dictionary::named(Dictionary::FRENCH)->rank('soleil'));
        self::assertNotNull(Dictionary::named(Dictionary::NAMES)->rank('marie'));
        self::assertNotNull(Dictionary::named(Dictionary::SURNAMES)->rank('tremblay'));

        foreach (Dictionary::bundled() as $dictionary) {
            self::assertGreaterThan(100, $dictionary->count());
        }
    }

    public function testAnUnknownBundledDictionary(): void
    {
        $this->expectExceptionObject(new InvalidDictionary('No dictionary named "user_inputs" is bundled.'));

        Dictionary::named(Dictionary::USER_INPUTS);
    }

    public function testUserInputs(): void
    {
        $dictionary = Dictionary::ofUserInputs(['  Jean.Dupont@Example.com ', '', 'Jo Li', str_repeat('x', 70)]);

        self::assertSame(Dictionary::USER_INPUTS, $dictionary->name);
        self::assertSame(
            ['jean.dupont@example.com' => 1, 'jean.dupont' => 2, 'example.com' => 3, 'jean' => 4, 'dupont' => 5, 'example' => 6, 'com' => 7, 'jo li' => 8, str_repeat('x', 64) => 9],
            $dictionary->ranks(),
        );
    }

    public function testCaseOfEveryScript(): void
    {
        self::assertSame(1, Dictionary::of('site', ['ÉTÉ'])->rank('été'));
        self::assertSame(
            ['élodie' => 1],
            Dictionary::ofUserInputs(['ÉLODIE'])->ranks(),
        );
        self::assertSame([str_repeat('é', 64) => 1], Dictionary::ofUserInputs([str_repeat('é', 70)])->ranks());
        // `éa` is two characters, too short to be a part of its own.
        self::assertSame(['éa bcd' => 1, 'bcd' => 2], Dictionary::ofUserInputs(['éa bcd'])->ranks());
    }
}
