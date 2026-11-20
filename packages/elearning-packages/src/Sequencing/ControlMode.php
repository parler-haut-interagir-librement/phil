<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Sequencing;

/**
 * The sequencing control modes of an activity, `<imsss:controlMode>`, which govern the navigation among its children:
 * whether the learner may choose one, leave one by choosing elsewhere, move by flow, and only forward; and whether the
 * rollup reads the objective and progress data of the current attempt only.
 */
final readonly class ControlMode
{
    public function __construct(
        public bool $choice = true,
        public bool $choiceExit = true,
        public bool $flow = false,
        public bool $forwardOnly = false,
        public bool $useCurrentAttemptObjectiveInfo = true,
        public bool $useCurrentAttemptProgressInfo = true,
    ) {
    }
}
