<?php

declare(strict_types=1);

namespace Phil\FormLogic\Tests\Unit\JsonLogic;

use DateTimeImmutable;
use Phil\FormLogic\Exception\InvalidLogic;
use Phil\FormLogic\Expression\AllOf;
use Phil\FormLogic\Expression\AnyOf;
use Phil\FormLogic\Expression\Arithmetic;
use Phil\FormLogic\Expression\ArithmeticOperator;
use Phil\FormLogic\Expression\Between;
use Phil\FormLogic\Expression\Comparison;
use Phil\FormLogic\Expression\ComparisonOperator;
use Phil\FormLogic\Expression\Conditional;
use Phil\FormLogic\Expression\In;
use Phil\FormLogic\Expression\ListOf;
use Phil\FormLogic\Expression\Literal;
use Phil\FormLogic\Expression\Missing;
use Phil\FormLogic\Expression\Not;
use Phil\FormLogic\Expression\Variable;
use Phil\FormLogic\JsonLogic\JsonLogicReader;
use Phil\FormLogic\JsonLogic\JsonLogicWriter;
use Phil\FormLogic\JsonLogic\Limits;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonLogicReader::class)]
#[CoversClass(JsonLogicWriter::class)]
#[CoversClass(Limits::class)]
final class JsonLogicReaderTest extends TestCase
{
    public function testEachOperatorIsReadIntoItsNode(): void
    {
        $reader = new JsonLogicReader();

        self::assertEquals(new Variable('a'), $reader->read('{"var": "a"}'));
        self::assertEquals(new Variable('a'), $reader->read('{"var": ["a"]}'));
        self::assertEquals(new Variable('a', new Literal(1)), $reader->read('{"var": ["a", 1]}'));
        self::assertEquals(new Missing(['a']), $reader->read('{"missing": "a"}'));
        self::assertEquals(new Missing(['a', 'b'], 1), $reader->read('{"missing_some": [1, ["a", "b"]]}'));
        self::assertEquals(new Missing(['a'], 0), $reader->read('{"missing_some": [0, "a"]}'));
        self::assertEquals(new Comparison(ComparisonOperator::GreaterOrEqual, new Variable('a'), new Literal(1)), $reader->read('{">=": [{"var": "a"}, 1]}'));
        self::assertEquals(new Comparison(ComparisonOperator::Less, new Literal(1), new Literal(2)), $reader->read('{"<": [1, 2]}'));
        self::assertEquals(new Between(new Literal(1), new Literal(2), new Literal(3)), $reader->read('{"<": [1, 2, 3]}'));
        self::assertEquals(new Between(new Literal(1), new Literal(2), new Literal(3), true), $reader->read('{"<=": [1, 2, 3]}'));
        self::assertEquals(new Not(new Literal(true)), $reader->read('{"!": true}'));
        self::assertEquals(new Not(new Literal(true), true), $reader->read('{"!!": [true]}'));
        self::assertEquals(new AllOf([new Literal(true)]), $reader->read('{"and": [true]}'));
        self::assertEquals(new AnyOf([new Literal(true), new Literal(false)]), $reader->read('{"or": [true, false]}'));
        self::assertEquals(new Conditional([[new Literal(true), new Literal(1)]]), $reader->read('{"if": [true, 1]}'));
        self::assertEquals(new Conditional([[new Literal(true), new Literal(1)], [new Literal(false), new Literal(2)]], new Literal(3)), $reader->read('{"if": [true, 1, false, 2, 3]}'));
        self::assertEquals(new In(new Literal('a'), new ListOf([new Literal('a')])), $reader->read('{"in": ["a", ["a"]]}'));
        self::assertEquals(new Arithmetic(ArithmeticOperator::Min, [new Literal(1)]), $reader->read('{"min": 1}'));
        self::assertEquals(new ListOf([]), $reader->read('[]'));
    }

    public function testADecodedRuleIsReadFromMapsOrObjects(): void
    {
        $reader = new JsonLogicReader();
        $expected = new Comparison(ComparisonOperator::Equal, new Variable('team'), new Literal('Blue'));

        self::assertEquals($expected, $reader->parse(['==' => [['var' => 'team'], 'Blue']]));
        self::assertEquals($expected, $reader->parse(json_decode('{"==": [{"var": "team"}, "Blue"]}', false, 512, \JSON_THROW_ON_ERROR)));
    }

    public function testAnObjectWithANumericKeyIsAnUnknownOperationRatherThanAList(): void
    {
        $this->expectExceptionObject(InvalidLogic::because('An object of a rule is one operation of the subset: '.implode(', ', JsonLogicReader::OPERATORS).'; "0" is not read.'));

        new JsonLogicReader()->read('{"0": 1}');
    }

    public function testAnEmptyObjectIsNoOperation(): void
    {
        $this->expectException(InvalidLogic::class);
        $this->expectExceptionMessageIsOrContains('"" is not read.');

        new JsonLogicReader()->read('{}');
    }

