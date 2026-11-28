<?php

declare(strict_types=1);

namespace Phil\Rules\Tests\Unit\Json;

use Phil\Rules\Condition\All;
use Phil\Rules\Condition\Any;
use Phil\Rules\Condition\Leaf;
use Phil\Rules\Condition\Not;
use Phil\Rules\Evaluation\Verdict;
use Phil\Rules\Exception\InvalidRule;
use Phil\Rules\Exception\LimitExceeded;
use Phil\Rules\Fact\Facts;
use Phil\Rules\Fact\FactSource;
use Phil\Rules\Json\RuleReader;
use Phil\Rules\Json\RuleWriter;
use Phil\Rules\Limits;
use Phil\Rules\Operand\Operands;
use Phil\Rules\Operand\OperandSchema;
use Phil\Rules\Tests\Support\Recorder;
use Phil\Rules\Type\ConditionType;
use Phil\Rules\Type\ConditionTypes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(RuleReader::class)]
#[CoversClass(RuleWriter::class)]
#[CoversClass(InvalidRule::class)]
#[CoversClass(LimitExceeded::class)]
final class RuleReaderTest extends TestCase
{
    private const string RULE = <<<'JSON'
        {
            "version": 1,
            "condition": {
                "all": [
                    {"type": "fact.in", "operands": {"fact": "country", "values": ["CA", "FR"]}},
                    {"not": {"type": "fact.present", "operands": {"fact": "unsubscribed_at"}}},
                    {"any": [
                        {"type": "fact.compare", "operands": {"fact": "orders", "operator": "gte", "value": 2}},
                        {"type": "date.compare", "operands": {"fact": "joined", "operator": "before", "date": "2026-01-01"}}
                    ]}
                ]
            }
        }
        JSON;

    private RuleReader $reader;

    protected function setUp(): void
    {
        $this->reader = new RuleReader(ConditionTypes::builtIn());
    }

    public function testReadsTheJsonForm(): void
    {
        $rule = $this->reader->fromJson(self::RULE);

        self::assertEquals(new All(
            new Leaf('fact.in', ['fact' => 'country', 'values' => ['CA', 'FR']]),
            new Not(new Leaf('fact.present', ['fact' => 'unsubscribed_at'])),
            new Any(
                new Leaf('fact.compare', ['fact' => 'orders', 'operator' => 'gte', 'value' => 2.0]),
                new Leaf('date.compare', ['fact' => 'joined', 'operator' => 'before', 'date' => '2026-01-01']),
            ),
        ), $rule->condition);
        self::assertTrue($rule->evaluate(Facts::of(['country' => 'CA', 'orders' => 3]))->passed);
    }

    public function testReadsBackWhatItWrites(): void
    {
        $writer = new RuleWriter();
        $rule = $this->reader->fromJson(self::RULE);
        $json = $writer->toJson($rule);

        self::assertSame(
            '{"version":1,"condition":{"all":[{"type":"fact.in","operands":{"fact":"country","values":["CA","FR"]}},{"not":{"type":"fact.present","operands":{"fact":"unsubscribed_at"}}},{"any":[{"type":"fact.compare","operands":{"fact":"orders","operator":"gte","value":2.0}},{"type":"date.compare","operands":{"fact":"joined","operator":"before","date":"2026-01-01"}}]}]}}',
            $json,
        );
        self::assertSame($json, $writer->toJson($this->reader->fromJson($json)));
        self::assertSame($json, $writer->toJson($this->reader->fromArray($writer->toArray($rule))));
        self::assertSame(hash('sha256', $json), $writer->fingerprint($rule));
    }

    public function testWritesEmptyOperandsAsAnObject(): void
    {
        $writer = new RuleWriter();
        $rule = new RuleReader(new ConditionTypes(new Recorder()))->fromArray(['version' => 1, 'condition' => ['type' => 'test.flag', 'operands' => ['holds' => true]]]);

        self::assertSame(['version' => 1, 'condition' => ['type' => 'test.flag', 'operands' => ['holds' => true]]], $writer->toArray($rule));
        self::assertSame('{"version":1,"condition":{"type":"test.flag","operands":{"holds":true}}}', $writer->toJson($rule));
    }

    public function testWritesUnicodeAndSlashesAsTheyAre(): void
    {
        $rule = $this->reader->fromArray(['version' => 1, 'condition' => ['type' => 'fact.equals', 'operands' => ['fact' => 'city', 'value' => 'Montréal/Est']]]);

        self::assertSame('{"version":1,"condition":{"type":"fact.equals","operands":{"fact":"city","value":"Montréal/Est"}}}', new RuleWriter()->toJson($rule));
    }

