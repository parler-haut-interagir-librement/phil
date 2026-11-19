<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Scorm;

use Phil\CoursesBundle\Application\ActivityType\ScormActivity;
use Phil\CoursesBundle\Application\ActivityTypes;
use Phil\CoursesBundle\Application\ContentUrls;
use Phil\CoursesBundle\Application\Learning;
use Phil\CoursesBundle\Application\Scorm\ScormLaunch;
use Phil\CoursesBundle\Application\Scorm\ScormRuntime;
use Phil\CoursesBundle\Domain\Exception\ActivityUnavailable;
use Phil\CoursesBundle\Domain\Exception\AttemptRefused;
use Phil\CoursesBundle\Domain\Model\Activity;
use Phil\CoursesBundle\Domain\Model\Enrollment;
use Phil\CoursesBundle\Domain\Progress\CompletionRequirement;
use Phil\CoursesBundle\Domain\Scorm\AttemptState;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\CoursesBundle\Domain\ValueObject\Release;
use Phil\CoursesBundle\Tests\Unit\Fixture\Store;
use Phil\ElearningPackages\Runtime\CmiData;
use Phil\ElearningPackages\Runtime\ScormSession;
use Phil\ElearningPackages\Runtime\ScormVersion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * An ended attempt opened for review: its data in review mode, without credit, no token and nothing kept, the commits
 * of the attempt refused on the server. The vectors of `tests/js/fixture/review.json` hold what the page hands the
 * content in review, and what the content's calls answer: the server answers them as `cmi.test.mjs` asks of the browser.
 */
#[CoversClass(ScormRuntime::class)]
#[CoversClass(ScormLaunch::class)]
#[CoversClass(ScormActivity::class)]
#[CoversClass(Learning::class)]
#[CoversClass(AttemptRefused::class)]
final class ScormReviewTest extends TestCase
{
    private const string VECTORS = __DIR__.'/../../js/fixture/review.json';

    private Store $store;

    protected function setUp(): void
    {
        $this->store = new Store();
    }

    public function testTheServerHandsAndAnswersTheVectorsOfTheReview(): void
    {
        /** @var list<array{version: string, commit: array<string, string>, values: array<string, string>, calls: list<array{0: string, 1: string, 2?: string}>, expect: list<array{string, string}>}> $vectors */
        $vectors = json_decode((string) file_get_contents(self::VECTORS), true, 16, \JSON_THROW_ON_ERROR);
        self::assertCount(2, $vectors);

        foreach ($vectors as $vector) {
            [$activity, $enrollment] = $this->activity('1.2' === $vector['version'] ? 'scorm12' : 'scorm2004', learner: 'ada'.$vector['version']);
            $launch = $this->store->scorm->launch($activity, $enrollment);
            $this->store->scorm->commit($launch->token, $vector['commit'], true);
            $review = $this->store->scorm->review($activity, $enrollment);
            $values = $review->values;
            ksort($values);
            $vector['values']['cmi.'.('1.2' === $vector['version'] ? 'core.student_id' : 'learner_id')] = 'ada'.$vector['version'];
            self::assertSame($vector['values'], $values, $vector['version']);

            $session = new ScormSession(new CmiData(ScormVersion::from($vector['version']), $vector['values']));

            foreach ($vector['calls'] as $index => $call) {
                $answer = match ($call[0]) {
                    'setValue' => $session->setValue($call[1], $call[2] ?? ''),
                    'getValue' => $session->getValue($call[1]),
                    'initialize' => $session->initialize($call[1]),
                    'commit' => $session->commit($call[1]),
                    default => $session->terminate($call[1]),
                };

                self::assertSame($vector['expect'][$index], [$answer, $session->getLastError()], $vector['version'].' '.$call[0].'('.$call[1].')');
            }
        }
    }

