<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Progress;

/**
 * What satisfies an activity, the values of cmi5's `moveOn`: the bundle applies the same rule to every activity type.
 */
enum CompletionRequirement: string
{
    case Completed = 'Completed';
    case Passed = 'Passed';
    case CompletedAndPassed = 'CompletedAndPassed';
    case CompletedOrPassed = 'CompletedOrPassed';
    /** Satisfied as soon as the learner opens it. */
    case NotApplicable = 'NotApplicable';

    public function isMetBy(bool $completed, ?bool $success): bool
    {
        return match ($this) {
            self::Completed => $completed,
            self::Passed => true === $success,
            self::CompletedAndPassed => $completed && true === $success,
            self::CompletedOrPassed => $completed || true === $success,
            self::NotApplicable => true,
        };
    }
}
