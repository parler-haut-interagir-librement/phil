<?php

declare(strict_types=1);

namespace Phil\AudienceSegments\Tests\Unit;

use Phil\AudienceSegments\Exception\InvalidSegment;
use Phil\AudienceSegments\Identifier;
use Phil\AudienceSegments\Segment;
use Phil\Rules\Condition\All;
use Phil\Rules\Condition\Leaf;
use Phil\Rules\Evaluation\Verdict;
use Phil\Rules\Fact\FactSource;
use Phil\Rules\Operand\Operands;
use Phil\Rules\Operand\OperandSchema;
use Phil\Rules\Rule;
use Phil\Rules\Type\ConditionType;
use Phil\Rules\Type\ConditionTypes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Segment::class)]
#[CoversClass(Identifier::class)]
#[CoversClass(InvalidSegment::class)]
final class SegmentTest extends TestCase
{
    public function testKeepsItsParts(): void
    {
        $rule = $this->rule('fact.present');
        $segment = new Segment('newsletter:fair-buyers', 'Acheteurs de la foire', $rule);

        self::assertSame('newsletter:fair-buyers', $segment->id);
        self::assertSame('Acheteurs de la foire', $segment->name);
        self::assertSame($rule, $segment->rule);
    }

    public function testAcceptsTheLongestName(): void
    {
        self::assertSame(200, \strlen(new Segment('s', str_repeat('a', 200), $this->rule('fact.present'))->name));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badNames(): iterable
    {
        yield 'empty' => [''];
        yield 'too long' => [str_repeat('a', 201)];
        yield 'with a new line' => ["a\nb"];
        yield 'ending with a new line' => ["ab\n"];
        yield 'with a delete' => ["a\x7F"];
        yield 'not UTF-8' => ["a\xC3"];
    }

    #[DataProvider('badNames')]
    public function testRefusesABadName(string $name): void
    {
        $this->expectException(InvalidSegment::class);
        $this->expectExceptionMessageIsOrContains('A segment name holds 1 to 200 bytes of UTF-8, without control characters.');

        new Segment('s', $name, $this->rule('fact.present'));
    }

    public function testRefusesABadIdentifier(): void
    {
        $this->expectException(InvalidSegment::class);
        $this->expectExceptionMessageIsOrContains('"a b" is not a segment identifier');

        new Segment('a b', 'Name', $this->rule('fact.present'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function forbidden(): iterable
    {
        yield 'opened' => ['email.opened'];
        yield 'clicked' => ['link-clicked'];
        yield 'score' => ['engagement_score'];
        yield 'predicted' => ['predicted.age'];
        yield 'open' => ['open'];
        yield 'lookalike' => ['lookalike.buyers'];
    }

    #[DataProvider('forbidden')]
    public function testRefusesAConditionThatProfilesOrReadsOpenings(string $type): void
    {
        $this->expectException(InvalidSegment::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The condition type "%s" guesses a trait, scores a behaviour or reads the opening or the clicking of a message; a segment never uses one.', $type));

        new Segment('s', 'Name', new Rule(new All(new Leaf('fact.present', ['fact' => 'a']), new Leaf($type)), ConditionTypes::builtIn()->with($this->type($type))));
    }

    public function testJudgesWholeWordsOnly(): void
    {
        $rule = new Rule(new Leaf('door.opener'), new ConditionTypes($this->type('door.opener')));

        self::assertSame(['door.opener'], new Segment('s', 'Name', $rule)->rule->typeNames());
    }

    private function rule(string $type): Rule
    {
        return new Rule(new Leaf($type, ['fact' => 'a']), ConditionTypes::builtIn());
    }

    private function type(string $name): ConditionType
    {
        return new readonly class($name) implements ConditionType {
            public function __construct(private string $typeName)
            {
            }

            public function name(): string
            {
                return $this->typeName;
            }

            public function operands(): OperandSchema
            {
                return new OperandSchema();
            }

            public function evaluate(Operands $operands, FactSource $facts): Verdict
            {
                return Verdict::pass('always');
            }
        };
    }
}
