<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Xapi;

use Phil\CoursesBundle\Application\ActivityType\XapiActivity;
use Phil\CoursesBundle\Application\Cmi5\Cmi5Settings;
use Phil\CoursesBundle\Application\LaunchUrl;
use Phil\CoursesBundle\Application\LaunchWindow;
use Phil\CoursesBundle\Application\Xapi\XapiContext;
use Phil\CoursesBundle\Application\Xapi\XapiLauncher;
use Phil\CoursesBundle\Application\Xapi\XapiOutcomes;
use Phil\CoursesBundle\Application\Xapi\XapiStatements;
use Phil\CoursesBundle\Domain\Cmi5\SessionState;
use Phil\CoursesBundle\Domain\Event\StatementRecorded;
use Phil\CoursesBundle\Domain\Exception\ActivityUnavailable;
use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\CoursesBundle\Domain\Exception\StatementRefused;
use Phil\CoursesBundle\Domain\Model\Activity;
use Phil\CoursesBundle\Domain\Model\Enrollment;
use Phil\CoursesBundle\Domain\Model\LaunchSession;
use Phil\CoursesBundle\Domain\Progress\CompletionRequirement;
use Phil\CoursesBundle\Domain\ValueObject\ActivityId;
use Phil\CoursesBundle\Domain\ValueObject\LaunchSessionId;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\CoursesBundle\Domain\Xapi\XapiVerb;
use Phil\CoursesBundle\Infrastructure\Doctrine\CourseTables;
use Phil\CoursesBundle\Tests\Unit\Fixture\FixedEndpoints;
use Phil\CoursesBundle\Tests\Unit\Fixture\Json;
use Phil\CoursesBundle\Tests\Unit\Fixture\Store;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * An xAPI activity: its settings, its launch with the parameters xAPI packages read and a token of its own session,
 * and its statements, kept under the registration of the enrollment and moving the learner's progress.
 */
#[CoversClass(XapiActivity::class)]
#[CoversClass(XapiLauncher::class)]
#[CoversClass(XapiStatements::class)]
#[CoversClass(XapiContext::class)]
#[CoversClass(XapiOutcomes::class)]
#[CoversClass(LaunchUrl::class)]
final class XapiRuntimeTest extends TestCase
{
    private const string IRI = 'https://example.org/xapi/knots';

    private Store $store;

    private Enrollment $enrollment;

    private Activity $knots;

    private Activity $framed;

    protected function setUp(): void
    {
        $this->store = new Store();
        $course = $this->store->courses->create('Sailing');
        $module = $this->store->courses->addModule($course->id, 'Rigging');
        $this->knots = $this->store->courses->addActivity($module->id, 'Knots', XapiActivity::NAME, [
            'activity_id' => self::IRI,
            'url' => 'https://units.example.net/knots/?lang=en#start',
            'launch_method' => 'OwnWindow',
            'mastery_score' => 0.8,
        ], CompletionRequirement::Passed);
        $this->framed = $this->store->courses->addActivity($module->id, 'Sails', XapiActivity::NAME, ['activity_id' => 'urn:sails', 'path' => 'sails/index.html', 'query' => '?mode=short'], CompletionRequirement::Completed);
        $this->enrollment = $this->store->enrollments->enroll($course->id, LearnerId::of('ada'));
    }

