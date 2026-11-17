<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Cmi5;

/**
 * What satisfies an assignable unit, the values of the `moveOn` attribute of cmi5: its completion, its success, both,
 * either, or nothing at all.
 */
enum MoveOn: string
{
    case Completed = 'Completed';
    case Passed = 'Passed';
    case CompletedAndPassed = 'CompletedAndPassed';
    case CompletedOrPassed = 'CompletedOrPassed';
    /** Satisfied as soon as the learner launches the unit. */
    case NotApplicable = 'NotApplicable';

    /**
     * Whether a unit completed or not, passed, failed or neither (null), is satisfied by this rule.
     */
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