    public function testTheCanonicalFormIsWrittenAndReadBack(): void
    {
        $reader = new JsonLogicReader();
        $writer = new JsonLogicWriter();
        $json = '{"if":[{"and":[{"<=":[0,{"var":["age",0]},17]},{"!":[{"missing":["guardian"]}]}]},"minor/é",{"or":[{"in":["x",{"var":"tags"}]},{"missing_some":[1,["a","b"]]},{"!!":[{"-":[{"var":"a"}]}]}]}]}';

        self::assertSame($json, $writer->write($reader->read($json)));
        self::assertSame('{"var":"a"}', $writer->write($reader->read('{"var": ["a"]}')));
        self::assertSame('{"!":[true]}', $writer->write($reader->read('{"!": true}')));
    }

    public function testTheJsonIsLimitedInBytes(): void
    {
        $reader = new JsonLogicReader(new Limits(maxBytes: 5));

        self::assertEquals(new Literal('abc'), $reader->read('"abc"'));

        $this->expectExceptionObject(InvalidLogic::because('A rule is at most 5 bytes of JSON.'));
        $reader->read('"abcd"');
    }

    public function testInvalidJsonIsRefused(): void
    {
        $this->expectException(InvalidLogic::class);
        $this->expectExceptionMessageMatches('/^A rule is valid JSON, nested at most 32 operations deep: Syntax error/');

        new JsonLogicReader()->read('{"==": [1, }');
    }

    public function testTheDeepestRuleWithinTheLimitIsReadAsJson(): void
    {
        $reader = new JsonLogicReader(new Limits(maxDepth: 1));

        self::assertEquals(new Not(new Variable('a')), $reader->read('{"!": [{"var": ["a"]}]}'));
    }

    public function testTheJsonIsLimitedInDepth(): void
    {
        $this->expectException(InvalidLogic::class);
        $this->expectExceptionMessageIsOrContains('Maximum stack depth exceeded');

        new JsonLogicReader(new Limits(maxDepth: 1))->read('[[[[[1]]]]]');
    }

    public function testOperationsAreLimitedInDepth(): void
    {
        $reader = new JsonLogicReader(new Limits(maxDepth: 2));

        self::assertEquals(new Not(new Not(new Literal(true))), $reader->parse(['!' => [['!' => [true]]]]));

        $this->expectExceptionObject(InvalidLogic::because('A rule is nested at most 2 operations deep.'));
        $reader->parse(['!' => [['!' => [['!' => [true]]]]]]);
    }

    public function testTheDefaultOfAVariableCountsInTheDepth(): void
    {
        $reader = new JsonLogicReader(new Limits(maxDepth: 2));

        self::assertEquals(new Variable('a', new Variable('b', new Literal(1))), $reader->parse(['var' => ['a', ['var' => ['b', 1]]]]));

        $this->expectExceptionObject(InvalidLogic::because('A rule is nested at most 2 operations deep.'));
        $reader->parse(['var' => ['a', ['var' => ['b', ['var' => ['c', 1]]]]]]);
    }

    public function testTheMessageOfAnUnknownOperationShowsItsStart(): void
    {
        $this->expectExceptionObject(InvalidLogic::because(\sprintf('An object of a rule is one operation of the subset: %s; "%s" is not read.', implode(', ', JsonLogicReader::OPERATORS), 'é'.str_repeat('ü', 39))));

        new JsonLogicReader()->parse(['é'.str_repeat('ü', 100) => 1]);
    }

    public function testNodesAreLimited(): void
    {
        $reader = new JsonLogicReader(new Limits(maxNodes: 3));

        self::assertEquals(new Comparison(ComparisonOperator::Equal, new Literal(1), new Literal(2)), $reader->parse(['==' => [1, 2]]));

        $this->expectExceptionObject(InvalidLogic::because('A rule has at most 3 nodes.'));
        $reader->parse(['and' => [1, 2, 3]]);
    }

    public function testListsAreLimited(): void
    {
        $reader = new JsonLogicReader(new Limits(maxListItems: 2));

        self::assertEquals(new ListOf([new Literal(1), new Literal(2)]), $reader->parse([1, 2]));

        $this->expectExceptionObject(InvalidLogic::because('A list of a rule has at most 2 items.'));
        $reader->parse([1, 2, 3]);
    }

    public function testTheNamesOfMissingAreLimited(): void
    {
        $reader = new JsonLogicReader(new Limits(maxListItems: 2));

        self::assertEquals(new Missing(['a', 'b']), $reader->parse(['missing' => ['a', 'b']]));

        $this->expectExceptionObject(InvalidLogic::because('A "missing" lists 1 to 2 names.'));
        $reader->parse(['missing' => ['a', 'b', 'c']]);
    }

