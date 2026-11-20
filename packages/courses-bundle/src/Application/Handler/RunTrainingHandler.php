<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Handler;

use Phil\CoursesBundle\Application\Training\TrainingReport;
use Phil\CoursesBundle\Application\Training\TrainingRun;

/**
 * Runs the mandatory training when the schedule, or the application, sends `RunTraining`.
 */
final readonly class RunTrainingHandler
{
    public function __construct(
        private TrainingRun $run,
    ) {
    }

    public function __invoke(): TrainingReport
    {
        return $this->run->run();
    }
}
