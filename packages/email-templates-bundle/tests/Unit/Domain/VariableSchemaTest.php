<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Tests\Unit\Domain;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Phil\EmailTemplatesBundle\Domain\Exception\InvalidTemplate;
use Phil\EmailTemplatesBundle\Domain\Exception\InvalidVariables;
use Phil\EmailTemplatesBundle\Domain\Schema\Variable;
use Phil\EmailTemplatesBundle\Domain\Schema\VariableSchema;
use Phil\EmailTemplatesBundle\Domain\Schema\VariableType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Stringable;

#[CoversClass(VariableSchema::class)]
#[CoversClass(Variable::class)]
#[CoversClass(VariableType::class)]
#[CoversClass(InvalidVariables::class)]
final class VariableSchemaTest extends TestCase
{
    public function testEveryTypeNormalizesItsValues(): void
    {
        $schema = VariableSchema::fromArray([
            'name' => ['type' => 'string', 'max_length' => 5],
            'count' => ['type' => 'integer'],
            'price' => ['type' => 'number'],
            'flag' => ['type' => 'boolean'],
            'at' => ['type' => 'datetime'],
            'link' => ['type' => 'url'],
            'mail' => ['type' => 'email'],
            'tags' => ['type' => 'list', 'items' => ['type' => 'string'], 'max_items' => 3],
            'who' => ['type' => 'object', 'fields' => ['first' => ['type' => 'string']]],
        ]);
        $stringable = new class implements Stringable {
            public function __toString(): string
            {
                return 'Ada';
            }
        };

        $values = $schema->validate([
            'name' => $stringable,
            'count' => '-42',
            'price' => '12.50',
            'flag' => false,
            'at' => '2026-11-18T19:30:00-05:00',
            'link' => 'HTTPS://Example.com/a',
            'mail' => '  ada@example.com ',
            'tags' => ['a', 1, 2.5],
            'who' => ['first' => 'Grace'],
        ]);

        self::assertSame('Ada', $values['name']);
        self::assertSame(-42, $values['count']);
        self::assertSame(12.5, $values['price']);
        self::assertFalse($values['flag']);
        self::assertInstanceOf(DateTimeImmutable::class, $values['at']);
        self::assertSame('2026-11-18T19:30:00-05:00', $values['at']->format(DateTimeInterface::ATOM));
        self::assertSame('https://example.com/a', $values['link']);
        self::assertSame('ada@example.com', $values['mail']);
        self::assertSame(['a', '1', '2.5'], $values['tags']);
        self::assertSame(['first' => 'Grace'], $values['who']);
        self::assertSame(['name', 'count', 'price', 'flag', 'at', 'link', 'mail', 'tags', 'who'], $schema->names());
        self::assertTrue($schema->has('who'));
        self::assertFalse($schema->has('first'));
    }

