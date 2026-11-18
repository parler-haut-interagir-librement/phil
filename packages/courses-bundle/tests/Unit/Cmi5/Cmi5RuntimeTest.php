<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Cmi5;

use Phil\CoursesBundle\Application\Cmi5\Cmi5Context;
use Phil\CoursesBundle\Application\Cmi5\Cmi5Launcher;
use Phil\CoursesBundle\Application\Cmi5\Cmi5Settings;
use Phil\CoursesBundle\Application\Cmi5\Cmi5Statements;
use Phil\CoursesBundle\Application\Cmi5\Cmi5Tokens;
use Phil\CoursesBundle\Application\Cmi5\IssueWaivedStatement;
use Phil\CoursesBundle\Application\Cmi5\LaunchData;
use Phil\CoursesBundle\Application\Cmi5\PlatformStatements;
use Phil\CoursesBundle\Application\LaunchWindow;
use Phil\CoursesBundle\Domain\Cmi5\Cmi5Iri;
use Phil\CoursesBundle\Domain\Cmi5\Cmi5Verb;
use Phil\CoursesBundle\Domain\Cmi5\SessionState;
use Phil\CoursesBundle\Domain\Event\ActivitySatisfied;
use Phil\CoursesBundle\Domain\Event\CourseCompleted;
use Phil\CoursesBundle\Domain\Event\StatementRecorded;
use Phil\CoursesBundle\Domain\Exception\ActivityUnavailable;
use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\CoursesBundle\Domain\Exception\StatementRefused;
use Phil\CoursesBundle\Domain\Model\Activity;
use Phil\CoursesBundle\Domain\Model\Enrollment;
use Phil\CoursesBundle\Domain\Model\LaunchSession;
use Phil\CoursesBundle\Domain\Progress\AvailabilityStatus;
use Phil\CoursesBundle\Domain\Progress\CompletionRequirement;
use Phil\CoursesBundle\Domain\ValueObject\ActivityId;
use Phil\CoursesBundle\Domain\ValueObject\EnrollmentId;
use Phil\CoursesBundle\Domain\ValueObject\LaunchSessionId;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\CoursesBundle\Tests\Unit\Fixture\FixedEndpoints;
use Phil\CoursesBundle\Tests\Unit\Fixture\Json;
use Phil\CoursesBundle\Tests\Unit\Fixture\Statements;
use Phil\CoursesBundle\Tests\Unit\Fixture\Store;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Cmi5Launcher::class)]
#[CoversClass(Cmi5Statements::class)]
#[CoversClass(Cmi5Tokens::class)]
#[CoversClass(Cmi5Settings::class)]
#[CoversClass(Cmi5Context::class)]
#[CoversClass(LaunchData::class)]
#[CoversClass(PlatformStatements::class)]
#[CoversClass(IssueWaivedStatement::class)]
final class Cmi5RuntimeTest extends TestCase
{
    private Store $store;

    private Enrollment $enrollment;

    private Activity $unit;

    private Activity $framed;

    protected function setUp(): void
    {
        $this->store = new Store();
        $course = $this->store->courses->create('Navigation');
        $module = $this->store->courses->addModule($course->id, 'Charts');
        $this->unit = $this->store->courses->addActivity($module->id, 'Symbols', 'cmi5', [
            'au_id' => 'https://example.org/aus/symbols',
            'url' => 'https://content.example.net/symbols/?lang=en#start',
            'launch_method' => 'OwnWindow',
            'mastery_score' => 0.8,
            'launch_parameters' => '{"level":2}',
            'entitlement_key' => 'key-1',
        ], CompletionRequirement::CompletedAndPassed);
        $this->framed = $this->store->courses->addActivity($module->id, 'Tides', 'cmi5', ['au_id' => 'https://example.org/aus/tides', 'path' => 'tides/start.html', 'query' => '?mode=short'], CompletionRequirement::Completed);
        $this->enrollment = $this->store->enrollments->enroll($course->id, LearnerId::of('ada'));
    }

