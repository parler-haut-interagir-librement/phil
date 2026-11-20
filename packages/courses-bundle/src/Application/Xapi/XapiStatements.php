<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Xapi;

use Phil\CoursesBundle\Application\ActivityType\XapiActivity;
use Phil\CoursesBundle\Application\Cmi5\Cmi5Settings;
use Phil\CoursesBundle\Application\Contract\LearningRecordStore;
use Phil\CoursesBundle\Application\Enrollments;
use Phil\CoursesBundle\Domain\Contract\CourseRepository;
use Phil\CoursesBundle\Domain\Contract\EnrollmentRepository;
use Phil\CoursesBundle\Domain\Event\StatementRecorded;
use Phil\CoursesBundle\Domain\Exception\StatementRefused;
use Phil\CoursesBundle\Domain\Model\Activity;
use Phil\CoursesBundle\Domain\Model\Enrollment;
use Phil\CoursesBundle\Domain\Model\LaunchSession;
use Phil\CoursesBundle\Domain\ValueObject\Outcome;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Receives the statements of a launch of an xAPI activity: the body is checked whole, then each statement by
 * `XapiRules`, handed to the learning record store under the registration of the enrollment, told to the dispatcher,
 * and applied — the outcome `XapiOutcomes` reads moves the learner's progress. A statement refused, or a store that
 * does not keep it, stops the request with the statements before it kept and applied.
 */
final readonly class XapiStatements
{
    public function __construct(
        private LearningRecordStore $store,
        private EnrollmentRepository $enrollmentStore,
        private CourseRepository $courses,
        private Enrollments $enrollments,
        private Cmi5Settings $settings,
        private ?EventDispatcherInterface $events = null,
    ) {
    }

    /**
     * Whether the session launched an xAPI activity, whose statements this service receives instead of the cmi5 rules.
     */
    public function handles(LaunchSession $session): bool
    {
        return XapiActivity::NAME === $this->courses->activity($session->activity)?->type;
    }

    /**
     * @throws StatementRefused when the session belongs to an enrollment or an activity that is gone
     */
    public function context(LaunchSession $session): XapiContext
    {
        [$enrollment, $activity] = $this->subject($session);

        return XapiContext::of($session, $enrollment, $activity, $this->settings->homePage());
    }

    /**
     * @param ?string $statementId the identifier a PUT request names
     *
     * @return list<string> the identifiers of the statements kept
     *
     * @throws StatementRefused when the body is not a statement or a list of them, or one fails a check
     */
    public function receive(LaunchSession $session, string $body, ?string $statementId = null): array
    {
        $statements = StatementBatch::decode($body, $statementId);
        [$enrollment, $activity] = $this->subject($session);
        $context = XapiContext::of($session, $enrollment, $activity, $this->settings->homePage());
        $ids = [];

        foreach ($statements as $statement) {
            $statement = XapiRules::check($statement, $context);
            $id = \is_string($statement['id'] ?? null) ? $statement['id'] : Uuid::v4()->toRfc4122();
            $statement['id'] = $id;
            $this->store->store($id, (string) json_encode($statement, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION), $context->registration);
            $this->events?->dispatch(new StatementRecorded($id, $session->id->value, XapiOutcomes::verbOf($statement)));
            $outcome = XapiOutcomes::of($statement, $context);

            if ($outcome instanceof Outcome) {
                $this->enrollments->record($enrollment, $activity, $outcome);
            }

            $ids[] = $id;
        }

        return $ids;
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
}
