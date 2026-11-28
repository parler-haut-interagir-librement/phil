<?php

declare(strict_types=1);

namespace Phil\Rules\Tests\Unit;

use Phil\Rules\Condition\All;
use Phil\Rules\Condition\Any;
use Phil\Rules\Condition\Condition;
use Phil\Rules\Condition\Leaf;
use Phil\Rules\Condition\Not;
use Phil\Rules\Evaluation\NodeKind;
use Phil\Rules\Evaluation\Outcome;
use Phil\Rules\Evaluation\Plan;
use Phil\Rules\Evaluation\Result;
use Phil\Rules\Evaluation\Step;
use Phil\Rules\Exception\InvalidRule;
use Phil\Rules\Exception\LimitExceeded;
use Phil\Rules\Fact\Facts;
use Phil\Rules\Limits;
use Phil\Rules\Rule;
use Phil\Rules\Tests\Support\Recorder;
use Phil\Rules\Type\ConditionTypes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Rule::class)]
#[CoversClass(Plan::class)]
#[CoversClass(All::class)]
#[CoversClass(Any::class)]
#[CoversClass(Not::class)]
#[CoversClass(Leaf::class)]
#[CoversClass(Result::class)]
#[CoversClass(Step::class)]
#[CoversClass(InvalidRule::class)]
#[CoversClass(LimitExceeded::class)]
final class RuleTest extends TestCase
{
    private Recorder $recorder;

    private ConditionTypes $types;

    protected function setUp(): void
    {
        $this->recorder = new Recorder();
        $this->types = new ConditionTypes($this->recorder);
    }

    public function testAllStopsAtTheFirstFailureAndSkipsTheRest(): void
    {
        $result = $this->rule(new All($this->flag(true, 'a'), $this->flag(false, 'b'), $this->flag(true, 'c')))->evaluate(Facts::of([]));

        self::assertFalse($result->passed);
        self::assertSame(['a', 'b'], $this->recorder->evaluated);
        self::assertSame(2, $result->evaluations);
        self::assertSame([Outcome::Passed, Outcome::Failed, Outcome::Skipped], array_map(static fn (Step $step): Outcome => $step->outcome, $result->trace->children));
        self::assertSame('/condition/all/2', $result->trace->children[2]->path);
        self::assertSame('test.flag', $result->trace->children[2]->type);
        self::assertNull($result->trace->children[2]->reason);
        self::assertSame(['b'], $this->labels($result->explanation()));
    }

    public function testAllPassesWhenEveryConditionHolds(): void
    {
        $result = $this->rule(new All($this->flag(true, 'a'), $this->flag(true, 'b')))->evaluate(Facts::of([]));

        self::assertTrue($result->passed);
        self::assertSame(Outcome::Passed, $result->trace->outcome);
        self::assertSame(NodeKind::All, $result->trace->kind);
        self::assertSame(['a', 'b'], $this->labels($result->explanation()));
    }

    public function testAnyStopsAtTheFirstSuccess(): void
    {
        $result = $this->rule(new Any($this->flag(false, 'a'), $this->flag(true, 'b'), $this->flag(false, 'c')))->evaluate(Facts::of([]));

        self::assertTrue($result->passed);
        self::assertSame(['a', 'b'], $this->recorder->evaluated);
        self::assertSame(['b'], $this->labels($result->explanation()));
        self::assertSame(NodeKind::Any, $result->trace->kind);
    }

    public function testAnyFailsWhenNoConditionHolds(): void
    {
        $result = $this->rule(new Any($this->flag(false, 'a'), $this->flag(false, 'b')))->evaluate(Facts::of([]));

        self::assertFalse($result->passed);
        self::assertSame(['a', 'b'], $this->labels($result->explanation()));
    }

    public function testNotInvertsAndExplainsByItsCondition(): void
    {
        $failing = $this->rule(new Not($this->flag(true, 'a')))->evaluate(Facts::of([]));
        $passing = $this->rule(new Not($this->flag(false, 'b')))->evaluate(Facts::of([]));

        self::assertFalse($failing->passed);
        self::assertSame(NodeKind::Not, $failing->trace->kind);
        self::assertSame('/condition/not', $failing->trace->children[0]->path);
        self::assertSame(['a'], $this->labels($failing->explanation()));
        self::assertTrue($passing->passed);
        self::assertSame(['b'], $this->labels($passing->explanation()));
    }

