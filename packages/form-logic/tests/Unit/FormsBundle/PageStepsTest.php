<?php

declare(strict_types=1);

namespace Phil\FormLogic\Tests\Unit\FormsBundle;

use Phil\FormLogic\FormsBundle\JsonLogicConditions;
use Phil\FormLogic\Logic\FormLogic;
use Phil\FormLogic\Logic\Judge;
use Phil\FormsBundle\Domain\Contract\ConditionEvaluator;
use Phil\FormsBundle\Domain\Field\FieldType;
use Phil\FormsBundle\Domain\Logic\BasicConditions;
use Phil\FormsBundle\Domain\Model\Form;
use Phil\FormsBundle\Domain\ValueObject\Field;
use Phil\FormsBundle\Infrastructure\Form\LogicSteps;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The steps the page of `phil/forms-bundle` gives the controller, read as this library's logic: the fields the browser
 * hides are the ones the bundle does not ask, under either evaluator, a field left empty and a field not asked kept
 * apart.
 */
#[CoversClass(FormLogic::class)]
#[CoversClass(Judge::class)]
final class PageStepsTest extends TestCase
{
    private function form(): Form
    {
        return Form::create('tournament', 'Tournament', [
            Field::of('team', 'Team', FieldType::Choice, choices: ['Blue', 'Green']),
            Field::of('captain', 'Captain', FieldType::Checkbox, shownWhen: ['==' => [['var' => 'team'], 'Blue']]),
            Field::of('armband', 'Armband', FieldType::Text, shownWhen: ['==' => [['var' => 'captain'], true]]),
            Field::of('reason', 'Why not', FieldType::Text, shownWhen: ['!=' => [['var' => 'captain'], true]]),
            Field::of('spare', 'Spare', FieldType::Text, shownWhen: ['!=' => [['var' => 'armband'], 'L']]),
            Field::of('nickname', 'Nickname', FieldType::Text, shownWhen: ['!=' => [['var' => 'team'], 'Green']]),
        ]);
    }

    /**
     * @return iterable<string, array{array<string, string|bool|null>}>
     */
    public static function answers(): iterable
    {
        yield 'nothing' => [['team' => null, 'captain' => false, 'armband' => null, 'reason' => null, 'spare' => null, 'nickname' => null]];
        yield 'green' => [['team' => 'Green', 'captain' => true, 'armband' => 'L', 'reason' => 'x', 'spare' => 'x', 'nickname' => 'x']];
        yield 'blue' => [['team' => 'Blue', 'captain' => false, 'armband' => null, 'reason' => 'x', 'spare' => 'x', 'nickname' => 'x']];
        yield 'captain' => [['team' => 'Blue', 'captain' => true, 'armband' => null, 'reason' => null, 'spare' => 'x', 'nickname' => 'x']];
        yield 'large armband' => [['team' => 'Blue', 'captain' => true, 'armband' => 'L', 'reason' => null, 'spare' => 'x', 'nickname' => null]];
    }

    /**
     * @param array<string, string|bool|null> $answer
     */
    #[DataProvider('answers')]
    public function testTheBrowserHidesTheFieldsTheBundleDoesNotAsk(array $answer): void
    {
        $evaluators = [new BasicConditions(), new JsonLogicConditions()];

        foreach ($evaluators as $evaluator) {
            self::assertSame($this->asked($evaluator, $answer), $this->shown($evaluator, $answer), $evaluator::class);
        }
    }

    public function testAConditionOnAFieldOfAnotherTierHidesItsField(): void
    {
        $form = Form::create('t', 'T', [
            Field::of('meal', 'Meal', FieldType::Text, tiers: ['vip']),
            Field::of('diet', 'Diet', FieldType::Text, shownWhen: ['!=' => [['var' => 'meal'], 'None']]),
        ]);
        $steps = new LogicSteps(new JsonLogicConditions())->of($form->fieldsFor([]));

        self::assertSame(['diet'], new Judge()->judge(FormLogic::fromArray($steps), [])->hidden);
        self::assertSame([], $form->accept(['diet' => 'Vegan'], logic: new JsonLogicConditions()));
    }

    /**
     * @param array<string, string|bool|null> $answer
     *
     * @return list<string>
     */
    private function asked(ConditionEvaluator $evaluator, array $answer): array
    {
        return array_keys($this->form()->accept($answer, logic: $evaluator));
    }

    /**
     * @param array<string, string|bool|null> $answer
     *
     * @return list<string>
     */
    private function shown(ConditionEvaluator $evaluator, array $answer): array
    {
        $form = $this->form();
        $logic = FormLogic::fromArray(new LogicSteps($evaluator)->of($form->fields()));
        $verdict = new Judge()->judge($logic, $answer);
        $shown = [];

        foreach ($form->fields() as $field) {
            if ($verdict->isShown($field->name)) {
                $shown[] = $field->name;
            }
        }

        return $shown;
    }
}
