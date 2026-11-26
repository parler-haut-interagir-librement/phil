<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Scorm;

use DateTimeImmutable;
use Phil\CoursesBundle\Application\ActivityType\ScormActivity;
use Phil\CoursesBundle\Application\Enrollments;
use Phil\CoursesBundle\Domain\Contract\CourseRepository;
use Phil\CoursesBundle\Domain\Contract\EnrollmentRepository;
use Phil\CoursesBundle\Domain\Contract\ScormAttemptRepository;
use Phil\CoursesBundle\Domain\Event\ScormSessionEnded;
use Phil\CoursesBundle\Domain\Exception\ActivityUnavailable;
use Phil\CoursesBundle\Domain\Exception\AttemptRefused;
use Phil\CoursesBundle\Domain\Model\Activity;
use Phil\CoursesBundle\Domain\Model\Enrollment;
use Phil\CoursesBundle\Domain\Model\ScormAttempt;
use Phil\CoursesBundle\Domain\Scorm\AttemptState;
use Phil\CoursesBundle\Domain\ValueObject\ActivityId;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\CoursesBundle\Domain\ValueObject\Outcome;
use Phil\CoursesBundle\Domain\ValueObject\Score;
use Phil\CoursesBundle\Domain\ValueObject\ScormAttemptId;
use Phil\ElearningPackages\Exception\ElearningPackagesException;
use Phil\ElearningPackages\PackageFormat;
use Phil\ElearningPackages\Runtime\CmiAttempt;
use Phil\ElearningPackages\Runtime\CmiCommit;
use Phil\ElearningPackages\Runtime\CmiData;
use Phil\ElearningPackages\Runtime\DataModel;
use Phil\ElearningPackages\Runtime\Entry;
use Phil\ElearningPackages\Runtime\LaunchSettings;
use Phil\ElearningPackages\Runtime\RuntimeStatus;
use Phil\ElearningPackages\Runtime\ScormVersion;
use Phil\ElearningPackages\Runtime\SessionEnd;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * The SCORM run time of the platform: the attempts of a learner at a SCORM activity, their sessions and their tracking
 * data, checked by the data model of phil/elearning-packages.
 *
 * At launch, the learner resumes a suspended attempt (`cmi.entry` is `resume`), goes on with an attempt of SCORM 1.2
 * neither ended nor suspended (`cmi.entry` is empty), or begins a new attempt (`ab-initio`) after an ended one; a
 * session left running — a page closed before it could say so — is ended first. Each launch opens the next session of
 * its attempt, and only that session commits.
 *
 * A commit keeps the values the data model accepts. Once the content reports the activity completed or passed, the
 * learner's progress records it; the end of a session records the whole outcome — completed, passed or failed, the
 * score —, a failure counted once the attempt ends, so that it uses one of the activity's tries. A session in browse
 * or review mode, or without credit, records nothing.
 *
 * An ended attempt opens again for review: the content reads its data in review mode, without credit, and nothing it
 * writes is kept — no session is opened, the token is empty, and a commit to an ended attempt is refused.
 *
 * Under a time limit, the platform counts the seconds the sessions of an attempt run by its own clock, and ends the
 * attempt at the deadline: the commit that reaches it ends the session with the exit `time-out`, a commit later than
 * `GRACE` seconds past it is not kept, and a session left running past it, or an attempt resumed past it, ends there.
 * An attempt ended by its time and neither completed nor passed counts as failed.
 */
