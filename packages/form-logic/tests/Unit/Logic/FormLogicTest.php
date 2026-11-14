<?php

declare(strict_types=1);

namespace Phil\FormLogic\Tests\Unit\Logic;

use Phil\FormLogic\Exception\InvalidLogic;
use Phil\FormLogic\Expression\Literal;
use Phil\FormLogic\JsonLogic\JsonLogicReader;
use Phil\FormLogic\JsonLogic\Limits;
use Phil\FormLogic\Logic\ComputedVariable;
use Phil\FormLogic\Logic\FieldCondition;
use Phil\FormLogic\Logic\FormLogic;
use Phil\FormLogic\Logic\Judge;
use Phil\FormLogic\Logic\Verdict;
use Phil\FormLogic\Typing\Schema;
use Phil\FormLogic\Typing\VariableDefinition;
use Phil\FormLogic\Typing\VariableType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FormLogic::class)]
#[CoversClass(FieldCondition::class)]
#[CoversClass(ComputedVariable::class)]
#[CoversClass(Judge::class)]
#[CoversClass(Verdict::class)]
final class FormLogicTest extends TestCase
{
    /**
     * @return list<array<string, mixed>>
     */
    private function steps(): array
    {
        return [
            ['field' => 'guest_name', 'when' => ['==' => [['var' => 'bringing_guest'], true]]],
            ['field' => 'guest_diet', 'when' => ['!!' => [['var' => 'guest_name']]]],
            ['compute' => 'seats', 'type' => 'number', 'value' => ['+' => [1, ['if' => [['var' => 'bringing_guest'], 1, 0]]]]],
            ['field' => 'table', 'when' => ['>' => [['var' => 'seats'], 1]]],
        ];
    }

    public function testTheLogicIsReadBackFromItsArray(): void
    {
        $logic = FormLogic::fromArray($this->steps());

        self::assertSame($this->steps(), $logic->toArray());
        self::assertCount(4, $logic->steps);
        self::assertInstanceOf(ComputedVariable::class, $logic->steps[2]);
        self::assertSame(VariableType::Number, $logic->steps[2]->type);
        self::assertSame([], new FormLogic()->toArray());
    }

    public function testTheReaderOfItsLimitsReadsTheRules(): void
    {
        $this->expectExceptionObject(InvalidLogic::because('A rule has at most 2 nodes.'));

        FormLogic::fromArray($this->steps(), new JsonLogicReader(new Limits(maxNodes: 2)));
    }

    public function testATextComputedVariable(): void
    {
        $logic = FormLogic::fromArray([['compute' => 'band', 'type' => 'text', 'value' => 'gold']]);

        self::assertSame(VariableType::Text, $logic->steps[0]->type ?? null);
    }

    public function testAStepOfAnotherShapeIsRefused(): void
    {
        $shapes = [
            'not a map' => 'x',
            'field without when' => ['field' => 'a'],
            'field with more' => ['field' => 'a', 'when' => true, 'extra' => 1],
            'field not a text' => ['field' => 1, 'when' => true],
            'compute without type' => ['compute' => 'a', 'value' => 1],
            'compute of an unknown type' => ['compute' => 'a', 'type' => 'money', 'value' => 1],
            'compute type not a text' => ['compute' => 'a', 'type' => 1, 'value' => 1],
            'compute without value' => ['compute' => 'a', 'type' => 'number'],
            'compute name not a text' => ['compute' => 1, 'type' => 'number', 'value' => 1],
            'compute with more' => ['compute' => 'a', 'type' => 'number', 'value' => 1, 'extra' => 1],
        ];

        foreach ($shapes as $label => $step) {
            try {
                FormLogic::fromArray([$step]);
                self::fail($label);
            } catch (InvalidLogic $exception) {
                self::assertSame('A step is {"field": name, "when": rule} or {"compute": name, "type": type, "value": rule}.', $exception->getMessage(), $label);
            }
        }

        self::assertCount(1, FormLogic::fromArray([['field' => 'a', 'when' => null]])->steps);
        self::assertCount(1, FormLogic::fromArray([['compute' => 'a', 'type' => 'boolean', 'value' => null]])->steps);
    }

    public function testAStepNamesOneThing(): void
    {
        $this->expectExceptionObject(InvalidLogic::because('"a" has one condition or computation only.'));

        new FormLogic([new FieldCondition('a', new Literal(true)), new ComputedVariable('a', new Literal(1))]);
    }

    public function testAStepReadsNothingAfterIt(): void
    {
        $this->expectExceptionObject(InvalidLogic::because('"a" reads "b", which comes after it or is itself.'));

        FormLogic::fromArray([['field' => 'a', 'when' => ['var' => 'b']], ['field' => 'b', 'when' => true]]);
    }

