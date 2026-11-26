<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Xapi;

use Phil\CoursesBundle\Domain\ValueObject\Outcome;
use Phil\CoursesBundle\Domain\ValueObject\Score;
use Phil\CoursesBundle\Domain\Xapi\XapiVerb;

/**
 * The outcome a checked statement reports about the activity launched, or none: `completed` completes it, `passed`
 * passes it and `failed` fails it, each with its scaled score; a `result.completion` or a `result.success` the
 * statement adds is read too. A statement of another verb — `mastered`, `experienced`, a verb of the authoring tool —
 * reports only through its result: `completion` true completes the activity, a boolean `success` passes or fails it.
 * With a mastery score, a scaled score decides success instead: at or above it the learner passed, below it they
 * failed. A statement about another activity, or of another verb without such a result, reports nothing.
 */
final class XapiOutcomes
{
    private function __construct()
    {
    }

    /**
     * @param array<array-key, mixed> $statement
     *
     * @return string the identifier of the verb of the statement, empty when it names none
     */
    public static function verbOf(array $statement): string
    {
        return \is_array($statement['verb'] ?? null) && \is_string($statement['verb']['id'] ?? null) ? $statement['verb']['id'] : '';
    }

    /**
     * @param array<array-key, mixed> $statement a statement `XapiRules` accepted
     */
    public static function of(array $statement, XapiContext $context): ?Outcome
    {
        $object = \is_array($statement['object'] ?? null) ? $statement['object'] : [];
        $verb = XapiVerb::tryFrom(self::verbOf($statement));

        if (($object['id'] ?? null) !== $context->activityIri) {
            return null;
        }

        $result = \is_array($statement['result'] ?? null) ? $statement['result'] : [];
        $score = \is_array($result['score'] ?? null) ? $result['score'] : [];
        $scaled = \is_int($score['scaled'] ?? null) || \is_float($score['scaled'] ?? null) ? $score['scaled'] : null;
        $completed = XapiVerb::Completed === $verb || true === ($result['completion'] ?? null);
        $success = match ($verb) {
            XapiVerb::Passed => true,
            XapiVerb::Failed => false,
            default => \is_bool($result['success'] ?? null) ? $result['success'] : null,
        };

        if (!$completed && null === $success) {
            return null;
        }

        if (null !== $context->masteryScore && null !== $scaled && XapiVerb::Failed !== $verb) {
            $success = $scaled >= $context->masteryScore;
        }

        return new Outcome($completed, $success, null === $scaled ? null : Score::scaled($scaled));
    }
}