    public function testMissingNamesAreAList(): void
    {
        $this->expectExceptionObject(InvalidLogic::because('A "missing" lists 1 to 500 names.'));

        new JsonLogicReader()->parse(['missing' => ['x' => 'a']]);
    }

    public function testTextsAreLimitedInCharacters(): void
    {
        $reader = new JsonLogicReader(new Limits(maxTextLength: 2));

        self::assertEquals(new Literal('éé'), $reader->parse('éé'));

        $this->expectExceptionObject(InvalidLogic::because('A text of a rule is UTF-8, at most 2 characters.'));
        $reader->parse('ééé');
    }

    public function testTextsAreUtf8(): void
    {
        $this->expectExceptionObject(InvalidLogic::because('A text of a rule is UTF-8, at most 10000 characters.'));

        new JsonLogicReader()->parse("\xC3");
    }

    public function testOnlyJsonValuesAreRead(): void
    {
        $this->expectExceptionObject(InvalidLogic::because('A rule holds JSON values only, not DateTimeImmutable.'));

        new JsonLogicReader()->parse(['==' => [new DateTimeImmutable(), 1]]);
    }

    /**
     * @return iterable<string, array{0: array<array-key, mixed>, 1: string}>
     */
    public static function refusedOperands(): iterable
    {
        yield 'var of three' => [['var' => ['a', 1, 2]], 'A "var" names its variable by a text, with a default after it or none.'];
        yield 'var empty' => [['var' => []], 'A "var" names its variable by a text, with a default after it or none.'];
        yield 'missing_some of one' => [['missing_some' => [1]], 'A "missing_some" takes how many names are needed, then the list of names.'];
        yield 'missing_some of three' => [['missing_some' => [1, ['a'], 2]], 'A "missing_some" takes how many names are needed, then the list of names.'];
        yield 'missing_some keyed' => [['missing_some' => ['a' => 1, 'b' => ['a']]], 'A "missing_some" takes how many names are needed, then the list of names.'];
        yield 'missing_some count as text' => [['missing_some' => ['1', ['a']]], 'A "missing_some" takes how many names are needed, then the list of names.'];
        yield 'missing_some names not a list' => [['missing_some' => [1, []]], 'A "missing_some" lists 1 to 500 names.'];
        yield 'missing of a number' => [['missing' => [1]], 'A "missing" lists the names of variables, as texts.'];
        yield 'equal of one' => [['==' => [1]], 'A "==" compares 2 operands.'];
        yield 'less of four' => [['<' => [1, 2, 3, 4]], 'A "<" compares 2 operands, or places one between 2 bounds.'];
        yield 'less or equal of one' => [['<=' => [1]], 'A "<=" compares 2 operands, or places one between 2 bounds.'];
        yield 'greater of three' => [['>' => [1, 2, 3]], 'A ">" compares 2 operands.'];
        yield 'not of two' => [['!' => [1, 2]], 'A "!" takes 1 operand.'];
        yield 'double not of none' => [['!!' => []], 'A "!!" takes 1 operand.'];
        yield 'and of none' => [['and' => []], 'An "and" has at least one operand.'];
        yield 'if of one' => [['if' => [true]], 'An "if" has at least one condition and its value.'];
        yield 'in of three' => [['in' => [1, 2, 3]], 'An "in" takes a needle and what it is looked for in.'];
        yield 'plus of none' => [['+' => []], 'A "+" takes at least one operand.'];
        yield 'divide of three' => [['/' => [1, 2, 3]], 'A "/" takes 2 operands.'];
        yield 'two operations' => [['==' => [1, 1], '!=' => [1, 2]], 'An object of a rule is one operation of the subset: '.implode(', ', JsonLogicReader::OPERATORS).'; "==" is not read.'];
    }

    /**
     * @param array<array-key, mixed> $rule
     */
    #[DataProvider('refusedOperands')]
    public function testOperandsAreChecked(array $rule, string $message): void
    {
        $this->expectExceptionObject(InvalidLogic::because($message));

        new JsonLogicReader()->parse($rule);
    }

    public function testEachLimitIsAtLeastOne(): void
    {
        self::assertSame(1, new Limits(1, 1, 1, 1, 1)->maxBytes);

        foreach ([[0, 1, 1, 1, 1], [1, 0, 1, 1, 1], [1, 1, 0, 1, 1], [1, 1, 1, 0, 1], [1, 1, 1, 1, 0]] as $limits) {
            try {
                new Limits(...$limits);
                self::fail('A limit of 0 is refused.');
            } catch (InvalidLogic $exception) {
                self::assertSame('Each limit of the reader is at least 1.', $exception->getMessage());
            }
        }
    }

    public function testTheDefaultLimits(): void
    {
        $limits = new Limits();

        self::assertSame([65_536, 32, 1_000, 500, 10_000], [$limits->maxBytes, $limits->maxDepth, $limits->maxNodes, $limits->maxListItems, $limits->maxTextLength]);
    }
}