    public function testAStepDoesNotReadItself(): void
    {
        $this->expectExceptionObject(InvalidLogic::because('"total" reads "total", which comes after it or is itself.'));

        FormLogic::fromArray([['compute' => 'total', 'type' => 'number', 'value' => ['+' => [['var' => 'total'], 1]]]]);
    }

    public function testAStepReadsTheStepsBeforeIt(): void
    {
        $logic = FormLogic::fromArray([['field' => 'b', 'when' => true], ['field' => 'a', 'when' => ['var' => 'b']], ['field' => 'c', 'when' => ['var' => 'b']]]);

        self::assertCount(3, $logic->steps);
    }

    public function testTheStepsAreBounded(): void
    {
        $steps = [];

        for ($index = 0; $index < FormLogic::MAX_STEPS; ++$index) {
            $steps[] = new FieldCondition('f'.$index, new Literal(true));
        }

        self::assertCount(500, new FormLogic($steps)->steps);

        $this->expectExceptionObject(InvalidLogic::because("A form's logic has at most 500 steps."));
        new FormLogic([...$steps, new FieldCondition('last', new Literal(true))]);
    }

    public function testTheStepsAreCheckedAgainstTheFieldsAndTheVariablesBeforeThem(): void
    {
        $fields = new Schema([
            new VariableDefinition('bringing_guest', VariableType::Boolean),
            new VariableDefinition('guest_name', VariableType::Text),
            new VariableDefinition('guest_diet', VariableType::Text),
            new VariableDefinition('table', VariableType::Choice, ['front', 'back']),
        ]);

        FormLogic::fromArray($this->steps())->check($fields);

        $this->expectExceptionObject(InvalidLogic::because('"seats" is compared with "many", a value it never takes.'));
        FormLogic::fromArray([...$this->steps(), ['field' => 'note', 'when' => ['==' => [['var' => 'seats'], 'many']]]])->check($fields);
    }

    public function testAComputedVariableNamedAsAFieldIsRefusedByTheCheck(): void
    {
        $this->expectExceptionObject(InvalidLogic::because('The variable "table" is defined twice.'));

        FormLogic::fromArray([['compute' => 'table', 'type' => 'number', 'value' => 1]])->check(new Schema([new VariableDefinition('table', VariableType::Number)]));
    }

    public function testNamesAreChecked(): void
    {
        foreach ([static fn (): FieldCondition => new FieldCondition('1a', new Literal(true)), static fn (): ComputedVariable => new ComputedVariable('a b', new Literal(1))] as $build) {
            try {
                $build();
                self::fail('The name is checked.');
            } catch (InvalidLogic $exception) {
                self::assertStringStartsWith('A variable is named by', $exception->getMessage());
            }
        }
    }

    public function testAComputedVariableIsNotAChoice(): void
    {
        foreach ([VariableType::Choice, VariableType::Choices] as $type) {
            try {
                new ComputedVariable('total', new Literal(1), $type);
                self::fail('A choice is refused.');
            } catch (InvalidLogic $exception) {
                self::assertSame('The computed variable "total" gives a number, a text, a boolean or a day.', $exception->getMessage());
            }
        }

        self::assertSame(VariableType::Date, new ComputedVariable('when', new Literal('2026-01-01'), VariableType::Date)->type);
        self::assertSame(VariableType::Number, new ComputedVariable('n', new Literal(1))->type);
    }

    public function testTheJudgeHidesInCascadeAndComputes(): void
    {
        $judge = new Judge();
        $logic = FormLogic::fromArray($this->steps());

        $verdict = $judge->judge($logic, ['bringing_guest' => false, 'guest_name' => 'Grace', 'guest_diet' => 'vegan', 'table' => 'front', 'seats' => 99]);

        self::assertSame(['guest_name', 'guest_diet', 'table'], $verdict->hidden);
        self::assertSame(['bringing_guest' => false], $verdict->answer);
        self::assertSame(['seats' => 1], $verdict->computed);
        self::assertFalse($verdict->isShown('guest_diet'));
        self::assertTrue($verdict->isShown('bringing_guest'));

        $verdict = $judge->judge($logic, ['bringing_guest' => true, 'guest_name' => 'Grace', 'table' => 'back']);

        self::assertSame([], $verdict->hidden);
        self::assertSame(['bringing_guest' => true, 'guest_name' => 'Grace', 'table' => 'back'], $verdict->answer);
        self::assertSame(['seats' => 2], $verdict->computed);
    }

    public function testAComputedVariableReadsTheFieldsHiddenBeforeItAsUnanswered(): void
    {
        $logic = FormLogic::fromArray([
            ['field' => 'extra', 'when' => false],
            ['compute' => 'total', 'type' => 'number', 'value' => ['+' => [['var' => 'base'], ['var' => 'extra']]]],
        ]);

        self::assertSame(['total' => 2], new Judge()->judge($logic, ['base' => '2', 'extra' => '40'])->computed);
    }
}
