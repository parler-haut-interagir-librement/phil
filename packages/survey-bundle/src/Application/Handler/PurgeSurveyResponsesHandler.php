<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Application\Handler;

use DateInterval;
use Phil\SurveyBundle\Domain\Contract\QuestionnaireRepository;
use Phil\SurveyBundle\Domain\Contract\ResponseRepository;
use Psr\Clock\ClockInterface;

/**
 * Deletes, for each questionnaire with a retention, its responses sent more than that many days ago, with their
 * addenda: a thousand at a time, a hundred times at most a questionnaire and a run, the next run going on. It is
 * idempotent: a second run at the same instant deletes nothing.
 */
final readonly class PurgeSurveyResponsesHandler
{
    public const int BATCH = 1000;

    public const int BATCHES = 100;

    public function __construct(
        private QuestionnaireRepository $questionnaires,
        private ResponseRepository $responses,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return int how many responses were deleted
     */
    public function __invoke(): int
    {
        $now = $this->clock->now();
        $total = 0;
        foreach ($this->questionnaires->retentions() as $key => $days) {
            $before = $now->sub(new DateInterval('P'.$days.'D'));

            for ($round = 0; $round < self::BATCHES; ++$round) {
                $deleted = $this->responses->purgeBefore($key, $before, self::BATCH);
                $total += $deleted;

                if ($deleted < self::BATCH) {
                    break;
                }
            }
        }

        return $total;
    }
}