    public function testAnEndedAttemptOpensForReviewAndKeepsNothing(): void
    {
        [$activity, $enrollment] = $this->activity('scorm2004', ['scaled_passing_score' => 0.8], CompletionRequirement::Passed);
        $launch = $this->store->scorm->launch($activity, $enrollment);
        $this->store->scorm->commit($launch->token, ['cmi.score.scaled' => '0.5', 'cmi.location' => 'end', 'cmi.exit' => 'normal'], true);
        $ended = $this->store->attemptStore->find($launch->attempt);
        self::assertNotNull($ended);
        $progress = $this->store->enrollments->progresses($enrollment)[$activity->id->value];
        self::assertSame([1, false], [$progress->failures, $progress->success]);
        $this->store->events->events = [];
        $this->store->clock->modify('+1 hour');

        $review = $this->store->scorm->review($activity, $enrollment);
        self::assertSame([true, '', null, false, 1], [$review->review, $review->token, $review->remaining, $review->resumed, $review->number]);
        self::assertSame($launch->attempt->value, $review->attempt->value);
        self::assertSame(['review', 'no-credit', '', 'end', '0.8', 'false'], [$review->values['cmi.mode'], $review->values['cmi.credit'], $review->values['cmi.entry'], $review->values['cmi.location'], $review->values['cmi.scaled_passing_score'], $review->values['adl.nav.request_valid.continue']]);
        self::assertArrayNotHasKey('cmi.exit', $review->values);
        self::assertSame(['review' => true, 'remaining' => null], array_intersect_key($review->state('/a.html'), ['review' => 0, 'remaining' => 0]));
        self::assertSame([], $this->store->events->events);

        $after = $this->store->attemptStore->find($launch->attempt);
        self::assertNotNull($after);
        self::assertSame([AttemptState::Ended, $ended->session, $ended->data, $ended->updatedAt->format('c')], [$after->state, $after->session, $after->data, $after->updatedAt->format('c')]);

        foreach ([$launch->token, $this->store->scormTokens->issue($ended->id, $ended->session), $this->store->scormTokens->issue($ended->id, $ended->session + 1)] as $token) {
            try {
                $this->store->scorm->commit($token, ['cmi.score.scaled' => '1', 'cmi.success_status' => 'passed'], true);
                self::fail('A commit to an attempt open for review is kept.');
            } catch (AttemptRefused $attemptRefused) {
                self::assertSame(['This attempt is over: it opens for review only, and nothing more is kept.', true], [$attemptRefused->getMessage(), $attemptRefused->closed]);
            }
        }

        $kept = $this->store->attemptStore->find($launch->attempt);
        self::assertSame($ended->data, $kept?->data);
        $progress = $this->store->enrollments->progresses($enrollment)[$activity->id->value];
        self::assertSame([1, false, false], [$progress->failures, $progress->success, $progress->isSatisfied()]);
        self::assertSame([], $this->store->events->events);
    }

    public function testTheLastEndedAttemptIsReviewedWhileANewerOneRuns(): void
    {
        [$activity, $enrollment] = $this->activity('scorm12');
        $first = $this->store->scorm->launch($activity, $enrollment);
        $this->store->scorm->commit($first->token, ['cmi.core.lesson_status' => 'failed', 'cmi.core.lesson_location' => 'one'], true);
        $second = $this->store->scorm->launch($activity, $enrollment);
        $this->store->scorm->commit($second->token, ['cmi.core.lesson_status' => 'passed', 'cmi.core.lesson_location' => 'two'], true);
        $third = $this->store->scorm->launch($activity, $enrollment);
        $this->store->scorm->commit($third->token, ['cmi.core.lesson_location' => 'three'], false);

        $review = $this->store->scorm->review($activity, $enrollment);
        self::assertSame([2, 'two', 'review', 'no-credit'], [$review->number, $review->values['cmi.core.lesson_location'], $review->values['cmi.core.lesson_mode'], $review->values['cmi.core.credit']]);
        self::assertSame(AttemptState::Running, $this->store->attemptStore->find($third->attempt)?->state);
        self::assertSame([$activity->id->value], $this->store->scorm->reviewable($enrollment));
        self::assertNotNull($this->store->scorm->commit($third->token, [], false)->token);
    }

