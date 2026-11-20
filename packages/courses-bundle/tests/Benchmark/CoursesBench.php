<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Benchmark;

use Phil\CoursesBundle\Application\Cmi5\Cmi5Context;
use Phil\CoursesBundle\Application\Cmi5\StatementRules;
use Phil\CoursesBundle\Application\Xapi\XapiContext;
use Phil\CoursesBundle\Application\Xapi\XapiOutcomes;
use Phil\CoursesBundle\Application\Xapi\XapiRules;
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
 * checking a statement of an xAPI activity and reading its outcome,
 * placing every activity of a course of ten modules of ten activities for a learner half-way through it, and the
 * commit of a SCORM session — the token checked, the attempt read, the values checked and kept — whose attempt holds
 * fifty interactions already.
 */
final readonly class CoursesBench
{
    private Cmi5Context $context;

    /** @var array<string, mixed> */
    private array $statement;

    private XapiContext $xapi;

    /** @var array<string, mixed> */
    private array $xapiStatement;

    private Store $store;

    private Enrollment $enrollment;

    private string $token;

    /** @var array<string, string> */
    private array $commit;

    public function __construct()
    {
        $this->context = new Cmi5Context('https://learn.example.org/', 'ada', 'https://learn.example.org/courses/activities/a1', 'https://example.org/au', 'https://learn.example.org/courses/c1', 'r-1', 's-1', CompletionRequirement::CompletedAndPassed, 0.8);
        $this->statement = Statements::passed($this->context, 0.9);
        $this->xapi = new XapiContext('https://learn.example.org/', 'ada', 'https://example.org/xapi/knots', 'r-1', 's-1', 0.8);
        $this->xapiStatement = [
            'actor' => ['objectType' => 'Agent', 'account' => ['homePage' => 'https://learn.example.org/', 'name' => 'ada']],
            'verb' => ['id' => 'http://adlnet.gov/expapi/verbs/passed'],
            'object' => ['objectType' => 'Activity', 'id' => 'https://example.org/xapi/knots'],
            'result' => ['score' => ['scaled' => 0.9], 'completion' => true],
            'context' => ['registration' => 'r-1'],
        ];
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

        $scorm = $this->store->courses->addActivity($this->store->courses->addModule($course->id, 'SCORM')->id, 'SCO', 'scorm', ['path' => 'a.html', 'scorm_version' => 'scorm2004']);
        $launch = $this->store->scorm->launch($scorm, $this->enrollment);
        $interactions = [];

        for ($i = 0; $i < 50; ++$i) {
            $interactions += ['cmi.interactions.'.$i.'.id' => 'q'.$i, 'cmi.interactions.'.$i.'.type' => 'choice', 'cmi.interactions.'.$i.'.learner_response' => 'a[,]b', 'cmi.interactions.'.$i.'.result' => 'correct'];
        }

        $this->token = (string) $this->store->scorm->commit($launch->token, $interactions, false)->token;
        $this->commit = ['cmi.location' => 'page-12', 'cmi.suspend_data' => str_repeat('s', 4_000), 'cmi.progress_measure' => '0.4', 'cmi.score.scaled' => '0.5', 'cmi.session_time' => 'PT10M'];
    }

    public function benchCommitAScormSession(): void
    {
        $this->store->scorm->commit($this->token, $this->commit, false);
    }

    public function benchCheckAStatement(): void
    {
        StatementRules::check($this->statement, $this->context, SessionState::Initialized, null);
    }

    public function benchCheckAStatementOfAnotherVerb(): void
    {
        StatementRules::check(Statements::of($this->context, Cmi5Verb::Initialized), $this->context, SessionState::Launched, null);
    }

    public function benchCheckAnXapiStatementAndReadItsOutcome(): void
    {
        XapiOutcomes::of(XapiRules::check($this->xapiStatement, $this->xapi), $this->xapi);
    }

    public function benchPlaceTheActivitiesOfACourse(): void
    {
        $this->store->availabilities->outline($this->enrollment);
    }
}
