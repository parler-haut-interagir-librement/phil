<?php

declare(strict_types=1);

namespace Phil\AiBundle\Application;

use Phil\AiBundle\Application\Exception\TooManyTasks;
use Phil\AiBundle\Domain\Exception\InvalidAiValue;
use Phil\AiBundle\Domain\Journal\KeySource;
use Phil\AiBundle\Domain\ValueObject\Cost;
use Phil\AiBundle\Domain\ValueObject\Subject;
use Phil\AiBundle\Domain\ValueObject\Tokens;
use Phil\UsageMeter\Exception\QuotaExceeded;
use Phil\UsageMeter\UsageMeter;
use Symfony\Component\Semaphore\SemaphoreFactory;
use Symfony\Component\Semaphore\SemaphoreInterface;
use Throwable;

/**
 * The quotas through `phil/usage-meter`, reserved before a task and settled after it:
 *
 * - a meter of tokens, which reserves the task's tokens;
 * - a budget, a meter of the cost in micros of the price list's currency, which reserves the task's cost bound;
 * - a ceiling, the same meter of cost for the whole application, counted on one subject of its own;
 * - a meter of tasks, one consumed per task.
 *
 * `admit()` consumes them atomically, in that order, the task's identifier as idempotency key, all or none: a refusal
 * gives back what was already consumed, then throws. `charge()` settles each meter with what the task spent: the excess
 * over the reservation recorded under the key `<id>:adjust`, the surplus given back. `release()` gives back the whole
 * reservation of a task whose call failed. An optional semaphore bounds how many tasks of one subject run at once.
 *
 * Tasks run on the person's own key are not counted unless the configuration says so: they pay their provider.
 */
