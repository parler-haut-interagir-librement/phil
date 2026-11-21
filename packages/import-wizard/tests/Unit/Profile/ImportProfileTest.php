<?php

declare(strict_types=1);

namespace Phil\ImportWizard\Tests\Unit\Profile;

use JsonException;
use Phil\ImportWizard\Exception\InvalidProfile;
use Phil\ImportWizard\Lms\LmsProfiles;
use Phil\ImportWizard\Profile\Column;
use Phil\ImportWizard\Profile\ColumnMapping;
use Phil\ImportWizard\Profile\ImportProfile;
use Phil\ImportWizard\Profile\ProfileData;
use Phil\ImportWizard\Profile\Reference;
use Phil\ImportWizard\Tests\Support\SampleRule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ImportProfile::class)]
#[CoversClass(ProfileData::class)]
final class ImportProfileTest extends TestCase
{
    public function testItFindsAColumnByName(): void
    {
        $profile = LmsProfiles::people();

        self::assertSame('email', $profile->column('email')?->name);
        self::assertNull($profile->column('nothing'));
    }

    public function testItKeepsAMappingForTheNextFile(): void
    {
        $profile = LmsProfiles::people();
        $mapping = new ColumnMapping([['header' => 'Mail', 'column' => 'email']]);
        $kept = $profile->withMapping($mapping);

        self::assertNull($profile->mapping);
        self::assertSame($mapping, $kept->mapping);
        self::assertSame($profile->columns, $kept->columns);
        self::assertSame($profile->naturalKey, $kept->naturalKey);
        self::assertSame($profile->rules, LmsProfiles::people()->rules);
        self::assertEquals(LmsProfiles::enrolments()->rules, LmsProfiles::enrolments()->withMapping(new ColumnMapping())->rules);
        self::assertSame('person', $kept->type);
    }

    public function testItSerialisesToJsonAndReadsBack(): void
    {
        $profile = LmsProfiles::enrolments()->withMapping(new ColumnMapping([['header' => 'Courriel', 'column' => 'person_email']]));
        $json = $profile->toJson();

        self::assertStringContainsString('"rule": "date-order"', $json);
        self::assertStringContainsString('"header": "Courriel"', $json);
        self::assertStringContainsString('"aliases": [', $json);
        self::assertStringContainsString('"learner email"', $json);
        self::assertStringContainsString('"date de début"', $json);
        self::assertStringNotContainsString('\/', $json);
        self::assertEquals($profile, ImportProfile::fromJson($json));
        self::assertSame(1, $profile->toArray()['format']);
    }

    public function testAProfileWithoutMappingReadsBackWithoutOne(): void
    {
        $profile = ImportProfile::fromArray(LmsProfiles::organisationalUnits()->toArray());

        self::assertNull($profile->mapping);
        self::assertEquals(LmsProfiles::organisationalUnits(), $profile);
    }

