<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\FormsBundle\Domain\Contract\ConditionEvaluator;
use Phil\FormsBundle\Domain\Field\FieldType;
use Phil\FormsBundle\Domain\ValueObject\Field;
use Phil\FormsBundle\Infrastructure\Form\LogicSteps;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LogicSteps::class)]
final class LogicStepsTest extends TestCase
{
    public function testAFormWithoutConditionsHasNoSteps(): void
    {
        self::assertSame([], new LogicSteps()->of([Field::of('name', 'Name', FieldType::Text), Field::of('consent', 'Consent', FieldType::Checkbox)]));
        self::assertSame([], new LogicSteps()->of([]));
    }

    public function testEachConditionIsTheStepOfItsField(): void
    {
        $blue = ['==' => [['var' => 'team'], 'Blue']];
        $notAda = ['!=' => [['var' => 'name'], 'Ada']];

        self::assertSame(
            [['field' => 'captain', 'when' => $blue], ['field' => 'nickname', 'when' => $notAda]],
            new LogicSteps()->of([
                Field::of('team', 'Team', FieldType::Choice, choices: ['Blue', 'Green']),
                Field::of('name', 'Name', FieldType::Text),
                Field::of('captain', 'Captain', FieldType::Checkbox, shownWhen: $blue),
                Field::of('nickname', 'Nickname', FieldType::Text, shownWhen: $notAda),
            ]),
        );
    }

    public function testAConditionThatReadsAConditionalFieldRequiresItShown(): void
    {
        $blue = ['==' => [['var' => 'team'], 'Blue']];
        $captain = ['==' => [['var' => 'captain'], true]];
        $notCaptain = ['!=' => [['var' => 'captain'], true]];
        $large = ['==' => [['var' => 'armband'], 'L']];

        self::assertSame(
            [
                ['compute' => '_shown_0', 'type' => 'boolean', 'value' => $blue],
                ['field' => 'captain', 'when' => ['var' => '_shown_0']],
                ['compute' => '_shown_1', 'type' => 'boolean', 'value' => ['and' => [['var' => '_shown_0'], $captain]]],
                ['field' => 'armband', 'when' => ['var' => '_shown_1']],
                ['field' => 'reason', 'when' => ['and' => [['var' => '_shown_0'], $notCaptain]]],
                ['field' => 'spare', 'when' => ['and' => [['var' => '_shown_1'], $large]]],
            ],
            new LogicSteps()->of([
                Field::of('team', 'Team', FieldType::Choice, choices: ['Blue', 'Green']),
                Field::of('captain', 'Captain', FieldType::Checkbox, shownWhen: $blue),
                Field::of('armband', 'Armband', FieldType::Text, shownWhen: $captain),
                Field::of('reason', 'Reason', FieldType::Text, shownWhen: $notCaptain),
                Field::of('spare', 'Spare', FieldType::Text, shownWhen: $large),
            ]),
        );
        self::assertSame('_shown_', LogicSteps::PREFIX);
    }

    public function testAConditionThatReadsAFieldThePageDoesNotShowIsFalse(): void
    {
        $blue = ['==' => [['var' => 'team'], 'Blue']];

        self::assertSame(
            [['field' => 'captain', 'when' => false], ['field' => 'nickname', 'when' => ['!=' => [['var' => 'name'], 'Ada']]]],
            new LogicSteps()->of([
                Field::of('name', 'Name', FieldType::Text),
                Field::of('captain', 'Captain', FieldType::Checkbox, shownWhen: $blue),
                Field::of('nickname', 'Nickname', FieldType::Text, shownWhen: ['!=' => [['var' => 'name'], 'Ada']]),
            ]),
            'the team, for another tier, is not on the page',
        );
    }

    public function testTheFieldsAConditionReadsComeFromTheEvaluator(): void
    {
        $evaluator = new class implements ConditionEvaluator {
            #[Override]
            public function fields(array $condition): array
            {
                return 'start' === array_key_first($condition) ? [] : ['team', 'name'];
            }

            #[Override]
            public function check(array $condition, array $fields): void
            {
            }

            #[Override]
            public function holds(array $condition, array $values): bool
            {
                return true;
            }
        };
        $any = ['or' => [['var' => 'team'], ['var' => 'name']]];
        $start = ['start' => []];

        self::assertSame(
            [
                ['compute' => '_shown_0', 'type' => 'boolean', 'value' => $start],
                ['field' => 'name', 'when' => ['var' => '_shown_0']],
                ['compute' => '_shown_1', 'type' => 'boolean', 'value' => ['and' => [['var' => '_shown_0'], $any]]],
                ['field' => 'team', 'when' => ['var' => '_shown_1']],
                ['field' => 'note', 'when' => ['and' => [['var' => '_shown_1'], ['var' => '_shown_0'], $any]]],
            ],
            new LogicSteps($evaluator)->of([
                Field::of('name', 'Name', FieldType::Text, shownWhen: $start),
                Field::of('team', 'Team', FieldType::Text, shownWhen: $any),
                Field::of('note', 'Note', FieldType::Text, shownWhen: $any),
            ]),
        );
    }
}
