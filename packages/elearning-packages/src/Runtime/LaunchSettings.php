<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Runtime;

use Phil\ElearningPackages\Exception\RuntimeRefused;

/**
 * What the platform hands a SCO when a session begins: the learner, the credit and the mode, and what the package
 * says of the item — its launch data, the mastery score of SCORM 1.2 or the scaled passing score of SCORM 2004, the
 * completion threshold of SCORM 2004, the time allowed and what happens once it is spent.
 */
final readonly class LaunchSettings
{
    public const array MODES = ['normal', 'review', 'browse'];

    public const array TIME_LIMIT_ACTIONS = ['exit,message', 'exit,no message', 'continue,message', 'continue,no message'];

    /**
     * @param ?float  $masteryScore        the score to pass, scaled from 0 to 1 — SCORM 1.2 reads it out of a hundred
     * @param ?float  $completionThreshold the progress measure that completes the SCO, from 0 to 1, in SCORM 2004
     * @param ?string $maxTimeAllowed      the time allowed, `HHHH:MM:SS` or a duration of ISO 8601
     *
     * @throws RuntimeRefused when a value is out of its bounds
     */
    public function __construct(
        public string $learnerId,
        public string $learnerName = '',
        public bool $credit = true,
        public string $mode = 'normal',
        public ?string $launchData = null,
        public ?float $masteryScore = null,
        public ?float $completionThreshold = null,
        public ?string $maxTimeAllowed = null,
        public ?string $timeLimitAction = null,
    ) {
        if ('' === $learnerId || \strlen($learnerId) > 255 || 1 !== preg_match('/^[!-~]+$/D', $learnerId)) {
            throw RuntimeRefused::because('The learner of a SCORM session is named by 1 to 255 printable characters, without a space.');
        }

        if (!\in_array($mode, self::MODES, true)) {
            throw RuntimeRefused::because('The mode of a SCORM session is "normal", "review" or "browse".');
        }

        if (null !== $masteryScore && ($masteryScore < 0.0 || $masteryScore > 1.0) || null !== $completionThreshold && ($completionThreshold < 0.0 || $completionThreshold > 1.0)) {
            throw RuntimeRefused::because('A mastery score and a completion threshold are from 0 to 1.');
        }

        if (null !== $timeLimitAction && !\in_array($timeLimitAction, self::TIME_LIMIT_ACTIONS, true)) {
            throw RuntimeRefused::because('The time limit action is one SCORM names, such as "continue,no message".');
        }
    }
}
