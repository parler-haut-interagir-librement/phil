<?php

declare(strict_types=1);

namespace Phil\ImportWizard\Tests\Unit\Profile;

use Phil\ImportWizard\Exception\InvalidProfile;
use Phil\ImportWizard\Lms\LmsProfiles;
use Phil\ImportWizard\Profile\Column;
use Phil\ImportWizard\Profile\ColumnMapping;
use Phil\ImportWizard\Profile\HeaderName;
use Phil\ImportWizard\Profile\ImportProfile;
use Phil\ImportWizard\Profile\ProfileData;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ColumnMapping::class)]
#[CoversClass(HeaderName::class)]
#[CoversClass(ProfileData::class)]
final class ColumnMappingTest extends TestCase
{
    public function testHeadersAreFoldedForComparison(): void
    {
        self::assertSame('prenom', HeaderName::fold(' Prénom '));
        self::assertSame('nomdefamille', HeaderName::fold('NOM-DE-FAMILLE'));
        self::assertSame('aeoessaaaaaaceeeeiiiinoooooouuuuyy', HeaderName::fold('æœßàáâãäåçèéêëìíîïñòóôõöøùúûüýÿ'));
        self::assertSame('email2', HeaderName::fold('E-mail #2'));
        self::assertSame('', HeaderName::fold('---'));
    }

    public function testItGuessesTheColumnsFromTheHeaders(): void
    {
        $mapping = ColumnMapping::guess(['Courriel', 'Prénom', 'Nom de famille', 'Notes', 'Unité'], LmsProfiles::people());

        self::assertSame([
            ['header' => 'Courriel', 'column' => 'email'],
            ['header' => 'Prénom', 'column' => 'given_name'],
            ['header' => 'Nom de famille', 'column' => 'family_name'],
            ['header' => 'Unité', 'column' => 'unit_code'],
        ], $mapping->toArray());
        self::assertSame([0 => 'email', 1 => 'given_name', 2 => 'family_name', 4 => 'unit_code'], $mapping->resolve(['Courriel', 'Prénom', 'Nom de famille', 'Notes', 'Unité']));
    }

    public function testAGuessMatchesTheHeaderAndTheNameOfAColumn(): void
    {
        $profile = new ImportProfile('thing', [new Column('code', required: true, header: 'Reference')], ['code']);

        self::assertSame([['header' => 'REFERENCE', 'column' => 'code']], ColumnMapping::guess(['REFERENCE'], $profile)->toArray());
        self::assertSame([['header' => 'Code', 'column' => 'code']], ColumnMapping::guess(['Code'], $profile)->toArray());
    }

    public function testAHeaderFillsOneColumnOnlyAndTheFirstHeaderWins(): void
    {
        $profile = new ImportProfile('thing', [
            new Column('code', required: true, aliases: ['id']),
            new Column('other', aliases: ['id']),
        ], ['code']);

        self::assertSame([['header' => 'ID', 'column' => 'code']], ColumnMapping::guess(['ID'], $profile)->toArray());
    }

    public function testAColumnTakesOneHeaderOnly(): void
    {
        $profile = new ImportProfile('thing', [new Column('code', required: true, aliases: ['id'])], ['code']);

        self::assertSame([['header' => 'ID', 'column' => 'code']], ColumnMapping::guess(['ID', 'id'], $profile)->toArray());
    }

    public function testAKeptMappingWinsOverTheGuessAndTheRestIsGuessed(): void
    {
        $kept = new ColumnMapping([['header' => 'Mail pro', 'column' => 'email']]);
        $mapping = $kept->completedFor(['Email', 'Mail pro', 'First name'], LmsProfiles::people());

        self::assertSame([
            ['header' => 'Mail pro', 'column' => 'email'],
            ['header' => 'First name', 'column' => 'given_name'],
        ], $mapping->toArray());
    }

    public function testAKeptPairWhoseHeaderOrColumnIsGoneIsDropped(): void
    {
        $kept = new ColumnMapping([['header' => 'Gone', 'column' => 'email'], ['header' => 'Here', 'column' => 'nothing']]);

        self::assertSame([], $kept->completedFor(['Here'], LmsProfiles::people())->toArray());
    }

    public function testTwoKeptHeadersFoldedAlikeTakeOneHeaderOfTheFile(): void
    {
        $kept = new ColumnMapping([['header' => 'E-mail', 'column' => 'email'], ['header' => 'EMAIL', 'column' => 'given_name']]);

        self::assertSame([['header' => 'E-mail', 'column' => 'email']], $kept->completedFor(['email'], LmsProfiles::people())->toArray());
    }

