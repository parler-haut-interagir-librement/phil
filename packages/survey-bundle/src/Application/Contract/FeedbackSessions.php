<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Application\Contract;

use DateTimeImmutable;

/**
 * The sessions whose feedback the application collects: when each starts and ends. The feedback of a session opens
 * the questionnaire's `opens_before` minutes before it starts and closes `closes_after` minutes after it ends.
 */
interface FeedbackSessions
{
    /**
     * @return array{DateTimeImmutable, DateTimeImmutable}|null the start and the end of the session, or null when the
     *                                                          application knows no such subject
     */
    public function session(string $subject): ?array;
}