    public function testItReadsBackTheRulesAnApplicationAdds(): void
    {
        $profile = new ImportProfile('thing', [new Column('code', required: true)], ['code'], [new SampleRule('x')]);

        self::assertEquals($profile, ImportProfile::fromArray($profile->toArray(), [SampleRule::class]));

        $this->expectException(InvalidProfile::class);
        $this->expectExceptionMessageIsOrContains('The rule of "profile.rules[0]" is not known.');

        ImportProfile::fromArray($profile->toArray());
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function wrongData(): iterable
    {
        $valid = LmsProfiles::organisationalUnits()->toArray();

        yield 'no format' => [['type' => 'x'], 'The field "profile.format" must be 1.'];
        yield 'another format' => [['format' => 2] + $valid, 'The field "profile.format" must be 1.'];
        yield 'format as text' => [['format' => '1'] + $valid, 'The field "profile.format" must be 1.'];
        yield 'no columns' => [['columns' => []] + $valid, 'The fields "profile.columns" and "profile.naturalKey" must not be empty.'];
        yield 'no natural key' => [['naturalKey' => []] + $valid, 'The fields "profile.columns" and "profile.naturalKey" must not be empty.'];
        yield 'a column not an object' => [['columns' => ['code']] + $valid, 'The field "profile.columns[0]" must be an object.'];
        yield 'a wrong column' => [['columns' => [['name' => 'a']]] + $valid, 'The field "profile.columns[0].type" must be a text.'];
        yield 'a rule without name' => [['rules' => [[]]] + $valid, 'The field "profile.rules[0].rule" must be a text.'];
        yield 'type missing' => [['type' => null] + $valid, 'The field "profile.type" must be a text.'];
        yield 'mapping not a list' => [['mapping' => 'x'] + $valid, 'The field "profile.mapping" must be a list.'];
        yield 'a wrong pair' => [['mapping' => [['header' => 'a']]] + $valid, 'The field "mapping[0].column" must be a text.'];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    #[DataProvider('wrongData')]
    public function testItRefusesDataOfTheWrongShape(array $data, string $message): void
    {
        $this->expectException(InvalidProfile::class);
        $this->expectExceptionMessageIsOrContains($message);

        ImportProfile::fromArray($data);
    }

    public function testItRefusesJsonThatIsNotAProfile(): void
    {
        foreach (['{', '"text"', '[[[[[[[[[[[[[[[[[[[1]]]]]]]]]]]]]]]]]]'] as $json) {
            try {
                ImportProfile::fromJson($json);
                self::fail('JSON that is not a profile was read.');
            } catch (InvalidProfile $exception) {
                self::assertContains($exception->getMessage(), ['The profile is not valid JSON.', 'The profile must be a JSON object.']);
            }
        }

        $this->expectExceptionMessageIsOrContains('A profile is at most 1 MiB of JSON.');
        ImportProfile::fromJson(str_repeat(' ', 1_048_577));
    }

    public function testJsonOfExactly1MiBIsRead(): void
    {
        $json = LmsProfiles::people()->toJson();

        self::assertEquals(LmsProfiles::people(), ImportProfile::fromJson(str_pad($json, 1_048_576)));
    }

    public function testItNamesWhatJsonIsRefusedFor(): void
    {
        try {
            ImportProfile::fromJson('"text"');
            self::fail('A JSON text was read as a profile.');
        } catch (InvalidProfile $invalidProfile) {
            self::assertSame('The profile must be a JSON object.', $invalidProfile->getMessage());
        }

        $this->expectExceptionMessageIsOrContains('The profile is not valid JSON.');
        ImportProfile::fromJson('{');
    }

    public function testItReadsJsonOfSixteenLevels(): void
    {
        $this->expectExceptionMessageIsOrContains('The field "profile.format" must be 1.');

        ImportProfile::fromJson('{"x": '.str_repeat('[', 14).'1'.str_repeat(']', 14).'}');
    }

    public function testItRefusesJsonOfSeventeenLevels(): void
    {
        $this->expectExceptionMessageIsOrContains('The profile is not valid JSON.');

        ImportProfile::fromJson('{"format": 1, "rules": [{"a": [[[[[[[[[[[[[[1]]]]]]]]]]]]]]}]}');
    }

    /**
     * @return iterable<string, array{callable(): ImportProfile, string}>
     */
    public static function wrongProfiles(): iterable
    {
        $code = new Column('code', required: true);

        yield 'type out of its pattern' => [static fn (): ImportProfile => new ImportProfile('Thing', [$code], ['code']), 'The type "Thing" must be lower-case letters, digits and hyphens.'];
        yield 'type with a line break' => [static fn (): ImportProfile => new ImportProfile("thing\n", [$code], ['code']), 'must be lower-case letters'];
        yield 'type too long' => [static fn (): ImportProfile => new ImportProfile('a'.str_repeat('b', 64), [$code], ['code']), 'must be lower-case letters'];
        yield 'column twice' => [static fn (): ImportProfile => new ImportProfile('thing', [$code, $code], ['code']), 'The column "code" is declared twice.'];
        yield 'key twice' => [static fn (): ImportProfile => new ImportProfile('thing', [$code], ['code', 'code']), 'The natural key of "thing" names a column twice.'];
        yield 'key optional' => [static fn (): ImportProfile => new ImportProfile('thing', [new Column('code')], ['code']), 'The natural key names "code", which must be a required column.'];
        yield 'key unknown' => [static fn (): ImportProfile => new ImportProfile('thing', [$code], ['name']), 'The natural key names "name", which must be a required column.'];
        yield 'self reference with a key of two columns' => [static fn (): ImportProfile => new ImportProfile('thing', [$code, new Column('name', required: true), new Column('parent', reference: new Reference('thing'))], ['code', 'name']), 'The column "parent" refers to "thing", whose natural key must be one column.'];
        yield 'mapping of an unknown column' => [static fn (): ImportProfile => new ImportProfile('thing', [$code], ['code'], mapping: new ColumnMapping([['header' => 'X', 'column' => 'x']])), 'The mapping names the column "x", which the profile does not have.'];
        yield 'too many columns' => [static fn (): ImportProfile => new ImportProfile('thing', array_map(static fn (int $i): Column => new Column('c'.$i, required: true), range(0, 256)), ['c0']), 'The profile "thing" has more than 256 columns.'];
    }

    /**
     * @param callable(): ImportProfile $build
     */
    #[DataProvider('wrongProfiles')]
    public function testItRefusesAProfileThatCannotBeUsed(callable $build, string $message): void
    {
        $this->expectException(InvalidProfile::class);
        $this->expectExceptionMessageIsOrContains($message);

        $build();
    }

    public function testItAcceptsTwoHundredFiftySixColumnsAndAReferenceToAnotherTypeWithAKeyOfTwoColumns(): void
    {
        $columns = array_map(static fn (int $i): Column => new Column('c'.$i, required: true), range(0, 255));

        self::assertCount(256, new ImportProfile('thing', $columns, ['c0'])->columns);
        self::assertSame(['person_email', 'course_code'], LmsProfiles::enrolments()->naturalKey);
        self::assertSame('a'.str_repeat('b', 63), new ImportProfile('a'.str_repeat('b', 63), [$columns[0]], ['c0'])->type);
    }

    public function testAProfileNestedSixteenLevelsDeepIsNotReadAsJson(): void
    {
        try {
            ImportProfile::fromJson(str_repeat('[', 16).str_repeat(']', 16));
            self::fail('A profile nested sixteen levels deep was read.');
        } catch (InvalidProfile $invalidProfile) {
            self::assertSame('The profile is not valid JSON.', $invalidProfile->getMessage());
        }
    }

    public function testItRefusesToWriteAProfileWhoseTextIsNotUtf8(): void
    {
        $profile = new ImportProfile('thing', [new Column('code', required: true, aliases: ["\xff"])], ['code']);

        $this->expectException(JsonException::class);

        $profile->toJson();
    }
}
