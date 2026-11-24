<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Domain\Logic;

use Phil\FormLogic\Contract\Expression;
use Phil\FormLogic\Evaluation\Evaluator;
use Phil\FormLogic\Exception\InvalidLogic;
use Phil\FormLogic\Expression\AllOf;
use Phil\FormLogic\JsonLogic\JsonLogicReader;
use Phil\FormLogic\Logic\ComputedVariable;
use Phil\FormLogic\Logic\FieldCondition;
use Phil\FormLogic\Logic\FormLogic;
use Phil\FormLogic\Logic\Judge;
use Phil\FormLogic\Logic\Verdict;
use Phil\FormLogic\Typing\Schema;
use Phil\FormLogic\Typing\TypeChecker;
use Phil\FormLogic\Typing\VariableDefinition;
use Phil\FormLogic\Typing\VariableNames;
use Phil\FormLogic\Typing\VariableType;
use Phil\SurveyBundle\Domain\Exception\InvalidQuestionnaire;
use Phil\SurveyBundle\Domain\Status\QuestionType;
use Phil\SurveyBundle\Domain\ValueObject\Ending;
use Phil\SurveyBundle\Domain\ValueObject\Question;
use Phil\SurveyBundle\Domain\ValueObject\Quota;
use Phil\SurveyBundle\Domain\ValueObject\Section;

/**
 * The logic of a questionnaire, in phil/form-logic: the condition of each question — its section's and its own, both
 * holding —, then the computed variables, such as a score; and the conditions of the endings and the quotas, read
 * after them. Every condition is a structure of JsonLogic, read strictly and checked against the types of the
 * questions when the questionnaire is built:
 *
 * - a question's or a section's condition reads the questions before it whose type the browser reads too — the
 *   browser's controller runs the same steps —, never a computed variable;
 * - an ending's or a quota's condition reads every question — each row of a matrix as `question.row` — and every
 *   computed variable; only the server evaluates it.
 */
final readonly class QuestionnaireLogic
{
    public FormLogic $form;

    /** @var list<array{Ending, ?Expression}> */
    private array $endings;

    /** @var list<array{Quota, Expression}> */
    private array $quotas;

    /**
     * @param list<Section>                                            $sections
     * @param list<array{compute: string, type: string, value: mixed}> $computed
     * @param list<Ending>                                             $endings
     * @param list<Quota>                                              $quotas
     *
     * @throws InvalidQuestionnaire naming the first condition refused
     */
    public function __construct(array $sections, array $computed, array $endings, array $quotas)
    {
        $reader = new JsonLogicReader();
        $checker = new TypeChecker();
        $steps = [];
        $schema = new Schema();
        $readable = [];

        try {
            foreach ($sections as $section) {
                $sectionCondition = null === $section->shownWhen ? null : $reader->parse($section->shownWhen);
                $this->checkReads($sectionCondition, $readable, 'the section "'.$section->name.'"');

                foreach ($section->questions as $question) {
                    $own = null === $question->shownWhen ? null : $reader->parse($question->shownWhen);
                    $this->checkReads($own, $readable, 'the question "'.$question->name.'"');
                    $condition = match (true) {
                        !$own instanceof Expression => $sectionCondition,
                        !$sectionCondition instanceof Expression => $own,
                        default => new AllOf([$sectionCondition, $own]),
                    };

                    if ($condition instanceof Expression) {
                        $steps[] = new FieldCondition($question->name, $condition);
                    }

                    foreach ($this->definitions($question) as $definition) {
                        $schema = $schema->with($definition);
                    }

                    if ($question->type->isReadByConditions()) {
                        $readable[$question->name] = true;
                    }
                }
            }

            $form = FormLogic::fromArray([...array_map(static fn (FieldCondition $step): array => ['field' => $step->field, 'when' => $step->shownWhen->jsonLogic()], $steps), ...$computed], $reader);
            $form->check($schema, $checker);

            foreach ($form->steps as $step) {
                if ($step instanceof ComputedVariable) {
                    $schema = $schema->with(new VariableDefinition($step->name, $step->type));
                }
            }

            $this->form = $form;
            $this->endings = $this->endings($endings, $reader, $checker, $schema);
            $this->quotas = $this->quotas($quotas, $reader, $checker, $schema);
        } catch (InvalidLogic $invalidLogic) {
            throw InvalidQuestionnaire::because(\sprintf('A condition is refused: %s', $invalidLogic->getMessage()));
        }
    }

    /**
     * Judges the answer as the browser collected it: the questions hidden, the answer without them, the computed
     * variables.
     *
     * @param array<string, mixed> $collected
     */
    public function judge(array $collected): Verdict
    {
        return new Judge()->judge($this->form, $collected);
    }

    /**
     * The first ending whose condition holds for the answer and the computed variables; the last always does.
     *
     * @param array<string, mixed> $data
     */
    public function ending(array $data): Ending
    {
        $evaluator = new Evaluator();

        foreach ($this->endings as [$ending, $condition]) {
            if (!$condition instanceof Expression || $evaluator->holds($condition, $data)) {
                return $ending;
            }
        }

        // Unreachable: the questionnaire's last ending has no condition.
        return $this->endings[\count($this->endings) - 1][0];
    }

    /**
     * The quotas whose condition the answer meets, in their order.
     *
     * @param array<string, mixed> $data
     *
     * @return list<Quota>
     */
    public function quotasMet(array $data): array
    {
        $evaluator = new Evaluator();
        $met = [];

        foreach ($this->quotas as [$quota, $condition]) {
            if ($evaluator->holds($condition, $data)) {
                $met[] = $quota;
            }
        }

        return $met;
    }

    /**
     * @param array<string, true> $readable
     */
    private function checkReads(?Expression $condition, array $readable, string $what): void
    {
        if (!$condition instanceof Expression) {
            return;
        }

        foreach (new VariableNames()->of($condition) as $name) {
            if (!isset($readable[explode('.', $name)[0]])) {
                throw InvalidQuestionnaire::because(\sprintf('The condition of %s reads "%s", which is not a question before it the browser reads too.', $what, $name));
            }
        }
    }

    /**
     * @return list<VariableDefinition>
     */
    private function definitions(Question $question): array
    {
        $type = $question->type->variableType();

        if (QuestionType::Matrix === $question->type) {
            return array_map(static fn (string $row): VariableDefinition => new VariableDefinition($question->name.'.'.$row, VariableType::Choice, $question->values()), $question->rowValues());
        }

        if (!$type instanceof VariableType) {
            return [];
        }

        $chooses = VariableType::Choice === $type || VariableType::Choices === $type;

        return [new VariableDefinition($question->name, $type, $chooses ? $question->values() : [])];
    }

    /**
     * @param list<Ending> $endings
     *
     * @return list<array{Ending, ?Expression}>
     */
    private function endings(array $endings, JsonLogicReader $reader, TypeChecker $checker, Schema $schema): array
    {
        $read = [];

        foreach ($endings as $ending) {
            $condition = null === $ending->when ? null : $reader->parse($ending->when);

            if ($condition instanceof Expression) {
                $checker->check($condition, $schema);
            }

            $read[] = [$ending, $condition];
        }

        return $read;
    }

    /**
     * @param list<Quota> $quotas
     *
     * @return list<array{Quota, Expression}>
     */
    private function quotas(array $quotas, JsonLogicReader $reader, TypeChecker $checker, Schema $schema): array
    {
        $read = [];

        foreach ($quotas as $quota) {
            $condition = $reader->parse($quota->when);
            $checker->check($condition, $schema);
            $read[] = [$quota, $condition];
        }

        return $read;
    }
}
