<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Import;

use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\CoursesBundle\Domain\ValueObject\Outcome;
use Phil\CoursesBundle\Domain\ValueObject\Score;

/**
 * Reads a result exported from another platform in the words of SCORM: the `lesson_status` of SCORM 1.2 — `passed`,
 * `completed`, `failed`, `incomplete`, `browsed`, `not attempted` —, or the `completion_status` and `success_status`
 * of SCORM 2004 joined by `+`, such as `completed+passed`; and a score out of a hundred.
 */
final class ScormStatus
{
    private function __construct()
    {
    }

    /**
     * @throws InvalidCourse when a word or the score is not one SCORM writes
     */
    public static function outcome(string $status, ?string $score = null): Outcome
    {
        $completed = false;
        $success = null;

        foreach (explode('+', strtolower($status)) as $word) {
            match (trim($word)) {
                'completed' => $completed = true,
                'passed' => $success = true,
                'failed' => $success = false,
                'incomplete', 'browsed', 'not attempted', 'not_attempted', 'unknown', '' => null,
                default => throw InvalidCourse::because(\sprintf('"%s" is not a status SCORM writes.', mb_substr($word, 0, 40))),
            };
        }

        return new Outcome($completed, $success, self::score($score));
    }

    private static function score(?string $score): ?Score
    {
        $score = trim((string) $score);

        if ('' === $score) {
            return null;
        }

        if (!is_numeric($score)) {
            throw InvalidCourse::because('A score is a number out of a hundred.');
        }

        return Score::percent((float) $score);
    }
}