    public function testTheSettingsAreChecked(): void
    {
        $type = $this->store->types->get(XapiActivity::NAME);
        self::assertSame(XapiActivity::NAME, $type->name());
        self::assertFalse($type->completesManually());
        self::assertSame(['activity_id' => 'urn:x', 'path' => 'a/b.html'], $type->settings(['activity_id' => 'urn:x', 'path' => 'a/b.html']));
        self::assertSame(['activity_id' => 'urn:x', 'url' => 'https://example.org/a', 'mastery_score' => 1, 'launch_method' => 'AnyWindow'], $type->settings(['activity_id' => 'urn:x', 'url' => 'https://example.org/a', 'mastery_score' => 1, 'launch_method' => 'AnyWindow']));
        self::assertSame(0, $type->settings(['activity_id' => 'urn:x', 'url' => 'https://example.org/a', 'mastery_score' => 0])['mastery_score']);
        self::assertSame(0.5, $type->settings(['activity_id' => 'urn:x', 'url' => 'https://example.org/a', 'mastery_score' => 0.5])['mastery_score']);

        foreach ([
            [['url' => 'https://example.org/a'], 'An xAPI activity names the "activity_id" of its package, an IRI of 2048 bytes at most.'],
            [['activity_id' => 'knots', 'url' => 'https://example.org/a'], 'An xAPI activity names the "activity_id" of its package'],
            [['activity_id' => 'urn:x', 'url' => 'https://example.org/a', 'launch_method' => 'Popup'], 'The "launch_method" of an xAPI activity is AnyWindow or OwnWindow.'],
            [['activity_id' => 'urn:x', 'url' => 'https://example.org/a', 'mastery_score' => 1.1], 'The "mastery_score" of an xAPI activity is a number from 0 to 1.'],
            [['activity_id' => 'urn:x', 'url' => 'https://example.org/a', 'mastery_score' => -0.1], 'The "mastery_score" of an xAPI activity is a number from 0 to 1.'],
            [['activity_id' => 'urn:x', 'url' => 'https://example.org/a', 'mastery_score' => '0.5'], 'The "mastery_score" of an xAPI activity is a number from 0 to 1.'],
            [['activity_id' => 'urn:x', 'url' => 'not an address'], 'The location of an xAPI activity is wrong: '],
            [['activity_id' => 'urn:x', 'path' => '../escape.html'], 'The location of an xAPI activity is wrong: '],
            [['activity_id' => 'urn:x'], 'An xAPI activity names its "url", or the "path" of its launch file.'],
        ] as [$settings, $reason]) {
            try {
                $type->settings($settings);
                self::fail('Accepted: '.json_encode($settings));
            } catch (InvalidCourse $invalidCourse) {
                self::assertStringStartsWith($reason, $invalidCourse->getMessage());
            }
        }
    }

    public function testAnActivityIsLaunchedWithTheParametersOfXapi(): void
    {
        $launch = $this->store->learning->launch($this->enrollment, $this->knots->id);

        self::assertSame(LaunchWindow::NewWindow, $launch->window);
        $url = (string) $launch->url;
        self::assertStringStartsWith('https://units.example.net/knots/?lang=en&endpoint=https%3A%2F%2Flearn.example.org%2Fcmi5%2Fxapi%2F&auth=Basic%20', $url);
        self::assertStringEndsWith('#start', $url);
        parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);
        self::assertSame(['lang', 'endpoint', 'auth', 'actor', 'registration', 'activity_id'], array_keys($query));
        self::assertSame($this->enrollment->registration, $query['registration']);
        self::assertSame(self::IRI, $query['activity_id']);
        self::assertSame('{"objectType":"Agent","account":{"homePage":"https://learn.example.org/","name":"ada"}}', $query['actor']);