    public function testNumbersAndDatesInTheirOtherForms(): void
    {
        $schema = VariableSchema::fromArray(['n' => ['type' => 'number'], 'i' => ['type' => 'integer'], 'd' => ['type' => 'datetime']]);

        self::assertSame(['n' => 3, 'i' => 4, 'd' => null], array_map(static fn (mixed $value): mixed => $value instanceof DateTimeInterface ? null : $value, $schema->validate(['n' => '3', 'i' => 4, 'd' => '2026-11-18'])));
        self::assertSame(2.25, $schema->validate(['n' => 2.25])['n']);
        self::assertSame('2026-11-18 00:00:00', $this->moment($schema->validate(['d' => '2026-11-18'])['d'])->format('Y-m-d H:i:s'));
        self::assertSame('2026-11-18 19:30:00', $this->moment($schema->validate(['d' => '2026-11-18T19:30'])['d'])->format('Y-m-d H:i:s'));
        self::assertSame('2026-11-18 19:30:15', $this->moment($schema->validate(['d' => '2026-11-18T19:30:15'])['d'])->format('Y-m-d H:i:s'));
        self::assertSame('2026-11-18 19:30:15.250000', $this->moment($schema->validate(['d' => '2026-11-18T19:30:15.25+00:00'])['d'])->format('Y-m-d H:i:s.u'));
        self::assertSame('+02:00', $this->moment($schema->validate(['d' => '2026-11-18T19:30+02:00'])['d'])->format('P'));
        $given = new DateTime('2026-01-01 10:00:00 UTC');
        self::assertInstanceOf(DateTimeImmutable::class, $schema->validate(['d' => $given])['d']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, mixed, non-empty-string}>
     */
    public static function refusedValues(): iterable
    {
        yield 'text too long' => [['type' => 'string', 'max_length' => 3], 'abcd', 'must be a text of at most 3 characters'];
        yield 'text not UTF-8' => [['type' => 'string'], "\xFF", 'must be a text of at most 10000 characters'];
        yield 'text as list' => [['type' => 'string'], ['a'], 'must be a text'];
        yield 'integer as float' => [['type' => 'integer'], 1.5, 'must be an integer'];
        yield 'integer as decimal text' => [['type' => 'integer'], '1.5', 'must be an integer'];
        yield 'integer too long' => [['type' => 'integer'], '1234567890123456789', 'must be an integer'];
        yield 'number as text' => [['type' => 'number'], 'one', 'must be a number'];
        yield 'number infinite' => [['type' => 'number'], \INF, 'must be a number'];
        yield 'number with exponent' => [['type' => 'number'], '1e5', 'must be a number'];
        yield 'boolean as integer' => [['type' => 'boolean'], 1, 'must be true or false'];
        yield 'date not ISO' => [['type' => 'datetime'], '18/11/2026', 'must be a date and time'];
        yield 'date impossible' => [['type' => 'datetime'], '2026-02-30', 'must be a date and time'];
        yield 'date as integer' => [['type' => 'datetime'], 1_700_000_000, 'must be a date and time'];
        yield 'date too long' => [['type' => 'datetime'], str_repeat('2', 41), 'must be a date and time'];
        yield 'url relative' => [['type' => 'url'], '/path', 'must be an absolute http or https address'];
        yield 'url of another scheme' => [['type' => 'url'], 'ftp://example.com/', 'must be an absolute http or https address'];
        yield 'url as integer' => [['type' => 'url'], 42, 'must be an absolute http or https address'];
        yield 'email without at' => [['type' => 'email'], 'ada', 'must be an email address'];
        yield 'email as integer' => [['type' => 'email'], 42, 'must be an email address'];
        yield 'list as map' => [['type' => 'list', 'items' => ['type' => 'string']], ['a' => 'b'], 'must be a list of at most 100 values'];
        yield 'list too long' => [['type' => 'list', 'items' => ['type' => 'string'], 'max_items' => 1], ['a', 'b'], 'must be a list of at most 1 values'];
        yield 'object as list' => [['type' => 'object', 'fields' => ['a' => ['type' => 'string']]], ['x'], 'must be a map of its fields'];
        yield 'object as text' => [['type' => 'object', 'fields' => ['a' => ['type' => 'string']]], 'x', 'must be a map of its fields'];
    }

    /**
     * @param array<string, mixed> $declaration
     * @param non-empty-string     $reason
     */
    #[DataProvider('refusedValues')]
    public function testARefusedValueNamesItsPathAndNotItsValue(array $declaration, mixed $value, string $reason): void
    {
        try {
            VariableSchema::fromArray(['v' => $declaration])->validate(['v' => $value]);
            self::fail('The value is refused.');
        } catch (InvalidVariables $invalidVariables) {
            self::assertStringStartsWith($reason, $invalidVariables->violations['v']);
            self::assertSame(['v'], array_keys($invalidVariables->violations));
            self::assertStringStartsWith('The variables do not match the schema: "v" '.$reason, $invalidVariables->getMessage());
        }
    }

    public function testNestedViolationsAreReportedAtTheirPaths(): void
    {
        $schema = VariableSchema::fromArray([
            'order' => ['type' => 'object', 'required' => true, 'fields' => [
                'number' => ['type' => 'string', 'required' => true],
                'lines' => ['type' => 'list', 'items' => ['type' => 'object', 'fields' => ['qty' => ['type' => 'integer', 'required' => true]]]],
            ]],
        ]);

        try {
            $schema->validate(['order' => ['lines' => [['qty' => 1], ['qty' => 'many'], null, []], 'extra' => 1], 'stranger' => 'x', 3 => 'y']);
            self::fail('The values are refused.');
        } catch (InvalidVariables $invalidVariables) {
            self::assertSame([
                'stranger' => 'is not declared in the schema',
                '3' => 'is not declared in the schema',
                'order.extra' => 'is not declared in the schema',
                'order.number' => 'is required',
                'order.lines.1.qty' => 'must be an integer',
                'order.lines.2' => 'must be a map of its fields',
                'order.lines.3.qty' => 'is required',
            ], $invalidVariables->violations);
        }
    }

    public function testMissingValuesTakeTheirDefaultOrNull(): void
    {
        $schema = VariableSchema::fromArray([
            'seats' => ['type' => 'integer', 'default' => 2],
            'note' => ['type' => 'string'],
            'name' => ['type' => 'string', 'required' => true, 'default' => 'friend'],
            'empty' => ['type' => 'object', 'fields' => ['a' => ['type' => 'string']]],
        ]);

        self::assertSame(['seats' => 2, 'note' => null, 'name' => 'friend', 'empty' => ['a' => null]], $schema->validate(['note' => null, 'empty' => []]));
        self::assertSame(5, $schema->validate(['seats' => 5])['seats']);
    }

    public function testARequiredVariableWithoutDefault(): void
    {
        $this->expectException(InvalidVariables::class);
        $this->expectExceptionMessageIsOrContains('"name" is required');

        VariableSchema::fromArray(['name' => ['type' => 'string', 'required' => true]])->validate([]);
    }

    public function testSamplesComeFromTheSampleTheDefaultOrTheType(): void
    {
        $schema = VariableSchema::fromArray([
            'first_name' => ['type' => 'string', 'max_length' => 4],
            'given' => ['type' => 'string', 'sample' => 'Grace', 'default' => 'Ada'],
            'fallback' => ['type' => 'string', 'default' => 'Ada'],
            'count' => ['type' => 'integer'],
            'price' => ['type' => 'number'],
            'flag' => ['type' => 'boolean'],
            'at' => ['type' => 'datetime'],
            'link' => ['type' => 'url'],
            'mail' => ['type' => 'email'],
            'tags' => ['type' => 'list', 'items' => ['type' => 'integer']],
            'who' => ['type' => 'object', 'fields' => ['age' => ['type' => 'integer', 'sample' => 30]]],
        ]);
        $samples = $schema->samples();

        self::assertSame('Firs', $samples['first_name']);
        self::assertSame('Grace', $samples['given']);
        self::assertSame('Ada', $samples['fallback']);
        self::assertSame(1, $samples['count']);
        self::assertSame(1.5, $samples['price']);
        self::assertTrue($samples['flag']);
        self::assertSame(Variable::SAMPLE_MOMENT, $this->moment($samples['at'])->format(DateTimeInterface::ATOM));
        self::assertSame('https://example.com/', $samples['link']);
        self::assertSame('person@example.com', $samples['mail']);
        self::assertSame([1], $samples['tags']);
        self::assertSame(['age' => 30], $samples['who']);
        self::assertEquals($schema->validate($samples), $samples);
    }

    public function testTheDeclarationReadsBack(): void
    {
        $declaration = [
            'name' => ['type' => 'string', 'required' => true, 'default' => 'Ada', 'sample' => 'Grace', 'description' => 'Who', 'max_length' => 50],
            'tags' => ['type' => 'list', 'items' => ['type' => 'string'], 'max_items' => 5],
            'who' => ['type' => 'object', 'fields' => ['a' => ['type' => 'integer']]],
            'flag' => ['type' => 'boolean', 'default' => false],
        ];
        $schema = VariableSchema::fromArray($declaration);

        self::assertSame($declaration, $schema->toArray());
        self::assertEquals($schema, VariableSchema::fromArray($schema->toArray()));
        self::assertSame([], VariableSchema::empty()->toArray());
        self::assertSame(['type' => 'list', 'items' => ['type' => 'string']], VariableSchema::fromArray(['l' => ['type' => 'list', 'items' => ['type' => 'string'], 'max_items' => 100]])->toArray()['l']);
        self::assertSame(['type' => 'string'], VariableSchema::fromArray(['s' => ['type' => 'string', 'max_length' => 10_000, 'required' => false]])->toArray()['s']);
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function refusedDeclarations(): iterable
    {
        yield 'bad name' => [['Name' => ['type' => 'string']], '"Name" is not a variable name'];
        yield 'integer name' => [[0 => ['type' => 'string']], '"0" is not a variable name'];
        yield 'reserved name' => [['email' => ['type' => 'string']], '"email" is a name the template keeps for itself.'];
        yield 'loop' => [['loop' => ['type' => 'string']], '"loop" is a name the template keeps for itself.'];
        yield 'not a map' => [['a' => 'string'], '"a" must be declared as a map'];
        yield 'unknown key' => [['a' => ['type' => 'string', 'color' => 'red']], '"a" has an unknown key "color"'];
        yield 'no type' => [['a' => []], '"a" has no type'];
        yield 'unknown type' => [['a' => ['type' => 'money']], '"a" has no type, or one that does not exist'];
        yield 'required not boolean' => [['a' => ['type' => 'string', 'required' => 'yes']], '"a": "required" is true or false.'];
        yield 'description too long' => [['a' => ['type' => 'string', 'description' => str_repeat('x', 501)]], '"a": "description" is a text of at most 500 characters.'];
        yield 'description not text' => [['a' => ['type' => 'string', 'description' => 5]], '"a": "description" is a text'];
        yield 'max items on text' => [['a' => ['type' => 'string', 'max_items' => 3]], '"a": "max_items" is given to a list only'];
        yield 'max items zero' => [['a' => ['type' => 'list', 'items' => ['type' => 'string'], 'max_items' => 0]], '"a": "max_items" is given to a list only, from 1 to 1000.'];
        yield 'max items too high' => [['a' => ['type' => 'list', 'items' => ['type' => 'string'], 'max_items' => 1001]], '"a": "max_items"'];
        yield 'max length on integer' => [['a' => ['type' => 'integer', 'max_length' => 3]], '"a": "max_length" is given to a string only'];
        yield 'max length zero' => [['a' => ['type' => 'string', 'max_length' => 0]], '"a": "max_length" is given to a string only, from 1 to 100000.'];
        yield 'max length too high' => [['a' => ['type' => 'string', 'max_length' => 100_001]], '"a": "max_length"'];
        yield 'object without fields' => [['a' => ['type' => 'object']], '"a" is an object: it declares its "fields".'];
        yield 'object with empty fields' => [['a' => ['type' => 'object', 'fields' => []]], '"a" is an object: it declares its "fields".'];
        yield 'fields on text' => [['a' => ['type' => 'string', 'fields' => []]], '"a": "fields" is given to an object only.'];
        yield 'list without items' => [['a' => ['type' => 'list']], '"a" is a list: it declares its "items".'];
        yield 'items on text' => [['a' => ['type' => 'string', 'items' => []]], '"a": "items" is given to a list only.'];
        yield 'bad nested field' => [['a' => ['type' => 'object', 'fields' => ['B' => ['type' => 'string']]]], '"a.B" is not a variable name'];
        yield 'bad items' => [['a' => ['type' => 'list', 'items' => ['type' => 'nope']]], '"a.items" has no type'];
        yield 'too deep' => [['a' => ['type' => 'object', 'fields' => ['b' => ['type' => 'object', 'fields' => ['c' => ['type' => 'list', 'items' => ['type' => 'list', 'items' => ['type' => 'string']]]]]]]], '"a.b.c.items" is a list nested more than 3 levels deep.'];
        yield 'default of wrong type' => [['a' => ['type' => 'integer', 'default' => 'x']], 'The default of "a" does not match its type: "a" must be an integer.'];
        yield 'sample of wrong type' => [['a' => ['type' => 'url', 'sample' => 'nope']], 'The sample of "a" does not match its type: "a" must be an absolute http or https address.'];
        yield 'default an object' => [['a' => ['type' => 'string', 'default' => new stdClass()]], 'The default of "a" is a text, a number, a boolean, a list or a map.'];
        yield 'nested sample of wrong type' => [['a' => ['type' => 'list', 'items' => ['type' => 'integer'], 'sample' => [1, 'x']]], 'The sample of "a" does not match its type: "a.1" must be an integer.'];
    }

    /**
     * @param array<array-key, mixed> $declaration
     */
    #[DataProvider('refusedDeclarations')]
    public function testARefusedDeclaration(array $declaration, string $error): void
    {
        try {
            VariableSchema::fromArray($declaration);
            self::fail('The declaration is refused.');
        } catch (InvalidTemplate $invalidTemplate) {
            self::assertNotSame([], array_filter($invalidTemplate->errors, static fn (string $message): bool => str_starts_with($message, $error)), implode("\n", $invalidTemplate->errors));
        }
    }

    public function testEveryReasonIsListedOnce(): void
    {
        try {
            VariableSchema::fromArray(['A' => ['type' => 'string'], 'b' => ['type' => 'nope'], 'c' => 'x']);
            self::fail('The declaration is refused.');
        } catch (InvalidTemplate $invalidTemplate) {
            self::assertCount(3, $invalidTemplate->errors);
        }
    }

    public function testASchemaHoldsAtMostAHundredVariables(): void
    {
        $spec = [];

        for ($i = 0; $i < 100; ++$i) {
            $spec['v'.$i] = ['type' => 'string'];
        }

        self::assertCount(100, VariableSchema::fromArray($spec)->variables);
        $spec['v100'] = ['type' => 'string'];
        $this->expectExceptionMessageIsOrContains('A schema declares at most 100 variables, counted at every depth; this one declares 101.');

        VariableSchema::fromArray($spec);
    }

    public function testNestedVariablesAndItemsCount(): void
    {
        $fields = [];

        for ($i = 0; $i < 98; ++$i) {
            $fields['f'.$i] = ['type' => 'string'];
        }

        self::assertCount(1, VariableSchema::fromArray(['o' => ['type' => 'object', 'fields' => $fields]])->variables);

        $this->expectExceptionMessageIsOrContains('this one declares 101');
        VariableSchema::fromArray(['o' => ['type' => 'object', 'fields' => $fields], 'l' => ['type' => 'list', 'items' => ['type' => 'string']]]);
    }

    public function testTheTypes(): void
    {
        self::assertTrue(VariableType::List->isComposite());
        self::assertTrue(VariableType::Object->isComposite());
        self::assertFalse(VariableType::String->isComposite());
        self::assertSame('must be an integer', new Variable('a', VariableType::Integer)->expectation());
    }

    private function moment(mixed $value): DateTimeImmutable
    {
        self::assertInstanceOf(DateTimeImmutable::class, $value);

        return $value;
    }
}
