<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Application;

use Phil\SchedulingPollBundle\Domain\Availability;
use Phil\SchedulingPollBundle\Domain\Exception\InvalidSchedulingPoll;

/**
 * Reads what the answer form posts: `answers[<slot>]` set to `yes`, `if_need_be` or `no` for each date, and `timezone`,
 * the time zone the respondent reads the dates in. Anything else than what the form sends is refused.
 */
final class AnswerForm
{
    /**
     * @param array<array-key, mixed> $fields the fields posted, as `Request::$request->all()` gives them
     *
     * @return array<string, Availability> the answers by slot
     *
     * @throws InvalidSchedulingPoll when the answers are not a map of slots to one of the three answers
     */
    public static function answers(array $fields): array
    {
        $posted = $fields['answers'] ?? [];

        if (!\is_array($posted) || \count($posted) > SchedulingPolls::MAX_SLOTS) {
            throw InvalidSchedulingPoll::because('The answers are a map of dates to "yes", "if_need_be" or "no".');
        }

        $answers = [];

        foreach ($posted as $slot => $answer) {
            $availability = \is_string($answer) ? Availability::tryFrom($answer) : null;

            if (!\is_string($slot) || !$availability instanceof Availability) {
                throw InvalidSchedulingPoll::because('The answers are a map of dates to "yes", "if_need_be" or "no".');
            }

            $answers[$slot] = $availability;
        }

        return $answers;
    }

    /**
     * The time zone posted, `null` when none is.
     *
     * @param array<array-key, mixed> $fields
     */
    public static function timezone(array $fields): ?string
    {
        $timezone = $fields['timezone'] ?? null;

        return \is_string($timezone) && '' !== $timezone ? $timezone : null;
    }
}