final readonly class ScormRuntime
{
    /** The seconds after the deadline a commit is still kept, for the time it takes to reach the server. */
    public const int GRACE = 30;

    /** The most activities of a course a session names as targets of a navigation request. */
    public const int MAX_TARGETS = 500;

    public function __construct(
        private ScormAttemptRepository $attempts,
        private CourseRepository $courses,
        private EnrollmentRepository $enrollments,
        private Enrollments $progress,
        private ScormTokens $tokens,
        private ClockInterface $clock,
        private ?EventDispatcherInterface $events = null,
    ) {
    }

    /**
     * Opens a session of the activity for the learner.
     *
     * @throws ActivityUnavailable when the activity does not name a version of SCORM
     */
    public function launch(Activity $activity, Enrollment $enrollment): ScormLaunch
    {
        $version = $this->version($activity);
        $limit = TimeLimit::of($activity, $version);
        $now = $this->clock->now();
        $latest = $this->attempts->latest($enrollment->id, $activity->id);

        if ($latest instanceof ScormAttempt && AttemptState::Running === $latest->state) {
            // A session closed without a word ran until its last commit.
            $this->endSession($latest, new CmiData($latest->version, $latest->data), $enrollment, $activity, $this->late($limit, $latest, $latest->updatedAt), $latest->updatedAt);
        }

        if ($latest instanceof ScormAttempt && AttemptState::Ended !== $latest->state && $this->late($limit, $latest, $now)) {
            $this->expire($latest, $enrollment, $activity);
        }

        [$attempt, $entry] = match ($latest?->state) {
            AttemptState::Suspended => [$latest, Entry::Resume],
            AttemptState::Paused => [$latest, Entry::Other],
            null => [new ScormAttempt(ScormAttemptId::generate(), $enrollment->id, $activity->id, 1, $version, $now), Entry::AbInitio],
            default => [new ScormAttempt(ScormAttemptId::generate(), $enrollment->id, $activity->id, $latest->number + 1, $version, $now), Entry::AbInitio],
        };

        $data = $this->begin($activity, $enrollment, $attempt, $limit, $entry, 'normal');
        $session = $attempt->open($data->values(), $now);
        $this->attempts->save($attempt);

        return new ScormLaunch($attempt->id, $attempt->number, $this->tokens->issue($attempt->id, $session), $attempt->version, $data->values() + $this->targets($activity, $attempt->version), DataModel::describe($attempt->version), Entry::Resume === $entry, remaining: $limit?->remaining($attempt, $now));
    }

    /**
     * Opens the learner's last ended attempt at the activity for review: its data in review mode, without credit, and
     * an empty token, so that nothing the content writes is kept.
     *
     * @throws ActivityUnavailable when the activity names no version of SCORM, or the learner has no ended attempt
     */
    public function review(Activity $activity, Enrollment $enrollment): ScormLaunch
    {
        $ended = null;

        foreach ($this->attempts->ofEnrollments($enrollment->id) as $attempt) {
            if (AttemptState::Ended === $attempt->state && $attempt->activity->equals($activity->id)) {
                $ended = $attempt;
            }
        }

        if (!$ended instanceof ScormAttempt) {
            throw ActivityUnavailable::because('This activity has no ended attempt to review.');
        }

        $data = $this->begin($activity, $enrollment, $ended, TimeLimit::of($activity, $ended->version), Entry::Other, 'review');

        return new ScormLaunch($ended->id, $ended->number, '', $ended->version, $data->values() + $this->targets($activity, $ended->version), DataModel::describe($ended->version), review: true);
    }

    /**
     * The activities of the enrollment that have an ended attempt to review, by identifier.
     *
     * @return list<string>
     */
    public function reviewable(Enrollment $enrollment): array
    {
        $activities = [];

        foreach ($this->attempts->ofEnrollments($enrollment->id) as $attempt) {
            if (AttemptState::Ended === $attempt->state) {
                $activities[$attempt->activity->value] = true;
            }
        }

        return array_keys($activities);
    }

    /**
     * Keeps a commit of a session, and ends the session when `finish` says so.
     *
     * @param array<array-key, mixed> $values the elements the content wrote since the last commit, in their order
     *
     * @throws AttemptRefused when the token, the learner or the session is not the attempt's
     */
    public function commit(string $token, array $values, bool $finish, ?LearnerId $current = null): CommitAnswer
    {
        [$id, $session] = $this->tokens->verify($token);
        $attempt = $this->attempts->find($id) ?? throw AttemptRefused::because('The attempt of this token is unknown.');
        $enrollment = $this->enrollments->find($attempt->enrollment) ?? throw AttemptRefused::because('The attempt of this token is unknown.');
        $activity = $this->courses->activity($attempt->activity) ?? throw AttemptRefused::because('The attempt of this token is unknown.');

        if ($current instanceof LearnerId && !$current->equals($enrollment->learner)) {
            throw AttemptRefused::because("This attempt is another learner's.");
        }

        if (AttemptState::Ended === $attempt->state) {
            throw AttemptRefused::ended();
        }

        if (!$attempt->accepts($session)) {
            throw AttemptRefused::closed();
        }

        $now = $this->clock->now();
        $deadline = TimeLimit::of($activity, $attempt->version)?->deadline;
        $spent = $attempt->spent($now);
        $data = new CmiData($attempt->version, $attempt->data);

        if (null !== $deadline && $spent > $deadline + self::GRACE) {
            $this->endSession($attempt, $data, $enrollment, $activity, true, $now);

            return new CommitAnswer(ended: true, remaining: $deadline - $spent, timeUp: true);
        }

        $errors = CmiCommit::apply($data, $values);
        $timeUp = null !== $deadline && $spent >= $deadline;
        $remaining = null === $deadline ? null : $deadline - $spent;

        if ($finish || $timeUp) {
            $answer = $this->endSession($attempt, $data, $enrollment, $activity, $timeUp, $now);

            return new CommitAnswer($errors, null, true, $answer[0], $answer[1], $remaining, $timeUp);
        }

        $before = $attempt->status;
        $status = $data->status();
        $attempt->commit($data->values(), $status, $now);
        $this->attempts->save($attempt);

        if ($this->credited($data) && ($status->isCompleted() && !$before->isCompleted() || true === $status->success() && true !== $before->success())) {
            $this->progress->record($enrollment, $activity, new Outcome($status->isCompleted(), true === $status->success() ? true : null, $this->score($status)));
        }

        return new CommitAnswer($errors, $this->tokens->issue($attempt->id, $session), remaining: $remaining);
    }

    /**
     * Ends the running session of an attempt and records its outcome; at the deadline, the session ends with the exit
     * `time-out` and the attempt with it.
     *
     * @return array{0: ?ActivityId, 1: bool} the activity a navigation request goes to, and whether it leaves for the outline
     */
    private function endSession(ScormAttempt $attempt, CmiData $data, Enrollment $enrollment, Activity $activity, bool $timeUp, DateTimeImmutable $at): array
    {
        if ($timeUp) {
            $data->put($attempt->version->core('exit'), 'time-out');
            $data->forget('adl.nav.request');
        }

        $end = CmiAttempt::end($data);

        if ($timeUp) {
            $end = new SessionEnd($end->status, $end->totalTime, false, true);
        }

        $attempt->end($data->values(), $end, $at);
        $this->attempts->save($attempt);

        $status = $end->status;
        $success = $status->success();
        $outcome = new Outcome($status->isCompleted(), match (true) {
            false === $success && !$end->attemptEnded => null,
            $timeUp && null === $success && !$status->isCompleted() => false,
            default => $success,
        }, $this->score($status));

        if ($this->credited($data) && (!$outcome->isEmpty() || $outcome->score instanceof Score)) {
            $this->progress->record($enrollment, $activity, $outcome);
        }

        $this->events?->dispatch(new ScormSessionEnded($enrollment->id->value, $activity->id->value, $attempt->id->value, $end->attemptEnded));

        return $this->navigation($activity, $end->navigation);
    }

    /**
     * Ends an attempt between two sessions whose time is spent: neither completed nor passed, it counts as failed.
     */
    private function expire(ScormAttempt $attempt, Enrollment $enrollment, Activity $activity): void
    {
        $attempt->expire($this->clock->now());
        $this->attempts->save($attempt);
        $status = $attempt->status;

        if ($this->credited(new CmiData($attempt->version, $attempt->data))) {
            $this->progress->record($enrollment, $activity, new Outcome($status->isCompleted(), $status->success() ?? ($status->isCompleted() ? null : false), $this->score($status)));
        }
    }

    /**
     * Whether the attempt ran past its deadline at that moment.
     */
    private function late(?TimeLimit $limit, ScormAttempt $attempt, DateTimeImmutable $at): bool
    {
        return null !== $limit?->deadline && $attempt->spent($at) >= $limit->deadline;
    }

    /**
     * The data a session begins with: the attempt's, with what the platform sets.
     */
    private function begin(Activity $activity, Enrollment $enrollment, ScormAttempt $attempt, ?TimeLimit $limit, Entry $entry, string $mode): CmiData
    {
        $data = new CmiData($attempt->version, $attempt->data);
        CmiAttempt::begin($data, $this->settings($activity, $enrollment->learner, $attempt->version, $limit, $mode), $entry);

        if (ScormVersion::Scorm2004 === $attempt->version) {
            [$previous, $next] = $this->neighbours($activity);
            $data->put('adl.nav.request_valid.continue', $next instanceof Activity ? 'true' : 'false');
            $data->put('adl.nav.request_valid.previous', $previous instanceof Activity ? 'true' : 'false');
        }

        return $data;
    }

    /**
     * Whether a choice or a jump to a target is valid, as the content reads it: `true` for each item an activity
     * of the course was imported from, `false` for any other. Handed to the browser with the session, never kept with
     * the attempt.
     *
     * @return array<string, string>
     */
    private function targets(Activity $activity, ScormVersion $version): array
    {
        if (ScormVersion::Scorm2004 !== $version) {
            return [];
        }

        $targets = ['adl.nav.request_valid.choice' => 'false', 'adl.nav.request_valid.jump' => 'false'];
        $named = 0;

        foreach ($this->courses->activities($activity->course) as $candidate) {
            if ($named >= self::MAX_TARGETS) {
                break;
            }

            if (null === $candidate->source || 1 !== preg_match('/^[^\s{}]{1,200}$/D', $candidate->source)) {
                continue;
            }

            $targets['adl.nav.request_valid.choice.{target='.$candidate->source.'}'] = 'true';
            $targets['adl.nav.request_valid.jump.{target='.$candidate->source.'}'] = 'true';
            ++$named;
        }

        return $targets;
    }

    /**
     * @return array{0: ?ActivityId, 1: bool}
     */
    private function navigation(Activity $activity, ?string $request): array
    {
        if (null === $request) {
            return [null, false];
        }

        [$previous, $next] = $this->neighbours($activity);

        if ('continue' === $request || 'previous' === $request) {
            $target = 'continue' === $request ? $next : $previous;

            return [$target?->id, !$target instanceof Activity];
        }

        if (1 === preg_match('/^\{target=([^\s{}]+)\}(?:choice|jump)$/D', $request, $match)) {
            foreach ($this->courses->activities($activity->course) as $candidate) {
                if ($match[1] === $candidate->source) {
                    return [$candidate->id, false];
                }
            }
        }

        return [null, true];
    }

    /**
     * The SCORM activities before and after this one in the outline.
     *
     * @return array{0: ?Activity, 1: ?Activity}
     */
    private function neighbours(Activity $activity): array
    {
        $scorm = array_values(array_filter($this->courses->activities($activity->course), static fn (Activity $candidate): bool => ScormActivity::NAME === $candidate->type));
        $previous = null;

        foreach ($scorm as $position => $candidate) {
            if ($candidate->id->equals($activity->id)) {
                return [$previous, $scorm[$position + 1] ?? null];
            }

            $previous = $candidate;
        }

        return [null, null];
    }

    private function settings(Activity $activity, LearnerId $learner, ScormVersion $version, ?TimeLimit $limit, string $mode): LaunchSettings
    {
        $mastery = $activity->setting(ScormVersion::Scorm12 === $version ? 'mastery_score' : 'scaled_passing_score');
        $threshold = $activity->setting('completion_threshold');

        try {
            return new LaunchSettings(
                $learner->value,
                credit: 'normal' === $mode,
                mode: $mode,
                launchData: $activity->stringSetting('launch_data'),
                masteryScore: is_numeric($mastery) ? max(0.0, min(1.0, (float) $mastery)) : null,
                completionThreshold: is_numeric($threshold) ? max(0.0, min(1.0, (float) $threshold)) : null,
                maxTimeAllowed: $limit?->duration($version),
                timeLimitAction: $limit->action ?? $activity->stringSetting('time_limit_action'),
            );
        } catch (ElearningPackagesException $elearningPackagesException) {
            throw ActivityUnavailable::because($elearningPackagesException->getMessage());
        }
    }

    private function credited(CmiData $data): bool
    {
        $core = ScormVersion::Scorm12 === $data->version;

        return 'no-credit' !== $data->value($core ? 'cmi.core.credit' : 'cmi.credit') && 'normal' === ($data->value($core ? 'cmi.core.lesson_mode' : 'cmi.mode') ?? 'normal');
    }

    private function version(Activity $activity): ScormVersion
    {
        $format = PackageFormat::tryFrom($activity->stringSetting('scorm_version') ?? '');

        return match ($format) {
            PackageFormat::Scorm12 => ScormVersion::Scorm12,
            PackageFormat::Scorm2004 => ScormVersion::Scorm2004,
            default => throw ActivityUnavailable::because(\sprintf('The SCORM activity "%s" names no version of SCORM.', $activity->id->value)),
        };
    }

    private function score(RuntimeStatus $status): ?Score
    {
        return null === $status->score ? null : Score::scaled($status->score);
    }
}
