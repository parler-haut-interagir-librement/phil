<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Benchmark;

use Phil\CoursesBundle\Application\Cmi5\Cmi5Context;
use Phil\CoursesBundle\Application\Cmi5\StatementRules;
use Phil\CoursesBundle\Domain\Cmi5\Cmi5Verb;
use Phil\CoursesBundle\Domain\Cmi5\SessionState;
use Phil\CoursesBundle\Domain\Model\Enrollment;
use Phil\CoursesBundle\Domain\Progress\CompletionRequirement;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\CoursesBundle\Domain\ValueObject\Outcome;
use Phil\CoursesBundle\Tests\Unit\Fixture\Statements;
use Phil\CoursesBundle\Tests\Unit\Fixture\Store;

/**
 * What the platform pays on its hot paths: checking a statement an assignable unit sends against the rules of cmi5,
 * and placing every activity of a course of ten modules of ten activities for a learner half-way through it.
 */
final readonly class CoursesBench
{
    private Cmi5Context $context;

    /** @var array<string, mixed> */
    private array $statement;

    private Store $store;

    private Enrollment $enrollment;

    public function __construct()
    {
        $this->context = new Cmi5Context('https://learn.example.org/', 'ada', 'https://learn.example.org/courses/activities/a1', 'https://example.org/au', 'https://learn.example.org/courses/c1', 'r-1', 's-1', CompletionRequirement::CompletedAndPassed, 0.8);
        $this->statement = Statements::passed($this->context, 0.9);
        $this->store = new Store();
        $course = $this->store->courses->create('Bench', '', true);

        for ($module = 0; $module < 10; ++$module) {
            $id = $this->store->courses->addModule($course->id, 'Module '.$module)->id;

            for ($activity = 0; $activity < 10; ++$activity) {
                $this->store->courses->addActivity($id, 'Activity '.$activity, 'page');
            }
        }

        $this->enrollment = $this->store->enrollments->enroll($course->id, LearnerId::of('ada'));

        foreach (\array_slice($this->store->courses->outline($course->id)->activities(), 0, 50) as $activity) {
            $this->store->enrollments->record($this->enrollment, $activity, Outcome::completed());
        }
    }

    public function benchCheckAStatement(): void
    {
        StatementRules::check($this->statement, $this->context, SessionState::Initialized, null);
    }

    public function benchCheckAStatementOfAnotherVerb(): void
    {
        StatementRules::check(Statements::of($this->context, Cmi5Verb::Initialized), $this->context, SessionState::Launched, null);
    }

    public function benchPlaceTheActivitiesOfACourse(): void
    {
        $this->store->availabilities->outline($this->enrollment);
    }
}