    public function testALeafMayOmitItsOperandsWhenItsTypeNeedsNone(): void
    {
        $types = new ConditionTypes(new Recorder())->with(new class implements ConditionType {
            public function name(): string
            {
                return 'test.always';
            }

            public function operands(): OperandSchema
            {
                return new OperandSchema();
            }

            public function evaluate(Operands $operands, FactSource $facts): Verdict
            {
                return Verdict::pass('always');
            }
        });
        $rule = new RuleReader($types)->fromJson('{"version":1,"condition":{"type":"test.always"}}');

        self::assertEquals(new Leaf('test.always'), $rule->condition);
        self::assertSame('{"version":1,"condition":{"type":"test.always","operands":{}}}', new RuleWriter()->toJson($rule));
        self::assertSame(['version' => 1, 'condition' => ['type' => 'test.always', 'operands' => []]], new RuleWriter()->toArray($rule));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function malformed(): iterable
    {
        yield 'not JSON' => ['{"version": 1,', 'The rule is not valid JSON: Syntax error'];
        yield 'a text' => ['"rule"', 'The rule is malformed at "": a rule is an object with "version" and "condition".'];
        yield 'a list' => ['[1]', 'The rule is malformed at "": a rule is an object with "version" and "condition" only.'];
        yield 'an empty object' => ['{}', 'The rule is malformed at "": a rule is an object with "version" and "condition" only.'];
        yield 'another key' => ['{"version": 1, "condition": {"type": "fact.present", "operands": {"fact": "a"}}, "name": "x"}', 'a rule is an object with "version" and "condition" only.'];
        yield 'no version' => ['{"condition": {"type": "fact.present", "operands": {"fact": "a"}}}', 'The rule is of version null'];
        yield 'version 2' => ['{"version": 2, "condition": {}}', 'The rule is of version 2; this library reads version 1.'];
        yield 'version as text' => ['{"version": "1", "condition": {}}', 'The rule is of version string'];
        yield 'no condition' => ['{"version": 1}', 'The rule is malformed at "": the rule holds no "condition".'];
        yield 'a condition as text' => ['{"version": 1, "condition": "all"}', 'The rule is malformed at "/condition": a condition is an object.'];
        yield 'an empty condition' => ['{"version": 1, "condition": {}}', 'The rule is malformed at "/condition": a condition is an object.'];
        yield 'a condition as list' => ['{"version": 1, "condition": [{"type": "fact.present"}]}', 'The rule is malformed at "/condition": a condition is an object.'];
        yield 'two keys' => ['{"version": 1, "condition": {"all": [], "any": []}}', 'The rule is malformed at "/condition": a condition holds one of "all", "any", "not" or "type".'];
        yield 'an unknown key' => ['{"version": 1, "condition": {"xor": []}}', 'The rule is malformed at "/condition": "xor" is not a condition; use "all", "any", "not" or "type".'];
        yield 'an empty all' => ['{"version": 1, "condition": {"all": []}}', 'The rule is malformed at "/condition": "all" holds a non-empty list of conditions.'];
        yield 'an any as object' => ['{"version": 1, "condition": {"any": {"a": {"type": "fact.present"}}}}', 'The rule is malformed at "/condition": "any" holds a non-empty list of conditions.'];
        yield 'an all as text' => ['{"version": 1, "condition": {"all": "x"}}', '"all" holds a non-empty list of conditions.'];
        yield 'a not as list' => ['{"version": 1, "condition": {"not": []}}', 'The rule is malformed at "/condition/not": a condition is an object.'];
        yield 'a nested problem' => ['{"version": 1, "condition": {"any": [{"type": "fact.present", "operands": {"fact": "a"}}, {"all": [{"nope": 1}]}]}}', 'The rule is malformed at "/condition/any/1/all/0": "nope" is not a condition'];
        yield 'a leaf with another key' => ['{"version": 1, "condition": {"type": "fact.present", "operands": {"fact": "a"}, "weight": 2}}', 'The rule is malformed at "/condition": a leaf holds "type" and "operands" only.'];
        yield 'a type as number' => ['{"version": 1, "condition": {"type": 3}}', 'a leaf has a text "type" and an object "operands".'];
        yield 'operands as text' => ['{"version": 1, "condition": {"type": "fact.present", "operands": "a"}}', 'a leaf has a text "type" and an object "operands".'];
        yield 'operands null' => ['{"version": 1, "condition": {"type": "fact.present", "operands": null}}', 'a leaf has a text "type" and an object "operands".'];
        yield 'operands as list' => ['{"version": 1, "condition": {"type": "fact.present", "operands": ["a"]}}', 'The rule is malformed at "/condition/operands": the operands are an object keyed by name, not a list.'];
        yield 'an unknown type' => ['{"version": 1, "condition": {"type": "fact.like", "operands": {}}}', 'The condition at "/condition" is of type "fact.like", which no package registered.'];
        yield 'a missing operand' => ['{"version": 1, "condition": {"type": "fact.present", "operands": {}}}', 'The operand "fact" of the condition "fact.present" at "/condition" is required.'];
    }

    #[DataProvider('malformed')]
    public function testRefusesAMalformedRule(string $json, string $message): void
    {
        $this->expectException(InvalidRule::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->reader->fromJson($json);
    }

    public function testReadsABigIntegerAsANumber(): void
    {
        $rule = $this->reader->fromJson('{"version": 1, "condition": {"type": "fact.equals", "operands": {"fact": "a", "value": 123456789012345678901234567890}}}');

        self::assertEquals(new Leaf('fact.equals', ['fact' => 'a', 'value' => 1.2345678901234568E+29]), $rule->condition);
    }

    public function testBoundsTheBytesRead(): void
    {
        $json = '{"version":1,"condition":{"type":"fact.present","operands":{"fact":"a"}}}';
        $reader = new RuleReader(ConditionTypes::builtIn(), new Limits(maxBytes: \strlen($json)));

        self::assertSame(['fact.present'], $reader->fromJson($json)->typeNames());

        $this->expectException(LimitExceeded::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The rule holds %d bytes; at most %d are read.', \strlen($json) + 1, \strlen($json)));

        $reader->fromJson($json.' ');
    }

    public function testBoundsTheDepthWhileReading(): void
    {
        $reader = new RuleReader(ConditionTypes::builtIn(), new Limits(maxDepth: 2));

        self::assertSame(['fact.present'], $reader->fromJson('{"version":1,"condition":{"not":{"type":"fact.present","operands":{"fact":"a"}}}}')->typeNames());

        $this->expectException(LimitExceeded::class);
        $this->expectExceptionMessageIsOrContains('The condition at "/condition/not/not" is nested deeper than 2 levels.');

        $reader->fromArray(['version' => 1, 'condition' => ['not' => ['not' => ['type' => 'fact.present', 'operands' => ['fact' => 'a']]]]]);
    }

    public function testBoundsTheDepthOfTheJsonItself(): void
    {
        $reader = new RuleReader(ConditionTypes::builtIn(), new Limits(maxDepth: 2));
        $deepest = '{"version":1,"condition":{"any":[{"type":"fact.in","operands":{"fact":"a","values":["x"]}}]}}';

        self::assertSame(['fact.in'], $reader->fromJson($deepest)->typeNames());

        $this->expectException(LimitExceeded::class);
        $this->expectExceptionMessageIsOrContains('The condition at "/condition" is nested deeper than 2 levels.');

        $reader->fromJson('{"version":1,"condition":{"any":[{"type":"fact.in","operands":{"fact":"a","values":[[[["x"]]]]}}]}}');
    }

    public function testTellsTheDepthOfTheJsonFromTheOperandRefusedAtTheLimit(): void
    {
        $reader = new RuleReader(ConditionTypes::builtIn(), new Limits(maxDepth: 2));
        $rule = static fn (string $values): string => '{"version":1,"condition":{"any":[{"type":"fact.in","operands":{"fact":"a","values":'.$values.'}}]}}';

        try {
            $reader->fromJson($rule('[["x"]]'));
            self::fail('The nested list is refused.');
        } catch (InvalidRule $invalidRule) {
            self::assertStringContainsString('item 0 must be a text, a number or a boolean', $invalidRule->getMessage());
        }

        $this->expectException(LimitExceeded::class);

        $reader->fromJson($rule('[[["x"]]]'));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function tooDeepAfterAnUnknownType(): iterable
    {
        $unknown = ['type' => 'test.unknown'];
        $leaf = ['type' => 'fact.present', 'operands' => ['fact' => 'a']];

        yield 'through not' => [['all' => [$unknown, ['not' => ['not' => $leaf]]]], '/condition/all/1/not/not'];
        yield 'through all' => [['all' => [$unknown, ['all' => [['all' => [$leaf]]]]]], '/condition/all/1/all/0/all/0'];
        yield 'through any' => [['all' => [$unknown, ['any' => [['any' => [$leaf]]]]]], '/condition/all/1/any/0/any/0'];
    }

    /**
     * @param array<string, mixed> $condition
     */
    #[DataProvider('tooDeepAfterAnUnknownType')]
    public function testBoundsTheDepthWhileReadingBeforeTheTypesAreChecked(array $condition, string $path): void
    {
        $this->expectException(LimitExceeded::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The condition at "%s" is nested deeper than 3 levels.', $path));

        new RuleReader(ConditionTypes::builtIn(), new Limits(maxDepth: 3))->fromArray(['version' => 1, 'condition' => $condition]);
    }

    public function testBoundsTheNodesWhileReadingBeforeTheTypesAreChecked(): void
    {
        $this->expectException(LimitExceeded::class);
        $this->expectExceptionMessageIsOrContains('The rule holds more than 2 conditions.');

        new RuleReader(ConditionTypes::builtIn(), new Limits(maxNodes: 2))->fromArray(['version' => 1, 'condition' => ['all' => [['type' => 'test.unknown'], ['type' => 'test.unknown']]]]);
    }

    public function testBoundsTheNodesWhileReading(): void
    {
        $reader = new RuleReader(ConditionTypes::builtIn(), new Limits(maxNodes: 2));
        $leaf = ['type' => 'fact.present', 'operands' => ['fact' => 'a']];

        self::assertSame(['fact.present'], $reader->fromArray(['version' => 1, 'condition' => ['all' => [$leaf]]])->typeNames());

        $this->expectException(LimitExceeded::class);
        $this->expectExceptionMessageIsOrContains('The rule holds more than 2 conditions.');

        $reader->fromArray(['version' => 1, 'condition' => ['all' => [$leaf, $leaf]]]);
    }
}