    public function testTracesTheBranchThatFailedThroughNestedConditions(): void
    {
        $rule = $this->rule(new All(
            $this->flag(true, 'a'),
            new Any($this->flag(false, 'b'), new Not($this->flag(true, 'c'))),
        ));
        $result = $rule->evaluate(Facts::of([]));

        self::assertFalse($result->passed);
        self::assertSame(['/condition/all/1/any/0', '/condition/all/1/any/1/not'], array_map(static fn (Step $step): string => $step->path, $result->explanation()));
        self::assertSame('flag.down', $result->explanation()[0]->reason);
        self::assertSame(['label' => 'b'], $result->explanation()[0]->details);
        self::assertSame(['test.flag'], $rule->typeNames());
    }

    public function testSkipsAWholeBranchWithoutEvaluatingIt(): void
    {
        $result = $this->rule(new Any($this->flag(true, 'a'), new All($this->flag(true, 'b'))))->evaluate(Facts::of([]));

        $skipped = $result->trace->children[1];
        self::assertSame(Outcome::Skipped, $skipped->outcome);
        self::assertSame(NodeKind::All, $skipped->kind);
        self::assertNull($skipped->type);
        self::assertSame([], $skipped->children);
        self::assertSame(['a'], $this->recorder->evaluated);
    }

    public function testKeepsTheOperandsAsTheSchemaReadsThem(): void
    {
        $rule = new Rule(new Leaf('fact.compare', ['value' => 3, 'operator' => 'gt', 'fact' => 'n']), ConditionTypes::builtIn());

        self::assertEquals(new Leaf('fact.compare', ['fact' => 'n', 'operator' => 'gt', 'value' => 3.0]), $rule->condition);
    }

    public function testKeepsTheShapeOfTheTree(): void
    {
        $rule = $this->rule(new All(new Any($this->flag(true, 'a')), new Not($this->flag(false, 'b'))));

        self::assertEquals(new All(new Any($this->flag(true, 'a')), new Not($this->flag(false, 'b'))), $rule->condition);
        self::assertSame($this->types, $rule->types);
    }

    public function testRefusesAnUnknownType(): void
    {
        $this->expectException(InvalidRule::class);
        $this->expectExceptionMessageIsOrContains('The condition at "/condition/any/1" is of type "test.other", which no package registered.');

        $this->rule(new Any($this->flag(true), new Leaf('test.other')));
    }

    public function testRefusesBadOperands(): void
    {
        $this->expectException(InvalidRule::class);
        $this->expectExceptionMessageIsOrContains('The operand "holds" of the condition "test.flag" at "/condition/not" must be true or false.');

        $this->rule(new Not(new Leaf('test.flag', ['holds' => 'yes'])));
    }

    public function testRefusesAnEmptyAll(): void
    {
        $this->expectException(InvalidRule::class);
        $this->expectExceptionMessageIsOrContains('The rule is malformed at "/condition/all/0": "all" holds no condition.');

        $this->rule(new All(new All()));
    }

    public function testRefusesAnEmptyAny(): void
    {
        $this->expectException(InvalidRule::class);
        $this->expectExceptionMessageIsOrContains('The rule is malformed at "/condition": "any" holds no condition.');

        $this->rule(new Any());
    }

    public function testRefusesAConditionOfAnotherClass(): void
    {
        $this->expectException(InvalidRule::class);
        $this->expectExceptionMessageIsOrContains('a condition is All, Any, Not or Leaf.');

        $this->rule(new All(new class implements Condition {}));
    }

    public function testBoundsTheDepth(): void
    {
        $limits = new Limits(maxDepth: 3);
        $deep = new Not(new Not($this->flag(true)));

        self::assertTrue(new Rule($deep, $this->types, $limits)->evaluate(Facts::of([]))->passed);

        $this->expectException(LimitExceeded::class);
        $this->expectExceptionMessageIsOrContains('The condition at "/condition/not/not/not" is nested deeper than 3 levels.');

        new Rule(new Not($deep), $this->types, $limits);
    }

    public function testBoundsTheNodes(): void
    {
        $limits = new Limits(maxNodes: 3);

        self::assertSame($limits, new Rule(new All($this->flag(true), $this->flag(true)), $this->types, $limits)->limits);

        $this->expectException(LimitExceeded::class);
        $this->expectExceptionMessageIsOrContains('The rule holds more than 3 conditions.');

        new Rule(new All($this->flag(true), $this->flag(true), $this->flag(true)), $this->types, $limits);
    }