    public function testAUnitIsLaunchedWithTheParametersOfCmi5(): void
    {
        $launch = $this->store->learning->launch($this->enrollment, $this->unit->id);

        self::assertSame(LaunchWindow::NewWindow, $launch->window);
        $url = (string) $launch->url;
        self::assertStringStartsWith('https://content.example.net/symbols/?lang=en&endpoint=https%3A%2F%2Flearn.example.org%2Fcmi5%2Fxapi%2F&fetch=https%3A%2F%2Flearn.example.org%2Fcmi5%2Ffetch%2F', $url);
        self::assertStringEndsWith('#start', $url);
        parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);
        self::assertSame($this->enrollment->registration, $query['registration']);
        self::assertSame('https://learn.example.org/courses/activities/'.$this->unit->id->value, $query['activityId']);
        self::assertSame('{"objectType":"Agent","account":{"homePage":"https://learn.example.org/","name":"ada"}}', $query['actor']);

        $sessions = $this->store->sessionStore->open($this->enrollment->id, $this->unit->id);
        self::assertCount(1, $sessions);
        self::assertSame('2026-11-13T14:00:00+00:00', $sessions[0]->expiresAt->format(\DATE_ATOM));
        self::assertSame('https://learn.example.org/cmi5/fetch/'.$sessions[0]->id->value, $query['fetch']);

