<?php

declare(strict_types=1);

namespace Phil\BrailleExportLiblouis\Tests\Unit;

use InvalidArgumentException;
use Phil\BrailleExport\Contraction;
use Phil\BrailleExportLiblouis\LiblouisTables;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(LiblouisTables::class)]
final class LiblouisTablesTest extends TestCase
{
    public function testTheDefaultTables(): void
    {
        $tables = LiblouisTables::defaults();

        self::assertSame('en-ueb-g1.ctb', $tables->table('en', Contraction::Uncontracted));
        self::assertSame('en-ueb-g2.ctb', $tables->table('en', Contraction::Contracted));
        self::assertSame('fr-bfu-comp6.utb', $tables->table('fr', Contraction::Uncontracted));
        self::assertSame('fr-bfu-g2.ctb', $tables->table('fr', Contraction::Contracted));
        self::assertNull($tables->table('de', Contraction::Uncontracted));
    }

    public function testARegionFallsBackToItsLanguage(): void
    {
        $tables = new LiblouisTables([
            'en' => ['contracted' => 'en-ueb-g2.ctb'],
            'en-US' => ['contracted' => 'en-us-g2.ctb'],
            'pt_BR' => ['uncontracted' => 'pt-br-g1.utb'],
        ]);

        self::assertSame('en-us-g2.ctb', $tables->table('en_us', Contraction::Contracted));
        self::assertSame('en-ueb-g2.ctb', $tables->table('EN-gb', Contraction::Contracted));
        self::assertSame('en-ueb-g2.ctb', $tables->table('en', Contraction::Contracted));
        self::assertNull($tables->table('en', Contraction::Uncontracted));
        self::assertNull($tables->table('en-US', Contraction::Uncontracted));
        self::assertSame('pt-br-g1.utb', $tables->table('pt-BR', Contraction::Uncontracted));
        self::assertNull($tables->table('pt', Contraction::Uncontracted));
    }

    public function testWithSetsOneTable(): void
    {
        $tables = LiblouisTables::defaults();
        $changed = $tables->with('FR_ca', Contraction::Contracted, 'fr-ca-g2.ctb,extra.cti');

        self::assertSame('fr-ca-g2.ctb,extra.cti', $changed->table('fr-CA', Contraction::Contracted));
        self::assertSame('fr-bfu-g2.ctb', $changed->table('fr', Contraction::Contracted));
        self::assertSame('fr-bfu-g2.ctb', $tables->table('fr-CA', Contraction::Contracted));
    }

    /**
     * @return iterable<string, array{array<string, array<string, string>>, string}>
     */
    public static function invalid(): iterable
    {
        yield 'a path' => [['fr' => ['contracted' => '../fr.ctb']], '"../fr.ctb" is not a list of liblouis table names.'];
        yield 'an absolute path' => [['fr' => ['contracted' => '/etc/passwd']], 'is not a list of liblouis table names.'];
        yield 'an empty table' => [['fr' => ['contracted' => '']], 'is not a list of liblouis table names.'];
        yield 'an empty name in a list' => [['fr' => ['contracted' => 'a.ctb,']], 'is not a list of liblouis table names.'];
        yield 'a space' => [['fr' => ['contracted' => 'a b']], 'is not a list of liblouis table names.'];
        yield 'an option' => [['fr' => ['contracted' => '-v']], 'is not a list of liblouis table names.'];
        yield 'a line feed' => [['fr' => ['contracted' => "a.ctb\n"]], 'is not a list of liblouis table names.'];
        yield 'too long a name' => [['fr' => ['contracted' => str_repeat('a', 129)]], 'is not a list of liblouis table names.'];
        yield 'too many tables' => [['fr' => ['contracted' => implode(',', array_fill(0, 9, 'a'))]], 'is not a list of liblouis table names.'];
        yield 'another contraction' => [['fr' => ['grade3' => 'a.ctb']], '"grade3" is neither "uncontracted" nor "contracted".'];
        yield 'a language that is not a tag' => [['french' => ['contracted' => 'a.ctb']], '"french" is not a language tag.'];
        yield 'an empty language' => [['' => ['contracted' => 'a.ctb']], '"" is not a language tag.'];
        yield 'a language with a line feed' => [["fr\n" => ['contracted' => 'a.ctb']], 'is not a language tag.'];
    }

    /**
     * @param array<string, array<string, string>> $tables
     */
    #[DataProvider('invalid')]
    public function testTheTablesAreChecked(array $tables, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains($message);

        new LiblouisTables($tables);
    }

    public function testTheLongestAcceptedNamesAndLists(): void
    {
        $longest = str_repeat('a', 128);
        $list = implode(',', array_fill(0, 8, 'a'));
        $tables = new LiblouisTables(['fra' => ['contracted' => $longest, 'uncontracted' => $list], 'de-1901' => ['contracted' => 'de.ctb']]);

        self::assertSame($longest, $tables->table('fra', Contraction::Contracted));
        self::assertSame($list, $tables->table('fra', Contraction::Uncontracted));
        self::assertSame('de.ctb', $tables->table('de-1901', Contraction::Contracted));
    }
}
