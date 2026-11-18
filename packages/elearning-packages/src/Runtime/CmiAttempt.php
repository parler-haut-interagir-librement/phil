<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Runtime;

/**
 * The platform's part of an attempt, around the content's: what it sets when a session begins, and what it decides
 * when the session ends.
 *
 * When a session begins, the learner, the credit, the mode, the entry and the values of the package are set, and the
 * exit and session time of the session before are forgotten.
 *
 * When it ends:
 * - the session time is added to the total time of the attempt;
 * - in SCORM 1.2, a status the content never set becomes `completed`, and with a mastery score and a raw score in
 *   credit mode, the status becomes `passed` or `failed` by the score, as SCORM 1.2 asks of the platform;
 * - an exit of `suspend` suspends the attempt, to resume it; otherwise SCORM 2004 ends the attempt, and SCORM 1.2 ends
 *   it once it is completed, passed or failed, a session that leaves it incomplete going on with the same data.
 */
final class CmiAttempt
{
    private function __construct()
    {
    }

    public static function begin(CmiData $data, LaunchSettings $settings, Entry $entry): void
    {
        $version = $data->version;
        $data->forget($version->core('exit'));
        $data->forget($version->core('session_time'));
        $data->put(ScormVersion::Scorm12 === $version ? 'cmi.core.student_id' : 'cmi.learner_id', $settings->learnerId);
        $data->put(ScormVersion::Scorm12 === $version ? 'cmi.core.student_name' : 'cmi.learner_name', $settings->learnerName);
        $data->put($version->core('credit'), $settings->credit ? 'credit' : 'no-credit');
        $data->put(ScormVersion::Scorm12 === $version ? 'cmi.core.lesson_mode' : 'cmi.mode', $settings->mode);
        $data->put($version->core('entry'), $entry->value);

        if (null !== $settings->launchData) {
            $data->put('cmi.launch_data', mb_substr($settings->launchData, 0, ScormVersion::Scorm12 === $version ? 4096 : 4000));
        }

        if (ScormVersion::Scorm12 === $version) {
            self::begin12($data, $settings);

            return;
        }

        self::begin2004($data, $settings);
    }

    public static function end(CmiData $data): SessionEnd
    {
        $version = $data->version;
        $session = Duration::parse($version, $data->value($version->core('session_time')) ?? '') ?? Duration::zero();
        $total = (Duration::parse($version, $data->value($version->core('total_time')) ?? '') ?? Duration::zero())->plus($session);
        $data->put($version->core('total_time'), $total->format($version));
        $exit = $data->value($version->core('exit')) ?? '';

        if (ScormVersion::Scorm12 === $version) {
            self::end12($data);
        }

        $status = $data->status();
        $suspended = 'suspend' === $exit;
        $ended = !$suspended && (ScormVersion::Scorm2004 === $version || $status->isCompleted());
        $navigation = $data->value('adl.nav.request');

        return new SessionEnd($status, $total, $suspended, $ended, null === $navigation || '_none_' === $navigation ? null : $navigation);
    }

    private static function begin12(CmiData $data, LaunchSettings $settings): void
    {
        if (null === $data->value('cmi.core.lesson_status')) {
            $data->put('cmi.core.lesson_status', 'not attempted');
        }

        if (null !== $settings->masteryScore) {
            $data->put('cmi.student_data.mastery_score', self::number($settings->masteryScore * 100));
        }

        $time = null === $settings->maxTimeAllowed ? null : Duration::parse(ScormVersion::Scorm12, $settings->maxTimeAllowed);

        if ($time instanceof Duration) {
            $data->put('cmi.student_data.max_time_allowed', $time->timespan());
        }

        if (null !== $settings->timeLimitAction) {
            $data->put('cmi.student_data.time_limit_action', $settings->timeLimitAction);
        }
    }

    private static function begin2004(CmiData $data, LaunchSettings $settings): void
    {
        if (null !== $settings->masteryScore) {
            $data->put('cmi.scaled_passing_score', self::number($settings->masteryScore));
        }

        if (null !== $settings->completionThreshold) {
            $data->put('cmi.completion_threshold', self::number($settings->completionThreshold));
        }

        $time = null === $settings->maxTimeAllowed ? null : Duration::parse(ScormVersion::Scorm2004, $settings->maxTimeAllowed);

        if ($time instanceof Duration) {
            $data->put('cmi.max_time_allowed', $time->interval());
        }

        if (null !== $settings->timeLimitAction) {
            $data->put('cmi.time_limit_action', $settings->timeLimitAction);
        }

        $data->forget('adl.nav.request');
    }

    private static function end12(CmiData $data): void
    {
        $status = $data->value('cmi.core.lesson_status');

        if (null === $status || 'not attempted' === $status) {
            $data->put('cmi.core.lesson_status', 'completed');
        }

        $mastery = $data->value('cmi.student_data.mastery_score');
        $raw = $data->value('cmi.core.score.raw');

        if ('credit' === $data->value('cmi.core.credit') && null !== $mastery && '' !== $mastery && null !== $raw && '' !== $raw && is_numeric($mastery) && is_numeric($raw)) {
            $data->put('cmi.core.lesson_status', (float) $raw >= (float) $mastery ? 'passed' : 'failed');
        }
    }

    private static function number(float $value): string
    {
        return rtrim(rtrim(\sprintf('%.7F', $value), '0'), '.');
    }
}