final readonly class Quota
{
    public const string APPLICATION_SUBJECT = 'phil_ai.application';

    /**
     * How long a place among the tasks running at once is held at most, in seconds, when the task never ends.
     */
    public const float CONCURRENCY_TTL = 300.0;

    /**
     * The service of the semaphore factory the bundle reads by default, that of FrameworkBundle's `semaphore`.
     */
    public const string SEMAPHORE_FACTORY = 'semaphore.factory';

    /**
     * The start of the name of a subject's semaphore, followed by the XXH128 hash of the subject.
     */
    public const string SEMAPHORE_PREFIX = 'phil_ai.concurrency.';

    private const string ADJUST = ':adjust';

    /**
     * The semaphore factory and how many tasks of one subject run at once, or null for no bound.
     *
     * @var array{SemaphoreFactory, int}|null
     */
    private ?array $places;

    /**
     * @param SemaphoreFactory|null $semaphores  the factory of the semaphore that bounds the tasks running at once
     * @param int|null              $concurrency how many tasks of one subject run at once; null for no bound
     *
     * @throws InvalidAiValue when the bound is below one, or comes without a semaphore factory
     */
    public function __construct(
        private UsageMeter $meter,
        private ?string $tokensMeter = null,
        private ?string $tasksMeter = null,
        private bool $countUserKeys = false,
        private ?string $budgetMeter = null,
        private ?string $ceilingMeter = null,
        private string $ceilingSubject = self::APPLICATION_SUBJECT,
        ?SemaphoreFactory $semaphores = null,
        ?int $concurrency = null,
    ) {
        if (null !== $concurrency && ($concurrency < 1 || !$semaphores instanceof SemaphoreFactory)) {
            throw InvalidAiValue::because(\sprintf('A bound of the tasks running at once is one or more, with a semaphore factory; got %d.', $concurrency));
        }

        $this->places = $semaphores instanceof SemaphoreFactory && null !== $concurrency ? [$semaphores, $concurrency] : null;
    }

    /**
     * Reserves the task on every meter, in this order: its tokens, its cost bound on the subject's budget, the same
     * bound on the application's ceiling, then one task. Without a bound, or with a bound of zero, the budget and the
     * ceiling only need room left.
     *
     * @param int       $reserve the tokens the task reserves
     * @param Cost|null $bound   the most the task should cost, from its reserve and its largest answer
     *
     * @throws QuotaExceeded when one of them does not fit; what was reserved before it is given back, nothing stays
     *                       counted
     */
    public function admit(Subject $subject, KeySource $source, int $reserve, string $taskId, ?Cost $bound = null): void
    {
        if (!$this->counts($source)) {
            return;
        }

        $micros = $bound instanceof Cost ? $bound->micros : 0;
        $steps = [[$subject->value, $this->tokensMeter, $reserve], [$subject->value, $this->budgetMeter, $micros], [$this->ceilingSubject, $this->ceilingMeter, $micros], [$subject->value, $this->tasksMeter, 1]];
        $reserved = [];

        try {
            foreach ($steps as [$owner, $meter, $amount]) {
                if (null === $meter) {
                    continue;
                }

                if (0 === $amount) {
                    $this->check($owner, $meter);

                    continue;
                }

                $this->meter->consume($owner, $meter, $amount, $taskId);
                $reserved[] = [$owner, $meter];
            }
        } catch (Throwable $throwable) {
            foreach ($reserved as [$owner, $meter]) {
                $this->meter->refund($owner, $meter, $taskId);
            }

            throw $throwable;
        }
    }

    /**
     * Gives back everything `admit()` reserved — the task, its tokens and its cost bound —, when the call to the model
     * failed or its stream was left before the end: a failure costs nothing. A second call gives nothing more back.
     */
    public function release(Subject $subject, KeySource $source, string $taskId): void
    {
        if (!$this->counts($source)) {
            return;
        }

        foreach ([[$subject->value, $this->tasksMeter], [$this->ceilingSubject, $this->ceilingMeter], [$subject->value, $this->budgetMeter], [$subject->value, $this->tokensMeter]] as [$owner, $meter]) {
            if (null !== $meter) {
                $this->meter->refund($owner, $meter, $taskId);
            }
        }
    }

    /**
     * Settles the reservation with what the task spent, on the tokens, the budget and the ceiling: the excess is
     * recorded under `<id>:adjust`, over the limit if need be, and the surplus is given back. A second call counts
     * nothing more.
     *
     * @param int       $reserve the tokens `admit()` reserved
     * @param Cost|null $bound   the cost bound `admit()` reserved
     */
    public function charge(Subject $subject, KeySource $source, Tokens $tokens, Cost $cost, string $taskId, int $reserve = 0, ?Cost $bound = null): void
    {
        if (!$this->counts($source)) {
            return;
        }

        $micros = $bound instanceof Cost ? $bound->micros : 0;
        $this->settle($subject->value, $this->tokensMeter, $tokens->total(), $reserve, $taskId);
        $this->settle($subject->value, $this->budgetMeter, $cost->micros, $micros, $taskId);
        $this->settle($this->ceilingSubject, $this->ceilingMeter, $cost->micros, $micros, $taskId);
    }

    /**
     * Takes one of the subject's places among the tasks running at once, held until the semaphore is released, or its
     * time to live ends; null when no bound is configured or the task is not counted.
     *
     * @throws TooManyTasks when every place is taken
     */
    public function enter(Subject $subject, KeySource $source): ?SemaphoreInterface
    {
        if (null === $this->places || !$this->counts($source)) {
            return null;
        }

        [$semaphores, $concurrency] = $this->places;
        // Released by AiTasks when the task ends; the time to live frees a place a process that died never gave back.
        $semaphore = $semaphores->createSemaphore(self::SEMAPHORE_PREFIX.hash('xxh128', $subject->value), $concurrency, 1, self::CONCURRENCY_TTL, false);

        if (!$semaphore->acquire()) {
            throw TooManyTasks::atOnce($concurrency);
        }

        return $semaphore;
    }

    private function check(string $subject, string $meter): void
    {
        $usage = $this->meter->usage($subject, $meter);

        if (!$usage->allows(1)) {
            throw QuotaExceeded::by($usage, 1);
        }
    }

    private function settle(string $subject, ?string $meter, int $spent, int $reserved, string $taskId): void
    {
        if (null === $meter) {
            return;
        }

        if ($spent > $reserved) {
            $this->meter->record($subject, $meter, $spent - $reserved, $taskId.self::ADJUST);
        } elseif ($spent < $reserved) {
            $this->meter->refund($subject, $meter, $taskId, $reserved - $spent);
        }
    }

    private function counts(KeySource $source): bool
    {
        return $this->countUserKeys || KeySource::Application === $source;
    }
}
