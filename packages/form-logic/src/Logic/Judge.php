<?php

declare(strict_types=1);

namespace Phil\FormLogic\Logic;

use Phil\FormLogic\Evaluation\Evaluator;

/**
 * Judges an answer on the server, whatever the browser showed: each step of the logic in its order, a hidden field's
 * value dropped — so the conditions after it read it as unanswered —, each computed variable added for the steps after
 * it, and a submitted value of the same name dropped: the server computes it, never the browser. The browser's
 * controller only shows and hides; the verdict is what the application validates and stores.
 */
final readonly class Judge
{
    public function __construct(private Evaluator $evaluator = new Evaluator())
    {
    }

    /**
     * @param array<array-key, mixed> $answer the submitted values, by field name
     */
    public function judge(FormLogic $logic, array $answer): Verdict
    {
        $data = $answer;
        $hidden = [];
        $computed = [];

        foreach ($logic->steps as $step) {
            if ($step instanceof ComputedVariable) {
                $computed[$step->name] = $this->evaluator->evaluate($step->expression, $data);
                $data[$step->name] = $computed[$step->name];
                unset($answer[$step->name]);

                continue;
            }

            if (!$this->evaluator->holds($step->shownWhen, $data)) {
                $hidden[] = $step->field;
                unset($answer[$step->field]);
                $data[$step->field] = null;
            }
        }

        return new Verdict($answer, $hidden, $computed);
    }
}
