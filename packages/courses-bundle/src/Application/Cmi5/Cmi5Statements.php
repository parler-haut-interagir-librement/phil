<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Cmi5;

use JsonException;
use Phil\CoursesBundle\Application\Contract\LearningRecordStore;
use Phil\CoursesBundle\Application\Enrollments;
use Phil\CoursesBundle\Domain\Cmi5\Cmi5Verb;
use Phil\CoursesBundle\Domain\Cmi5\SessionState;
use Phil\CoursesBundle\Domain\Contract\CourseRepository;
use Phil\CoursesBundle\Domain\Contract\EnrollmentRepository;
use Phil\CoursesBundle\Domain\Contract\LaunchSessionRepository;
use Phil\CoursesBundle\Domain\Event\StatementRecorded;
use Phil\CoursesBundle\Domain\Exception\StatementRefused;
use Phil\CoursesBundle\Domain\Model\Activity;
use Phil\CoursesBundle\Domain\Model\Enrollment;
use Phil\CoursesBundle\Domain\Model\LaunchSession;
use Phil\CoursesBundle\Domain\ValueObject\Outcome;
use Phil\CoursesBundle\Domain\ValueObject\Score;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Receives the statements of a cmi5 session: each checked against the rules of cmi5, then handed to the learning
 * record store, then applied — `initialized` opens the session, `completed`, `passed` and `failed` move the learner's
 * progress, `terminated` ends the session. After each outcome, the platform issues the `satisfied` statement of each
 * block, then of the course, the learner has satisfied and has none of yet. The body of a request — its size, its
 * JSON, its number of statements, each an object — is checked whole first; its statements are then checked, kept and
 * applied one after the other, each against the state the ones before it left, so that a statement refused, or a store
 * that does not keep it, stops the request with the statements before it kept and applied.
 */
