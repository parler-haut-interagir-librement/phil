<?php

declare(strict_types=1);

namespace Phil\AiBundle\Application;

use Phil\AiBundle\Domain\Journal\KeySource;
use Phil\AiBundle\Domain\ValueObject\Cost;
use Phil\AiBundle\Domain\ValueObject\Subject;
use Phil\AiBundle\Domain\ValueObject\Tokens;
use Phil\UsageMeter\Exception\QuotaExceeded;
use Phil\UsageMeter\UsageMeter;

/**
 * The quotas through `phil/usage-meter`:
 *
 * - a meter of tokens, checked before a task for the tokens it reserves and charged after it with those it spent;
 * - a meter of tasks, one consumed per task before it starts and given back when the call fails;
 * - a budget, a meter of the cost in micros of the price list's currency, which must have room left before a task and
 *   is charged with its cost after;
 * - a ceiling, the same meter of cost for the whole application, counted on one subject of its own.
 *
 * Tasks run on the person's own key are not counted unless the configuration says so: they pay their provider.
 */
final readonly class Quota
{
    public const string APPLICATION_SUBJECT = 'phil_ai.application';

    public function __construct(
        private UsageMeter $meter,
        private ?string $tokensMeter = null,
        private ?string $tasksMeter = null,
        private bool $countUserKeys = false,
        private ?string $budgetMeter = null,
        private ?string $ceilingMeter = null,
        private string $ceilingSubject = self::APPLICATION_SUBJECT,
    ) {
    }

    /**
     * @throws QuotaExceeded when the tokens reserved do not fit, no budget is left to the subject or to the application,
     *                       or no task is left; nothing was counted
     */
    public function admit(Subject $subject, KeySource $source, int $reserve, string $taskId): void
    {
        if (!$this->counts($source)) {
            return;
        }

        $this->check($subject->value, $this->tokensMeter, $reserve);
        $this->check($subject->value, $this->budgetMeter, 1);
        $this->check($this->ceilingSubject, $this->ceilingMeter, 1);

        if (null !== $this->tasksMeter) {
            $this->meter->consume($subject->value, $this->tasksMeter, 1, $taskId);
        }
    }

    /**
     * Gives back the task consumed by `admit()`, when the call to the model failed: a failure costs no task.
     */
    public function release(Subject $subject, KeySource $source, string $taskId): void
    {
        if (null === $this->tasksMeter || !$this->counts($source)) {
            return;
        }

        $this->meter->refund($subject->value, $this->tasksMeter, $taskId);
    }

    public function charge(Subject $subject, KeySource $source, Tokens $tokens, Cost $cost, string $taskId): void
    {
        if (!$this->counts($source)) {
            return;
        }

        $this->record($subject->value, $this->tokensMeter, $tokens->total(), $taskId);
        $this->record($subject->value, $this->budgetMeter, $cost->micros, $taskId);
        $this->record($this->ceilingSubject, $this->ceilingMeter, $cost->micros, $taskId);
    }

    private function check(string $subject, ?string $meter, int $amount): void
    {
        if (null === $meter) {
            return;
        }

        $usage = $this->meter->usage($subject, $meter);

        if (!$usage->allows($amount)) {
            throw QuotaExceeded::by($usage, $amount);
        }
    }

    private function record(string $subject, ?string $meter, int $amount, string $taskId): void
    {
        if (null !== $meter && $amount > 0) {
            $this->meter->record($subject, $meter, $amount, $taskId);
        }
    }

    private function counts(KeySource $source): bool
    {
        return $this->countUserKeys || KeySource::Application === $source;
    }
}