        $sessions = $this->store->sessionStore->open($this->enrollment->id, $this->knots->id);
        self::assertCount(1, $sessions);
        self::assertTrue($sessions[0]->fetched);
        self::assertSame('2026-11-13T14:00:00+00:00', $sessions[0]->expiresAt->format(\DATE_ATOM));
        self::assertSame('Basic '.$this->store->tokens->token($sessions[0]->id), $query['auth']);
        self::assertSame($sessions[0]->id->value, $this->store->tokens->authenticate(Json::text($query, 'auth'))?->id->value);
        self::assertSame(0, Store::count($this->store->connection, 'SELECT COUNT(*) FROM '.CourseTables::STATEMENT));
    }

    public function testAnActivityOfThePackageIsFramedFromTheContentOrigin(): void
    {
        $launch = $this->store->learning->launch($this->enrollment, $this->framed->id);

        self::assertSame(LaunchWindow::Embedded, $launch->window);
        self::assertStringStartsWith('https://content.example.org/'.$this->framed->course->value.'/sails/index.html?mode=short&endpoint=', (string) $launch->url);
    }

    public function testALaunchAbandonsTheSessionStillOpen(): void
    {
        $this->store->learning->launch($this->enrollment, $this->knots->id);
        $first = $this->store->sessionStore->open($this->enrollment->id, $this->knots->id)[0];
        $this->store->clock->modify('+90 seconds');
        $this->store->learning->launch($this->enrollment, $this->knots->id);

        $open = $this->store->sessionStore->open($this->enrollment->id, $this->knots->id);
        self::assertCount(1, $open);
        self::assertNotSame($first->id->value, $open[0]->id->value);
        $abandoned = $this->store->sessionStore->find($first->id);
        self::assertNotNull($abandoned);
        self::assertSame([SessionState::Abandoned, '2026-11-13T10:01:30+00:00'], [$abandoned->state, $abandoned->endedAt?->format(\DATE_ATOM)]);
    }

    public function testAnActivityWithoutAPlaceOrAHomePageIsNotLaunched(): void
    {
        $bare = new Activity($this->knots->id, $this->knots->course, $this->knots->module, 'Bare', XapiActivity::NAME, 0, ['activity_id' => self::IRI]);

        try {
            $this->store->xapiLauncher->launch($bare, $this->enrollment);
            self::fail('Launched.');
        } catch (ActivityUnavailable $activityUnavailable) {
            self::assertSame(\sprintf('The xAPI activity "%s" names no URL.', $this->knots->id->value), $activityUnavailable->getMessage());
        }

        $this->expectException(ActivityUnavailable::class);
        new XapiLauncher($this->store->sessionStore, $this->store->tokens, new FixedEndpoints(), $this->store->urls, new Cmi5Settings(null), $this->store->clock)->launch($this->knots, $this->enrollment);
    }

    public function testTheStatementsAreKeptAndMoveTheProgress(): void
    {
        $session = $this->session();
        self::assertTrue($this->store->xapi->handles($session));
        $context = $this->store->xapi->context($session);
        self::assertSame([self::IRI, $this->enrollment->registration, 'ada', 'https://learn.example.org/', 0.8, $session->id->value], [$context->activityIri, $context->registration, $context->learner, $context->homePage, $context->masteryScore, $context->session]);

        $ids = $this->store->xapi->receive($session, (string) json_encode([
            $this->statement('https://example.org/verbs/experienced', self::IRI.'/page-1'),
            ['id' => '0a1b2c3d-4e5f-4a6b-8c7d-9e0f1a2b3c4d'] + $this->statement(XapiVerb::Completed->value, self::IRI, ['score' => ['scaled' => 0.75]]),
        ]));

        self::assertCount(2, $ids);
        self::assertSame('0a1b2c3d-4e5f-4a6b-8c7d-9e0f1a2b3c4d', $ids[1]);
        $kept = $this->store->lrs->statementsOf([$this->enrollment->registration]);
        self::assertCount(2, $kept);
        $completed = array_find($kept, static fn (array $statement): bool => '0a1b2c3d-4e5f-4a6b-8c7d-9e0f1a2b3c4d' === $statement['id'])['statement'] ?? '';
        self::assertSame($this->enrollment->registration, Json::at(Json::decode($completed), 'context', 'registration'));
        self::assertSame(0.75, Json::at(Json::decode($completed), 'result', 'score', 'scaled'));
        self::assertSame(['https://example.org/verbs/experienced', XapiVerb::Completed->value], array_map(static fn (StatementRecorded $event): string => $event->verb, $this->store->events->of(StatementRecorded::class)));
        self::assertSame($session->id->value, $this->store->events->of(StatementRecorded::class)[0]->session);
        $progress = $this->store->enrollments->progresses($this->enrollment)[$this->knots->id->value];
        self::assertSame([true, false, 0.75, false], [$progress->completed, $progress->success, $progress->score?->scaled, $progress->isSatisfied()]);

        self::assertSame(['1b2c3d4e-5f6a-4b7c-8d9e-0f1a2b3c4d5e'], $this->store->xapi->receive($session, (string) json_encode($this->statement(XapiVerb::Passed->value, self::IRI, ['score' => ['scaled' => 0.9]])), '1b2c3d4e-5f6a-4b7c-8d9e-0f1a2b3c4d5e'));
        $progress = $this->store->enrollments->progresses($this->enrollment)[$this->knots->id->value];
        self::assertSame([true, 0.9, true], [$progress->success, $progress->score?->scaled, $progress->isSatisfied()]);
    }

    public function testAStatementRefusedStopsTheRequestWithTheOnesBeforeKept(): void
    {
        $session = $this->session();

        try {
            $this->store->xapi->receive($session, (string) json_encode([
                $this->statement('https://example.org/verbs/experienced'),
                ['actor' => ['account' => ['homePage' => 'https://learn.example.org/', 'name' => 'eve']]] + $this->statement(XapiVerb::Passed->value),
            ]));
            self::fail('Accepted.');
        } catch (StatementRefused) {
            self::assertCount(1, $this->store->lrs->statementsOf([$this->enrollment->registration]));
            $progress = $this->store->enrollments->progresses($this->enrollment)[$this->knots->id->value];
            self::assertSame([false, null], [$progress->completed, $progress->success]);
        }
    }

    public function testASessionOfAnotherTypeOrOfAGoneActivityIsNotHandled(): void
    {
        $cmi5 = new LaunchSession(LaunchSessionId::generate(), $this->enrollment->id, $this->store->courses->addActivity($this->store->courses->outline($this->knots->course)->modules[0]->module->id, 'Page', 'page')->id, $this->store->clock->now(), $this->store->clock->now()->modify('+1 hour'));
        self::assertFalse($this->store->xapi->handles($cmi5));

        $gone = new LaunchSession(LaunchSessionId::generate(), $this->enrollment->id, ActivityId::generate(), $this->store->clock->now(), $this->store->clock->now()->modify('+1 hour'));
        self::assertFalse($this->store->xapi->handles($gone));
        $this->expectException(StatementRefused::class);
        $this->expectExceptionMessageIsOrContains('The session belongs to an enrollment or an activity that is gone.');
        $this->store->xapi->context($gone);
    }

    public function testTheParametersGoBeforeTheFragment(): void
    {
        self::assertSame('https://a.example/x?a=1&b=c%20d#f', LaunchUrl::with('https://a.example/x?a=1#f', ['b' => 'c d']));
        self::assertSame('https://a.example/x?b=1', LaunchUrl::with('https://a.example/x', ['b' => '1']));
    }

    public function testTheMasteryScoreIsTheNumberTheActivityHoldsAndNothingElse(): void
    {
        $session = $this->session();

        foreach ([[1, 1.0], [0.25, 0.25], ['high', null], [null, null]] as [$setting, $expected]) {
            $activity = new Activity(ActivityId::generate(), $this->knots->course, $this->knots->module, 'Mastery', XapiActivity::NAME, 9, null === $setting ? [] : ['mastery_score' => $setting]);

            self::assertSame($expected, XapiContext::of($session, $this->enrollment, $activity, 'https://learn.example.org/')->masteryScore, var_export($setting, true));
        }
    }

    public function testTheStatementsAreKeptAsWrittenWithoutEscapingSlashesOrLetters(): void
    {
        $session = $this->session();
        $this->store->xapi->receive($session, (string) json_encode([
            'id' => '0a1b2c3d-4e5f-4a6b-8c7d-9e0f1a2b3c4d',
            'actor' => ['objectType' => 'Agent', 'account' => ['homePage' => 'https://learn.example.org/', 'name' => 'ada']],
            'verb' => ['id' => 'https://example.org/verbs/experienced', 'display' => ['fr' => 'expérimenté']],
            'object' => ['objectType' => 'Activity', 'id' => self::IRI],
            'result' => ['score' => ['raw' => 3.0]],
        ], \JSON_PRESERVE_ZERO_FRACTION));

        $kept = $this->store->lrs->statementsOf([$this->enrollment->registration]);

        self::assertCount(1, $kept);
        self::assertStringContainsString('"id":"https://example.org/xapi/knots"', $kept[0]['statement']);
        self::assertStringContainsString('"fr":"expérimenté"', $kept[0]['statement']);
        self::assertStringContainsString('"raw":3.0', $kept[0]['statement']);
    }

    public function testTheVerbOfAStatementIsItsIdentifierOrNothing(): void
    {
        self::assertSame('urn:verb', XapiOutcomes::verbOf(['verb' => ['id' => 'urn:verb']]));
        self::assertSame('', XapiOutcomes::verbOf(['verb' => ['display' => ['en' => 'did']]]));
        self::assertSame('', XapiOutcomes::verbOf(['verb' => ['id' => 5]]));
        self::assertSame('', XapiOutcomes::verbOf(['verb' => 'urn:verb']));
        self::assertSame('', XapiOutcomes::verbOf([]));
    }

    public function testAStatementIsKeptWithoutADispatcher(): void
    {
        $xapi = new XapiStatements($this->store->lrs, $this->store->enrollmentStore, $this->store->courseStore, $this->store->enrollments, $this->store->settings);

        self::assertCount(1, $xapi->receive($this->session(), (string) json_encode($this->statement('https://example.org/verbs/experienced'))));
    }

    private function session(): LaunchSession
    {
        $this->store->learning->launch($this->enrollment, $this->knots->id);

        return $this->store->sessionStore->open($this->enrollment->id, $this->knots->id)[0];
    }

    /**
     * @param array<string, mixed> $result
     *
     * @return array<string, mixed>
     */
    private function statement(string $verb, string $object = self::IRI, array $result = []): array
    {
        return [
            'actor' => ['objectType' => 'Agent', 'account' => ['homePage' => 'https://learn.example.org/', 'name' => 'ada']],
            'verb' => ['id' => $verb],
            'object' => ['objectType' => 'Activity', 'id' => $object],
        ] + ([] === $result ? [] : ['result' => $result]);
    }
}