        $launched = $this->statements();
        self::assertCount(1, $launched);
        self::assertSame(Cmi5Verb::Launched->value, Json::at($launched[0], 'verb', 'id'));
        self::assertSame('Normal', Json::at($launched[0], 'context', 'extensions', Cmi5Iri::LAUNCH_MODE));
        self::assertSame($url, Json::at($launched[0], 'context', 'extensions', Cmi5Iri::LAUNCH_URL));
        self::assertSame('CompletedAndPassed', Json::at($launched[0], 'context', 'extensions', Cmi5Iri::MOVE_ON));
        self::assertSame('{"level":2}', Json::at($launched[0], 'context', 'extensions', Cmi5Iri::LAUNCH_PARAMETERS));
        self::assertSame([['objectType' => 'Activity', 'id' => 'https://example.org/aus/symbols']], Json::at($launched[0], 'context', 'contextActivities', 'grouping'));
        self::assertSame('2026-11-13T10:00:00.000+00:00', Json::at($launched[0], 'timestamp'));
        self::assertCount(1, $this->store->events->of(StatementRecorded::class));
        self::assertSame(Cmi5Verb::Launched->value, $this->store->events->of(StatementRecorded::class)[0]->verb);
    }

    public function testAUnitOfThePackageIsFramedFromTheContentOrigin(): void
    {
        $launch = $this->store->learning->launch($this->enrollment, $this->framed->id);

        self::assertSame(LaunchWindow::Embedded, $launch->window);
        self::assertStringStartsWith('https://content.example.org/'.$this->framed->course->value.'/tides/start.html?mode=short&endpoint=', (string) $launch->url);
        self::assertNull(Json::at($this->statements()[0], 'context', 'extensions', Cmi5Iri::LAUNCH_PARAMETERS));
        self::assertNotNull(Json::at($this->statements()[0], 'context', 'extensions', Cmi5Iri::LAUNCH_URL));
    }

    public function testALaunchAbandonsTheSessionStillOpen(): void
    {
        $this->store->learning->launch($this->enrollment, $this->unit->id);
        $first = $this->store->sessionStore->open($this->enrollment->id, $this->unit->id)[0];
        $this->store->clock->modify('+90 seconds');
        $this->store->learning->launch($this->enrollment, $this->unit->id);

        $open = $this->store->sessionStore->open($this->enrollment->id, $this->unit->id);
        self::assertCount(1, $open);
        self::assertNotSame($first->id->value, $open[0]->id->value);
        $abandoned = $this->store->sessionStore->find($first->id);
        self::assertSame(SessionState::Abandoned, $abandoned?->state);
        self::assertSame('2026-11-13T10:01:30+00:00', $abandoned->endedAt?->format(\DATE_ATOM));

        $statements = $this->statements();
        self::assertSame([Cmi5Verb::Launched->value, Cmi5Verb::Abandoned->value, Cmi5Verb::Launched->value], array_map(static fn (array $statement): string => Json::text($statement, 'verb', 'id'), $statements));
        self::assertSame('PT90S', Json::at($statements[1], 'result', 'duration'));
        self::assertSame($first->id->value, Json::at($statements[1], 'context', 'extensions', Cmi5Iri::SESSION));
    }

    public function testATokenIsFetchedOnceAndOpensItsSession(): void
    {
        $session = $this->launch($this->unit, false);
        $answer = $this->store->tokens->fetch($session->id->value);

        self::assertArrayHasKey('auth-token', $answer);
        $token = $answer['auth-token'];
        self::assertSame($token, $this->store->tokens->token($session->id));
        self::assertSame(['error-code' => '1', 'error-text' => 'The token of this session was fetched already.'], $this->store->tokens->fetch($session->id->value));
        self::assertSame($session->id->value, $this->store->tokens->authenticate('Basic '.$token)?->id->value);
        self::assertSame($session->id->value, $this->store->tokens->authenticate('Basic  '.$token)?->id->value);

        foreach ([null, '', 'Bearer '.$token, 'Basic '.substr($token, 0, -4).'AAAA', 'Basic !!!', 'Basic '.base64_encode('no-colon'), 'Basic '.base64_encode('bad id:x'), 'Basic '.base64_encode($session->id->value.':forged'), 'Basic '.base64_encode(str_repeat('a', 37).':x')] as $header) {
            self::assertNull($this->store->tokens->authenticate($header), (string) $header);
        }

        $this->store->clock->modify('+4 hours');
        self::assertNull($this->store->tokens->authenticate('Basic '.$token));
    }

    public function testAnUnknownEndedOrExpiredSessionGivesNoToken(): void
    {
        $invalid = ['error-code' => '2', 'error-text' => 'The session is unknown, ended or expired.'];
        self::assertSame($invalid, $this->store->tokens->fetch('not an id'));
        self::assertSame($invalid, $this->store->tokens->fetch(LaunchSessionId::generate()->value));

        $session = $this->launch($this->unit, false);
        $unfetched = new Cmi5Tokens($this->store->sessionStore, $this->store->clock, Store::SECRET);
        self::assertNull($unfetched->authenticate('Basic '.$unfetched->token($session->id)));
        self::assertNull(new Cmi5Tokens($this->store->sessionStore, $this->store->clock, 'another secret')->authenticate('Basic '.$this->store->tokens->token($session->id)));
        $this->store->clock->modify('+4 hours');
        self::assertSame($invalid, $this->store->tokens->fetch($session->id->value));
    }

    public function testTheStatementsOfASessionMoveTheProgressAndCompleteTheCourse(): void
    {
        $tides = $this->launch($this->framed);
        $context = $this->store->statements->context($tides);
        $this->store->statements->receive($tides, (string) json_encode(Statements::of($context, Cmi5Verb::Initialized)));
        self::assertSame(SessionState::Initialized, $this->store->sessionStore->find($tides->id)?->state);
        $tides = $this->reload($tides);
        $this->store->statements->receive($tides, (string) json_encode([Statements::completed($context), Statements::of($context, Cmi5Verb::Terminated, ['duration' => 'PT6M'])]));
        self::assertSame(SessionState::Terminated, $this->store->sessionStore->find($tides->id)?->state);
        self::assertTrue($this->store->enrollmentStore->progress($this->enrollment->id, $this->framed->id)?->isSatisfied());

        $symbols = $this->launch($this->unit);
        $context = $this->store->statements->context($symbols);
        $this->store->statements->receive($symbols, (string) json_encode(Statements::of($context, Cmi5Verb::Initialized)));
        $symbols = $this->reload($symbols);
        $ids = $this->store->statements->receive($symbols, (string) json_encode([Statements::failed($context, 0.5), Statements::completed($context)]));
        self::assertCount(2, $ids);
        self::assertSame(1, $this->store->enrollmentStore->progress($this->enrollment->id, $this->unit->id)?->failures);
        self::assertFalse($this->store->enrollments->get($this->enrollment->id)->isCompleted());

        $id = 'a7d1c6b8-0e0f-4c3a-9a37-2b8f1d4e5c6a';
        self::assertSame([$id], $this->store->statements->receive($symbols, (string) json_encode(Statements::passed($context, 0.85)), $id));
        self::assertSame(0.85, $this->store->enrollmentStore->progress($this->enrollment->id, $this->unit->id)?->score?->scaled);
        self::assertTrue($this->store->enrollments->get($this->enrollment->id)->isCompleted());
        self::assertCount(1, $this->store->events->of(CourseCompleted::class));

        $last = array_last($this->statements());
        self::assertSame(Cmi5Verb::Satisfied->value, Json::at($last, 'verb', 'id'));
        self::assertSame('https://learn.example.org/courses/'.$this->unit->course->value, Json::at($last, 'object', 'id'));
        self::assertSame([['objectType' => 'Activity', 'id' => 'https://learn.example.org/courses/'.$this->unit->course->value]], Json::at($last, 'context', 'contextActivities', 'grouping'));
        self::assertContains(['id' => Cmi5Iri::MOVE_ON_CATEGORY], (array) Json::at($last, 'context', 'contextActivities', 'category'));
        self::assertSame(1, Store::count($this->store->connection, 'SELECT COUNT(*) FROM phil_course_statement WHERE id = ?', [$id]));
        self::assertSame(0.85, Json::at(Json::decode($this->store->connection->fetchOne('SELECT body FROM phil_course_statement WHERE id = ?', [$id])), 'result', 'score', 'scaled'));
    }

    public function testAStatementWithAScoreOfOneKeepsItAsAFloat(): void
    {
        $session = $this->launch($this->framed);
        $context = $this->store->statements->context($session);
        $this->store->statements->receive($session, (string) json_encode(Statements::of($context, Cmi5Verb::Initialized)));
        $this->store->statements->receive($this->reload($session), (string) json_encode(Statements::of($context, Cmi5Verb::Completed, ['completion' => true, 'duration' => 'PT1M', 'score' => ['scaled' => 1]])));

        self::assertSame(1.0, $this->store->enrollmentStore->progress($this->enrollment->id, $this->framed->id)?->score?->scaled);
    }

    public function testABodyThatIsNotStatementsIsRefused(): void
    {
        $session = $this->launch($this->framed);
        $context = $this->store->statements->context($session);
        $statement = Statements::of($context, Cmi5Verb::Initialized);

        foreach ([
            'The body is not JSON of a bounded depth.' => ['{', null],
            'The body is a statement, or a list of them.' => ['[]', null],
            'Each statement is an object.' => ['[1]', null],
            'A request holds 50 statements at most.' => [(string) json_encode(array_fill(0, 51, $statement)), null],
            'A request holds 1048576 bytes of statements at most.' => [str_repeat(' ', 1_048_577), null],
            'A statement put at an identifier is one statement of that identifier.' => [(string) json_encode([$statement, $statement]), 'a7d1c6b8-0e0f-4c3a-9a37-2b8f1d4e5c6a'],
            'one statement of that identifier' => [(string) json_encode(['id' => 'b7d1c6b8-0e0f-4c3a-9a37-2b8f1d4e5c6a'] + $statement), 'a7d1c6b8-0e0f-4c3a-9a37-2b8f1d4e5c6a'],
            'one statement of that identifier.' => ['[1]', 'a7d1c6b8-0e0f-4c3a-9a37-2b8f1d4e5c6a'],
            'The body is not JSON' => [str_repeat('[', 40).str_repeat(']', 40), null],
            'The body is a statement' => ['"text"', null],
        ] as $message => [$body, $id]) {
            try {
                $this->store->statements->receive($session, $body, $id);
                self::fail('The body is accepted: '.$message);
            } catch (StatementRefused $statementRefused) {
                self::assertStringContainsString($message, $statementRefused->getMessage());
            }
        }

        self::assertCount(1, $this->statements());
        self::assertCount(50, $this->store->statements->receive($session, (string) json_encode([$statement, ...array_fill(0, 49, Statements::of($context, 'http://adlnet.gov/expapi/verbs/answered'))])));
    }

    public function testAStatementRefusedInABatchLeavesTheOnesBeforeItKeptAndApplied(): void
    {
        $session = $this->launch($this->framed);
        $context = $this->store->statements->context($session);
        $batch = (string) json_encode([Statements::of($context, Cmi5Verb::Initialized), Statements::of($context, Cmi5Verb::Terminated)]);

        try {
            $this->store->statements->receive($session, $batch);
            self::fail('A terminated statement without a duration is accepted.');
        } catch (StatementRefused $statementRefused) {
            self::assertStringContainsString('duration', $statementRefused->getMessage());
        }

        $session = $this->reload($session);
        self::assertSame(SessionState::Initialized, $session->state);
        self::assertSame(Cmi5Verb::Initialized->value, Json::at(array_last($this->statements()), 'verb', 'id'));

        $this->expectException(StatementRefused::class);
        $this->store->statements->receive($session, $batch);
    }

    public function testAStatementOfAGoneEnrollmentIsRefused(): void
    {
        $session = new LaunchSession(LaunchSessionId::generate(), EnrollmentId::of('gone'), $this->unit->id, $this->store->clock->now(), $this->store->clock->now()->modify('+1 hour'));

        foreach ([static fn (Store $store): mixed => $store->statements->receive($session, '{"verb":{}}'), static fn (Store $store): mixed => $store->statements->context($session)] as $call) {
            try {
                $call($this->store);
                self::fail('The session is accepted.');
            } catch (StatementRefused $statementRefused) {
                self::assertSame('The session belongs to an enrollment or an activity that is gone.', $statementRefused->getMessage());
            }
        }

        $this->expectException(StatementRefused::class);
        $this->store->statements->receive(new LaunchSession(LaunchSessionId::generate(), $this->enrollment->id, ActivityId::of('gone'), $this->store->clock->now(), $this->store->clock->now()), '{"verb":{}}');
    }

    public function testAWaivedUnitHasItsStatement(): void
    {
        $listener = new IssueWaivedStatement($this->store->enrollmentStore, $this->store->courseStore, $this->store->statements, $this->store->settings, $this->store->clock);
        $this->store->events->listeners[] = static fn (object $event): mixed => $event instanceof ActivitySatisfied ? $listener($event) : null;
        $this->store->enrollments->waive($this->enrollment, $this->unit);

        $statement = $this->statements()[0];
        self::assertSame(Cmi5Verb::Waived->value, Json::at($statement, 'verb', 'id'));
        self::assertSame(['completion' => true, 'success' => true], Json::at($statement, 'result'));
        self::assertSame('Administrative', Json::at($statement, 'context', 'extensions', Cmi5Iri::REASON));
        self::assertSame(AvailabilityStatus::Satisfied, $this->store->availabilities->of($this->enrollment, $this->unit->id)->availability->status);

        $page = $this->store->courses->addActivity($this->store->courses->outline($this->unit->course)->modules[0]->module->id, 'Page', 'page');
        $this->store->enrollments->waive($this->enrollment, $page);
        $this->store->learning->complete($this->enrollment, $page->id);
        $listener(new ActivitySatisfied('gone', $this->unit->id->value, true));
        $listener(new ActivitySatisfied($this->enrollment->id->value, 'gone', true));
        $listener(new ActivitySatisfied($this->enrollment->id->value, $this->unit->id->value));
        self::assertCount(1, $this->statements());
    }

    public function testTheLaunchDataIsTheContextTemplate(): void
    {
        $session = $this->launch($this->unit);
        $document = LaunchData::document($this->store->statements->context($session), 'https://learn.example.org/courses/x');

        self::assertSame([
            'contextTemplate' => [
                'contextActivities' => ['grouping' => [['objectType' => 'Activity', 'id' => 'https://example.org/aus/symbols']]],
                'extensions' => [Cmi5Iri::SESSION => $session->id->value],
            ],
            'launchMode' => 'Normal',
            'moveOn' => 'CompletedAndPassed',
            'masteryScore' => 0.8,
            'launchParameters' => '{"level":2}',
            'entitlementKey' => ['courseStructure' => 'key-1'],
            'returnURL' => 'https://learn.example.org/courses/x',
        ], $document);
        self::assertSame(['contextTemplate', 'launchMode', 'moveOn'], array_keys(LaunchData::document($this->store->statements->context($this->launch($this->framed)))));
    }

    public function testAContextFallsBackOnItsActivity(): void
    {
        $activity = new Activity(ActivityId::of('a'), $this->unit->course, $this->unit->module, 'A', 'cmi5', 0, ['mastery_score' => 1]);
        $session = new LaunchSession(LaunchSessionId::of('s'), $this->enrollment->id, $activity->id, $this->store->clock->now(), $this->store->clock->now());
        $context = Cmi5Context::of($session, $this->enrollment, $activity, 'https://learn.example.org/');

        self::assertSame('https://learn.example.org/courses/activities/a', $context->publisherId);
        self::assertSame('https://learn.example.org/courses/activities/a', $context->activityIri);
        self::assertSame(1.0, $context->masteryScore);
        self::assertNull(Cmi5Context::of($session, $this->enrollment, new Activity(ActivityId::of('a'), $this->unit->course, $this->unit->module, 'A', 'cmi5', 0, ['mastery_score' => 'high']), 'https://learn.example.org/')->masteryScore);
    }

    public function testTheSettingsNameTheHomePage(): void
    {
        self::assertSame('https://learn.example.org/', new Cmi5Settings('https://LEARN.example.org')->homePage());
        self::assertSame(60, new Cmi5Settings('https://learn.example.org', 60)->sessionTtl);
        self::assertSame(14_400, new Cmi5Settings(null)->sessionTtl);
        self::assertSame('http://learn.localhost/', new Cmi5Settings('http://learn.localhost', 60, true)->homePage());

        foreach ([static fn (): Cmi5Settings => new Cmi5Settings('https://learn.example.org', 59), static fn (): Cmi5Settings => new Cmi5Settings('http://learn.localhost')] as $build) {
            try {
                $build();
                self::fail('The settings are accepted.');
            } catch (InvalidCourse $invalidCourse) {
                self::assertMatchesRegularExpression('/^(A cmi5 session holds for 60 seconds at least\.|The home page of the cmi5 accounts is not an address: .+)$/', $invalidCourse->getMessage());
            }
        }

        $launcher = new Cmi5Launcher($this->store->sessionStore, $this->store->statements, new FixedEndpoints(), $this->store->urls, new Cmi5Settings(null), $this->store->clock);

        $this->expectException(ActivityUnavailable::class);
        $this->expectExceptionMessageIsOrContains('"phil_courses.cmi5.actor_home_page"');

        $launcher->launch($this->unit, $this->enrollment);
    }

    public function testAUnitWithoutLocationCannotBeLaunched(): void
    {
        $activity = new Activity(ActivityId::of('a'), $this->unit->course, $this->unit->module, 'A', 'cmi5', 0, ['au_id' => 'x']);

        $this->expectException(ActivityUnavailable::class);
        $this->expectExceptionMessageIsOrContains('The assignable unit "a" names no URL.');

        $this->store->launcher->launch($activity, $this->enrollment);
    }

    private function launch(Activity $activity, bool $fetch = true): LaunchSession
    {
        $this->store->learning->launch($this->enrollment, $activity->id);
        $session = $this->store->sessionStore->open($this->enrollment->id, $activity->id)[0];

        if ($fetch) {
            $this->store->tokens->fetch($session->id->value);
        }

        return $this->reload($session);
    }

    private function reload(LaunchSession $session): LaunchSession
    {
        return $this->store->sessionStore->find($session->id) ?? $session;
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    private function statements(): array
    {
        return array_map(Json::decode(...), $this->store->connection->fetchFirstColumn('SELECT body FROM phil_course_statement ORDER BY stored_at, rowid'));
    }
}