    public function testAnActivityWithoutAnEndedAttemptHasNothingToReview(): void
    {
        [$activity, $enrollment] = $this->activity('scorm12');
        [$other] = $this->activity('scorm12', learner: 'eve');
        $this->store->scorm->launch($activity, $enrollment);
        self::assertSame([], $this->store->scorm->reviewable($enrollment));

        foreach ([$activity, $other] as $candidate) {
            try {
                $this->store->scorm->review($candidate, $enrollment);
                self::fail('An activity without an ended attempt opens for review.');
            } catch (ActivityUnavailable $activityUnavailable) {
                self::assertSame('This activity has no ended attempt to review.', $activityUnavailable->getMessage());
            }
        }
    }

    public function testTheActivityTypeAndTheLearningOpenAReviewOfAReachedScormActivity(): void
    {
        [$activity, $enrollment] = $this->activity('scorm12');
        $page = $this->store->courses->addActivity($activity->module, 'P', 'page', ['body' => 'Hi']);
        $later = $this->store->courses->addActivity($activity->module, 'L', 'scorm', ['path' => 'a.html', 'scorm_version' => 'scorm12'], release: Release::daysAfterEnrollment(3));
        $types = new ActivityTypes([new ScormActivity(new ContentUrls(Store::CONTENT), $this->store->scorm), ...array_map($this->store->types->get(...), ['page', 'link', 'cmi5'])]);
        $learning = new Learning($this->store->enrollments, $this->store->availabilities, $types);
        $launch = $learning->launch($enrollment, $activity->id);
        $this->store->scorm->commit((string) $launch->scorm?->token, ['cmi.core.lesson_status' => 'passed'], true);

        self::assertSame([$activity->id->value], $learning->reviewable($enrollment));
        self::assertSame([], $this->store->learning->reviewable($enrollment));
        self::assertSame([], new Learning($this->store->enrollments, $this->store->availabilities, new ActivityTypes([]))->reviewable($enrollment));
        $review = $learning->review($enrollment, $activity->id);
        self::assertSame(Store::CONTENT.'/'.$activity->course->value.'/a.html', $review->url);
        self::assertTrue($review->scorm?->review);

        foreach ([
            [$learning, $page, 'Only an attempt of a SCORM activity opens for review.'],
            [$learning, $later, 'This activity is not open: it is scheduled.'],
            [$this->store->learning, $activity, 'An attempt opens for review with the SCORM run time only.'],
        ] as [$taker, $taken, $message]) {
            try {
                $taker->review($enrollment, $taken->id);
                self::fail($message);
            } catch (ActivityUnavailable $activityUnavailable) {
                self::assertSame($message, $activityUnavailable->getMessage());
            }
        }

        $this->store->courses->addActivity($activity->module, 'Gated', 'page');
        $course = $this->store->courses->get($activity->course);
        $course->imposeOrder(true);

        $this->store->courseStore->save($course);
        $locked = $this->store->courses->outline($activity->course)->activities()[3];

        $this->expectException(ActivityUnavailable::class);
        $this->expectExceptionMessageIs('This activity is not open: it is locked.');
        $learning->review($enrollment, $locked->id);
    }

    /**
     * @param array<string, float|int|string> $settings
     *
     * @return array{0: Activity, 1: Enrollment}
     */
    private function activity(string $version, array $settings = [], CompletionRequirement $requirement = CompletionRequirement::CompletedOrPassed, string $learner = 'ada'): array
    {
        $course = $this->store->courses->create('C'.$learner);
        $module = $this->store->courses->addModule($course->id, 'M');
        $activity = $this->store->courses->addActivity($module->id, 'A', 'scorm', ['path' => 'a.html', 'scorm_version' => $version] + $settings, $requirement);

        return [$activity, $this->store->enrollments->enroll($course->id, LearnerId::of($learner))];
    }
}
