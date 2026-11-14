<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Application\Exception;

use Phil\OnboardingBundle\Domain\Exception\OnboardingException;
use RuntimeException;

/**
 * A person asked for what the step does not allow: ticking a step that an event, a checker or an answer completes,
 * skipping a step that cannot be skipped, answering a step without a question, or one no longer pending.
 */
final class StepRefused extends RuntimeException implements OnboardingException
{
    public static function notManual(string $flow, string $step): self
    {
        return new self(\sprintf('The step "%s" of the flow "%s" is not ticked by hand.', $step, $flow));
    }

    public static function notAQuestion(string $flow, string $step): self
    {
        return new self(\sprintf('The step "%s" of the flow "%s" asks no question.', $step, $flow));
    }

    public static function alreadyDone(string $flow, string $step): self
    {
        return new self(\sprintf('The step "%s" of the flow "%s" is no longer pending.', $step, $flow));
    }

    public static function notSkippable(string $flow, string $step): self
    {
        return new self(\sprintf('The step "%s" of the flow "%s" cannot be skipped.', $step, $flow));
    }
}