    public function testAHeaderIsFoundAsWrittenBeforeFolded(): void
    {
        $mapping = new ColumnMapping([['header' => 'e-mail', 'column' => 'email']]);

        self::assertSame([1 => 'email'], $mapping->resolve(['E-MAIL ', 'e-mail']));
        self::assertSame([0 => 'email'], $mapping->resolve(['E-MAIL ', 'x']));
        self::assertSame([], $mapping->resolve(['x']));
    }

    public function testAHeaderOfPunctuationOnlyIsNeverFoundFolded(): void
    {
        $mapping = new ColumnMapping([['header' => '---', 'column' => 'email']]);

        self::assertSame([], $mapping->resolve(['***']));
        self::assertSame([0 => 'email'], $mapping->resolve(['---']));
    }

    public function testTwoPairsOnOneHeaderOfTheFileResolveToTheFirst(): void
    {
        $mapping = new ColumnMapping([['header' => 'Email', 'column' => 'email'], ['header' => 'EMAIL', 'column' => 'other']]);

        self::assertSame([0 => 'email'], $mapping->resolve(['email']));
    }

    public function testItResolvesInTheOrderOfTheHeaders(): void
    {
        $mapping = new ColumnMapping([['header' => 'B', 'column' => 'b'], ['header' => 'A', 'column' => 'a']]);

        self::assertSame([0 => 'a', 1 => 'b'], $mapping->resolve(['A', 'B']));
    }

    public function testItIsAdjustedHeaderByHeader(): void
    {
        $mapping = new ColumnMapping([['header' => 'A', 'column' => 'a'], ['header' => 'B', 'column' => 'b']]);

        self::assertSame([['header' => 'B', 'column' => 'b'], ['header' => 'C', 'column' => 'a']], $mapping->with('C', 'a')->toArray());
        self::assertSame([['header' => 'B', 'column' => 'b'], ['header' => 'A', 'column' => 'c']], $mapping->with('A', 'c')->toArray());
        self::assertSame([['header' => 'B', 'column' => 'b']], $mapping->without('A')->toArray());
        self::assertSame('B', $mapping->headerOf('b'));
        self::assertNull($mapping->headerOf('c'));
    }

    public function testItRefusesAHeaderOrAColumnMappedTwice(): void
    {
        try {
            new ColumnMapping([['header' => 'A', 'column' => 'a'], ['header' => 'A', 'column' => 'b']]);
            self::fail('A header mapped twice was accepted.');
        } catch (InvalidProfile $invalidProfile) {
            self::assertSame('The column "b" or its header is mapped twice.', $invalidProfile->getMessage());
        }

        $this->expectException(InvalidProfile::class);
        new ColumnMapping([['header' => 'A', 'column' => 'a'], ['header' => 'B', 'column' => 'a']]);
    }

    public function testItSerialisesAndReadsBack(): void
    {
        $mapping = new ColumnMapping([['header' => 'A', 'column' => 'a']]);

        self::assertEquals($mapping, ColumnMapping::fromArray($mapping->toArray()));
    }

    public function testItNamesTheFieldOfWrongData(): void
    {
        $this->expectExceptionMessageIsOrContains('The field "m[1].column" must be a text.');

        ColumnMapping::fromArray([['header' => 'A', 'column' => 'a'], ['header' => 'B']], 'm');
    }

    public function testItNamesTheDefaultPath(): void
    {
        $this->expectExceptionMessageIsOrContains('The field "mapping[0].header" must be a text.');

        ColumnMapping::fromArray([[]]);
    }

    public function testAHeaderIsFoldedWithItsCapitalAccents(): void
    {
        self::assertSame('ecole', HeaderName::fold('ÉCOLE'));
        self::assertSame('unite', HeaderName::fold('UNITÉ'));
    }

    public function testReplacingAPairKeepsTheOtherPairs(): void
    {
        $mapping = new ColumnMapping([['header' => 'A', 'column' => 'a'], ['header' => 'B', 'column' => 'b'], ['header' => 'C', 'column' => 'c']]);

        self::assertSame(
            [['header' => 'A', 'column' => 'a'], ['header' => 'C', 'column' => 'c'], ['header' => 'X', 'column' => 'b']],
            $mapping->with('X', 'b')->toArray(),
        );
    }
}