final readonly class Cmi5Statements
{
    public const int MAX_BYTES = 1_048_576;

    public const int MAX_STATEMENTS = 50;

    private const int DEPTH = 32;

    private Cmi5Satisfaction $satisfaction;

    public function __construct(
        private LearningRecordStore $store,
        private LaunchSessionRepository $sessions,
        private EnrollmentRepository $enrollmentStore,
        private CourseRepository $courses,
        private Enrollments $enrollments,
        private Cmi5Settings $settings,
        private ClockInterface $clock,
        private ?EventDispatcherInterface $events = null,
    ) {
        $this->satisfaction = new Cmi5Satisfaction($courses, $enrollmentStore, $this, $clock, $events);
    }

    /**
     * @param ?string $statementId the identifier a PUT request names, which the statement must carry or takes
     *
     * @return list<string> the identifiers of the statements kept
     *
     * @throws StatementRefused when the body is not a statement or a list of them, or one breaks a rule of cmi5
     */
    public function receive(LaunchSession $session, string $body, ?string $statementId = null): array
    {
        $statements = $this->decode($body, $statementId);
        [$enrollment, $activity] = $this->subject($session);
        $ids = [];

        foreach ($statements as $statement) {
            $ids[] = $this->one($session, $enrollment, $activity, $statement);
        }

        return $ids;
    }

    /**
     * Issues a statement of the platform: hands it to the store and tells the dispatcher.
     *
     * @param array<array-key, mixed> $statement
     */
    public function issue(array $statement, string $registration, string $session): void
    {
        $id = \is_string($statement['id'] ?? null) ? $statement['id'] : Uuid::v4()->toRfc4122();
        $statement['id'] = $id;
        $verb = $statement['verb'] ?? null;
        $this->store->store($id, (string) json_encode($statement, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION), $registration);
        $this->events?->dispatch(new StatementRecorded($id, $session, \is_array($verb) && \is_string($verb['id'] ?? null) ? $verb['id'] : ''));
    }

    /**
     * Issues the `satisfied` statements of the blocks, and of the course, the learner satisfied and has none of yet.
     *
     * @param Cmi5Context $context the context of the session that satisfied a unit
     */
    public function satisfy(Cmi5Context $context, Enrollment $enrollment): void
    {
        $this->satisfaction->satisfy($context, $enrollment);
    }

    /**
     * The context the statements of the session agree with.
     */
    public function context(LaunchSession $session): Cmi5Context
    {
        [$enrollment, $activity] = $this->subject($session);

        return Cmi5Context::of($session, $enrollment, $activity, $this->settings->homePage());
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    private function decode(string $body, ?string $statementId): array
    {
        if (\strlen($body) > self::MAX_BYTES) {
            throw StatementRefused::because(\sprintf('A request holds %d bytes of statements at most.', self::MAX_BYTES));
        }

        try {
            $decoded = json_decode($body, true, self::DEPTH, \JSON_THROW_ON_ERROR | \JSON_BIGINT_AS_STRING);
        } catch (JsonException) {
            throw StatementRefused::because('The body is not JSON of a bounded depth.');
        }

        if (!\is_array($decoded) || [] === $decoded) {
            throw StatementRefused::because('The body is a statement, or a list of them.');
        }

        $statements = array_is_list($decoded) ? $decoded : [$decoded];

        if (\count($statements) > self::MAX_STATEMENTS) {
            throw StatementRefused::because(\sprintf('A request holds %d statements at most.', self::MAX_STATEMENTS));
        }

        if (null !== $statementId) {
            if (\count($statements) > 1 || !\is_array($statements[0]) || ($statements[0]['id'] ?? $statementId) !== $statementId) {
                throw StatementRefused::because('A statement put at an identifier is one statement of that identifier.');
            }

            $statements[0]['id'] = $statementId;
        }

        return array_map(static fn (mixed $statement): array => \is_array($statement) ? $statement : throw StatementRefused::because('Each statement is an object.'), $statements);
    }

    /**
     * @return array{0: Enrollment, 1: Activity}
     */
    private function subject(LaunchSession $session): array
    {
        $enrollment = $this->enrollmentStore->find($session->enrollment);
        $activity = $this->courses->activity($session->activity);

        if (!$enrollment instanceof Enrollment || !$activity instanceof Activity) {
            throw StatementRefused::because('The session belongs to an enrollment or an activity that is gone.');
        }

        return [$enrollment, $activity];
    }

    /**
     * @param array<array-key, mixed> $statement
     */
    private function one(LaunchSession $session, Enrollment $enrollment, Activity $activity, array $statement): string
    {
        $context = Cmi5Context::of($session, $enrollment, $activity, $this->settings->homePage());
        $verb = StatementRules::check($statement, $context, $session->state, $this->enrollmentStore->progress($enrollment->id, $activity->id));
        $statement['id'] = \is_string($statement['id'] ?? null) ? $statement['id'] : Uuid::v4()->toRfc4122();
        $this->issue($statement, $enrollment->registration, $session->id->value);

        if ($verb instanceof Cmi5Verb) {
            $this->apply($verb, $session, $enrollment, $activity, $statement, $context);
        }

        return $statement['id'];
    }

    /**
     * @param array<array-key, mixed> $statement
     */
    private function apply(Cmi5Verb $verb, LaunchSession $session, Enrollment $enrollment, Activity $activity, array $statement, Cmi5Context $context): void
    {
        $now = $this->clock->now();

        if (Cmi5Verb::Initialized === $verb || Cmi5Verb::Terminated === $verb) {
            Cmi5Verb::Initialized === $verb ? $session->initialize() : $session->end(SessionState::Terminated, $now);
            $this->sessions->save($session);

            return;
        }

        $score = $this->score($statement);
        $this->enrollments->record($enrollment, $activity, match ($verb) {
            Cmi5Verb::Completed => new Outcome(true, null, $score),
            Cmi5Verb::Passed => Outcome::passed($score),
            default => Outcome::failed($score),
        });
        $this->satisfy($context, $enrollment);
    }

    /**
     * @param array<array-key, mixed> $statement
     */
    private function score(array $statement): ?Score
    {
        $result = \is_array($statement['result'] ?? null) ? $statement['result'] : [];
        $score = \is_array($result['score'] ?? null) ? $result['score'] : [];
        $scaled = $score['scaled'] ?? null;

        return \is_int($scaled) || \is_float($scaled) ? Score::scaled((float) $scaled) : null;
    }
}
