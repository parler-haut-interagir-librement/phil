<?php

declare(strict_types=1);

namespace Phil\ImportWizard\Tests\Unit\Profile;

use ErrorException;
use Phil\ImportWizard\Exception\InvalidProfile;
use Phil\ImportWizard\Plan\Reason;
use Phil\ImportWizard\Profile\Column;
use Phil\ImportWizard\Profile\ColumnType;
use Phil\ImportWizard\Profile\ProfileData;
use Phil\ImportWizard\Profile\Reference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Column::class)]
#[CoversClass(Reference::class)]
#[CoversClass(ProfileData::class)]
final class ColumnTest extends TestCase
{
    public function testItDefaultsToAnOptionalTextColumnHeadedByItsName(): void
    {
        $column = new Column('given_name');

        self::assertSame(ColumnType::Text, $column->type);
        self::assertFalse($column->required);
        self::assertSame('given_name', $column->header);
        self::assertSame([], $column->aliases);
        self::assertNull($column->maxLength);
        self::assertSame([], $column->choices);
        self::assertNull($column->reference);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function wrongNames(): iterable
    {
        yield 'upper case' => ['Email'];
        yield 'leading digit' => ['1email'];
        yield 'hyphen' => ['e-mail'];
        yield 'empty' => [''];
        yield 'too long' => ['a'.str_repeat('b', 64)];
        yield 'line break after' => ["email\n"];
        yield 'text before' => ['-email'];
    }

    #[DataProvider('wrongNames')]
    public function testItRefusesANameOutOfItsPattern(string $name): void
    {
        $this->expectException(InvalidProfile::class);
        $this->expectExceptionMessageIsOrContains('must be lower-case letters, digits and underscores');

        new Column($name);
    }

    public function testANameOf64CharactersIsAccepted(): void
    {
        $name = 'a'.str_repeat('b', 63);

        self::assertSame($name, new Column($name)->name);
    }

    public function testItRefusesABlankHeader(): void
    {
        $this->expectException(InvalidProfile::class);
        $this->expectExceptionMessageIsOrContains('The column "code" needs a header, at most 32 aliases and 256 choices.');

        new Column('code', header: '  ');
    }

    public function testItAcceptsThirtyTwoAliasesAndNotMore(): void
    {
        self::assertCount(32, new Column('code', aliases: array_fill(0, 32, 'x'))->aliases);

        $this->expectException(InvalidProfile::class);
        new Column('code', aliases: array_fill(0, 33, 'x'));
    }

    public function testItAcceptsTwoHundredFiftySixChoicesAndNotMore(): void
    {
        self::assertCount(256, new Column('code', choices: array_fill(0, 256, 'x'))->choices);

        $this->expectException(InvalidProfile::class);
        new Column('code', choices: array_fill(0, 257, 'x'));
    }

    public function testItRefusesALengthBelowOne(): void
    {
        self::assertSame(1, new Column('code', maxLength: 1)->maxLength);

        $this->expectException(InvalidProfile::class);
        $this->expectExceptionMessageIsOrContains('The maximum length of the column "code" must be positive.');

        new Column('code', maxLength: 0);
    }

    public function testItRefusesChoicesOnAColumnThatIsNotText(): void
    {
        $this->expectException(InvalidProfile::class);
        $this->expectExceptionMessageIsOrContains('The column "level" has choices: it must be a text column.');

        new Column('level', ColumnType::Integer, choices: ['1']);
    }

    public function testItRefusesAReferenceToATypeOutOfItsPattern(): void
    {
        $this->expectException(InvalidProfile::class);
        $this->expectExceptionMessageIsOrContains('The type "Unit" must be lower-case letters, digits and hyphens.');

        new Reference('Unit');
    }

    public function testItReadsACellWithinItsLength(): void
    {
        $column = new Column('code', maxLength: 3);

        self::assertSame('été', $column->parse('été'));
        self::assertSame(Reason::TooLong, $column->parse('étés'));
    }

    public function testItReadsAChoiceInAnyCaseAsDeclared(): void
    {
        $column = new Column('role', choices: ['Learner', 'instructor']);

        self::assertSame('Learner', $column->parse('LEARNER'));
        self::assertSame('instructor', $column->parse('Instructor'));
        self::assertSame(Reason::NotAllowed, $column->parse('admin'));
    }

    public function testItGivesTheReasonOfTheTypeBeforeTheChoices(): void
    {
        self::assertSame(Reason::NotAnInteger, new Column('level', ColumnType::Integer)->parse('x'));
        self::assertSame(12, new Column('level', ColumnType::Integer)->parse('12'));
    }

    public function testItSerialisesAndReadsBack(): void
    {
        $column = new Column('unit_code', ColumnType::Text, true, 'Unit', ['unité'], 64, ['a', 'b'], new Reference('organisational-unit'));
        $data = $column->toArray();

        self::assertSame([
            'name' => 'unit_code',
            'type' => 'text',
            'required' => true,
            'header' => 'Unit',
            'aliases' => ['unité'],
            'maxLength' => 64,
            'choices' => ['a', 'b'],
            'reference' => 'organisational-unit',
        ], $data);
        self::assertEquals($column, Column::fromArray($data));
    }

    public function testItReadsBackAColumnWithoutReferenceNorHeader(): void
    {
        $column = Column::fromArray(['name' => 'code', 'type' => 'integer', 'required' => false]);

        self::assertEquals(new Column('code', ColumnType::Integer), $column);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function wrongData(): iterable
    {
        yield 'unknown type' => [['name' => 'a', 'type' => 'uuid', 'required' => true], 'The field "c.type" must be one of the column types.'];
        yield 'type not a text' => [['name' => 'a', 'type' => 1, 'required' => true], 'The field "c.type" must be a text.'];
        yield 'name missing' => [['type' => 'text', 'required' => true], 'The field "c.name" must be a text.'];
        yield 'required not a boolean' => [['name' => 'a', 'type' => 'text', 'required' => 'yes'], 'The field "c.required" must be true or false.'];
        yield 'header not a text' => [['name' => 'a', 'type' => 'text', 'required' => true, 'header' => 3], 'The field "c.header" must be a text.'];
        yield 'length not a number' => [['name' => 'a', 'type' => 'text', 'required' => true, 'maxLength' => '3'], 'The field "c.maxLength" must be a whole number.'];
        yield 'aliases not a list' => [['name' => 'a', 'type' => 'text', 'required' => true, 'aliases' => ['x' => 'y']], 'The field "c.aliases" must be a list.'];
        yield 'aliases a text' => [['name' => 'a', 'type' => 'text', 'required' => true, 'aliases' => 'y'], 'The field "c.aliases" must be a list.'];
        yield 'alias not a text' => [['name' => 'a', 'type' => 'text', 'required' => true, 'aliases' => ['x', 2]], 'The field "c.aliases[1]" must be a text.'];
        yield 'reference not a text' => [['name' => 'a', 'type' => 'text', 'required' => true, 'reference' => []], 'The field "c.reference" must be a text.'];
    }

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('wrongData')]
    public function testItRefusesDataOfTheWrongShape(array $data, string $message): void
    {
        $this->expectException(InvalidProfile::class);
        $this->expectExceptionMessageIsOrContains($message);

        Column::fromArray($data, 'c');
    }

    public function testItNamesTheDefaultPath(): void
    {
        $this->expectExceptionMessageIsOrContains('The field "column.type" must be a text.');

        Column::fromArray([]);
    }

    public function testChoicesAreComparedWithoutCaseAmongCapitalAccents(): void
    {
        $column = new Column('level', choices: ['Élève', 'Maître']);

        self::assertSame('Élève', $column->parse('ÉLÈVE'));
        self::assertSame('Élève', $column->parse('élève'));
        self::assertSame('Maître', $column->parse('MAÎTRE'));
    }

    public function testAColumnWithoutReferenceSerialisesWithoutOneAndWithoutAWarning(): void
    {
        set_error_handler(static function (int $level, string $message): never {
            throw new ErrorException($message, 0, $level);
        });

        try {
            self::assertNull(new Column('code')->toArray()['reference']);
        } finally {
            restore_error_handler();
        }
    }
}