    public function testBoundsTheEvaluations(): void
    {
        $limits = new Limits(maxEvaluations: 2);

        self::assertSame(2, new Rule(new All($this->flag(true), $this->flag(true)), $this->types, $limits)->evaluate(Facts::of([]))->evaluations);

        $this->expectException(LimitExceeded::class);
        $this->expectExceptionMessageIsOrContains('The rule needs more than 2 evaluations of its conditions.');

        new Rule(new All($this->flag(true), $this->flag(true), $this->flag(true)), $this->types, $limits)->evaluate(Facts::of([]));
    }

    public function testCountsEvaluationsAfreshForEachEvaluation(): void
    {
        $rule = new Rule(new All($this->flag(true), $this->flag(true)), $this->types, new Limits(maxEvaluations: 2));

        self::assertSame(2, $rule->evaluate(Facts::of([]))->evaluations);
        self::assertSame(2, $rule->evaluate(Facts::of([]))->evaluations);
    }

    public function testListsEachTypeOnce(): void
    {
        $rule = new Rule(
            new All(new Leaf('fact.present', ['fact' => 'a']), new Not(new Leaf('fact.equals', ['fact' => 'b', 'value' => 1])), new Leaf('fact.present', ['fact' => 'c'])),
            ConditionTypes::builtIn(),
        );

        self::assertSame(['fact.present', 'fact.equals'], $rule->typeNames());
    }

    public function testSkipsEveryConditionAfterTheDecidingOne(): void
    {
        $result = $this->rule(new All($this->flag(false, 'a'), $this->flag(true, 'b'), $this->flag(true, 'c')))->evaluate(Facts::of([]));

        self::assertSame([Outcome::Failed, Outcome::Skipped, Outcome::Skipped], array_map(static fn (Step $step): Outcome => $step->outcome, $result->trace->children));
        self::assertSame(['/condition/all/0', '/condition/all/1', '/condition/all/2'], array_map(static fn (Step $step): string => $step->path, $result->trace->children));
        self::assertSame(['a'], $this->recorder->evaluated);
    }

    public function testAcceptsAllAndAnyNestedToTheDepthLimit(): void
    {
        $limits = new Limits(maxDepth: 3);
        $rule = new Rule(new All(new Any($this->flag(true))), $this->types, $limits);

        self::assertTrue($rule->evaluate(Facts::of([]))->passed);
    }

    public function testBoundsTheDepthThroughAllAndAnyBeforeLookingAtTheRest(): void
    {
        $this->expectException(LimitExceeded::class);
        $this->expectExceptionMessageIsOrContains('The condition at "/condition/all/0/any/0/all/0" is nested deeper than 3 levels.');

        new Rule(new All(new Any(new All($this->flag(true))), new Leaf('test.other')), $this->types, new Limits(maxDepth: 3));
    }

    public function testKeepsTheConditionsOfAllAndAnyAsLists(): void
    {
        $conditions = ['first' => $this->flag(true, 'a'), 'second' => $this->flag(false, 'b')];

        self::assertSame([0, 1], array_keys(new All(...$conditions)->conditions));
        self::assertSame([0, 1], array_keys(new Any(...$conditions)->conditions));
    }

    public function testListsTheTypesInOrderOfFirstUseAsAList(): void
    {
        $rule = new Rule(
            new Any(new Leaf('fact.equals', ['fact' => 'a', 'value' => 1]), new Leaf('fact.present', ['fact' => 'a']), new Leaf('fact.equals', ['fact' => 'b', 'value' => 2])),
            ConditionTypes::builtIn(),
        );

        self::assertSame(['fact.equals', 'fact.present'], $rule->typeNames());
    }

    private function rule(Condition $condition): Rule
    {
        return new Rule($condition, $this->types);
    }

    private function flag(bool $holds, ?string $label = null): Leaf
    {
        return new Leaf('test.flag', null === $label ? ['holds' => $holds] : ['holds' => $holds, 'label' => $label]);
    }

    /**
     * @param list<Step> $steps
     *
     * @return list<scalar|null>
     */
    private function labels(array $steps): array
    {
        return array_map(static fn (Step $step): mixed => $step->details['label'] ?? null, $steps);
    }
}
