<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Cmi5;

use DateTimeImmutable;
use Phil\CoursesBundle\Application\Cmi5\Cmi5Context;
use Phil\CoursesBundle\Application\Cmi5\Cmi5Statements;
use Phil\CoursesBundle\Application\Cmi5\Cmi5Tokens;
use Phil\CoursesBundle\Application\Cmi5\PlatformStatements;
use Phil\CoursesBundle\Application\Cmi5\StatementRules;
use Phil\CoursesBundle\Domain\Cmi5\Cmi5Iri;
use Phil\CoursesBundle\Domain\Cmi5\Cmi5Verb;
use Phil\CoursesBundle\Domain\Event\StatementRecorded;
use Phil\CoursesBundle\Domain\Exception\StatementRefused;
use Phil\CoursesBundle\Domain\Model\Activity;
use Phil\CoursesBundle\Domain\Model\Enrollment;
use Phil\CoursesBundle\Domain\Model\LaunchSession;
use Phil\CoursesBundle\Domain\Progress\CompletionRequirement;
use Phil\CoursesBundle\Domain\ValueObject\LaunchSessionId;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\CoursesBundle\Tests\Unit\Fixture\Json;
use Phil\CoursesBundle\Tests\Unit\Fixture\Statements;
use Phil\CoursesBundle\Tests\Unit\Fixture\Store;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The statements the platform writes, the tokens of the sessions, and the statements a unit sends, to the detail.
 */
#[CoversClass(PlatformStatements::class)]
#[CoversClass(StatementRules::class)]
#[CoversClass(Cmi5Tokens::class)]
#[CoversClass(Cmi5Statements::class)]
#[CoversClass(Cmi5Context::class)]
final class Cmi5SurvivorsTest extends TestCase
{
    private const string UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    public function testAStatementOfThePlatformIsWhole(): void
    {
        $context = $this->context();
        $statement = PlatformStatements::launched($context, 'https://content.example.org/a', new DateTimeImmutable('2026-11-13 10:00 UTC'));

        $id = $statement['id'];
        self::assertIsString($id);
        self::assertMatchesRegularExpression(self::UUID, $id);
        self::assertSame(['objectType' => 'Agent', 'account' => ['homePage' => 'https://learn.example.org/', 'name' => 'ada']], $statement['actor']);
        self::assertSame(['id' => Cmi5Verb::Launched->value, 'display' => ['en-US' => 'launched']], $statement['verb']);
        self::assertSame(['objectType' => 'Activity', 'id' => 'https://learn.example.org/courses/activities/a1'], $statement['object']);
        self::assertSame('r-1', Json::at($statement, 'context', 'registration'));
        self::assertSame([['id' => Cmi5Iri::CATEGORY]], Json::at($statement, 'context', 'contextActivities', 'category'));
        self::assertSame([
            Cmi5Iri::SESSION => 's-1',
            Cmi5Iri::LAUNCH_MODE => 'Normal',
            Cmi5Iri::LAUNCH_URL => 'https://content.example.org/a',
            Cmi5Iri::MOVE_ON => 'Completed',
        ], Json::at($statement, 'context', 'extensions'));
    }

    public function testAWaiverCarriesTheMoveOnCategory(): void
    {
        $statement = PlatformStatements::waived($this->context(), new DateTimeImmutable('2026-11-13 10:00 UTC'));

        self::assertSame([['id' => Cmi5Iri::CATEGORY], ['id' => Cmi5Iri::MOVE_ON_CATEGORY]], Json::at($statement, 'context', 'contextActivities', 'category'));
    }

    public function testAnAbandonedSessionLastsItsSecondsOverDays(): void
    {
        $statement = PlatformStatements::abandoned($this->context(), new DateTimeImmutable('2026-11-13 10:00:00 UTC'), new DateTimeImmutable('2026-11-14 12:03:04 UTC'));

        self::assertSame(['duration' => 'PT93784S'], $statement['result']);
        self::assertSame(['duration' => 'PT0S'], PlatformStatements::abandoned($this->context(), new DateTimeImmutable('2026-11-13 10:00:00 UTC'), new DateTimeImmutable('2026-11-13 10:00:00 UTC'))['result']);
        self::assertSame(['duration' => 'PT0S'], PlatformStatements::abandoned($this->context(), new DateTimeImmutable('2026-11-13 10:00:01 UTC'), new DateTimeImmutable('2026-11-13 10:00:00 UTC'))['result']);
    }

    public function testATokenIsTheSessionAndItsSignature(): void
    {
        $store = new Store();
        $id = LaunchSessionId::of('0192f0c1-5c5e-7a3b-9e1d-0123456789ab');

        self::assertSame(base64_encode($id->value.':'.hash_hmac('sha256', 'phil_courses.cmi5|'.$id->value, Store::SECRET)), $store->tokens->token($id));
    }

    public function testAnAuthorizationIsTheWholeHeader(): void
    {
        $store = new Store();
        $session = new LaunchSession(LaunchSessionId::of('0192f0c1-5c5e-7a3b-9e1d-0123456789ab'), $this->enrollment($store)->id, $this->activity($store)->id, $store->clock->now(), $store->clock->now()->modify('+1 hour'), true);
        $store->sessionStore->save($session);
        $token = $store->tokens->token($session->id);
        $signature = hash_hmac('sha256', 'phil_courses.cmi5|'.$session->id->value, Store::SECRET);

        self::assertSame($session->id->value, $store->tokens->authenticate('Basic '.$token)?->id->value);

        foreach (['xBasic '.$token, 'Basic '.$token.' trailing', 'Basic '.base64_encode($session->id->value.':'.$signature."\n")] as $header) {
            self::assertNull($store->tokens->authenticate($header), $header);
        }
    }

    public function testAStatementOfTheUnitIsStoredAsWrittenAndToldWithoutADispatcher(): void
    {
        $store = new Store();
        $quiet = new Cmi5Statements($store->lrs, $store->sessionStore, $store->enrollmentStore, $store->courseStore, $store->enrollments, $store->settings, $store->clock);

        $quiet->issue(['id' => '0192f0c1-5c5e-4a3b-9e1d-0123456789ab', 'verb' => ['display' => 'x'], 'object' => ['id' => 'https://example.org/é'], 'result' => ['score' => ['scaled' => 1.0]]], 'r-1', 's-1');
        self::assertSame('{"id":"0192f0c1-5c5e-4a3b-9e1d-0123456789ab","verb":{"display":"x"},"object":{"id":"https://example.org/é"},"result":{"score":{"scaled":1.0}}}', $store->connection->fetchOne('SELECT body FROM phil_course_statement'));

        $store->statements->issue(['verb' => ['id' => 5]], 'r-1', 's-1');
        self::assertSame('', $store->events->of(StatementRecorded::class)[0]->verb);
    }

    public function testABodyOfTheLimitIsRead(): void
    {
        $store = new Store();
        $session = $this->openSession($store);
        $statement = (string) json_encode(Statements::of($store->statements->context($session), Cmi5Verb::Initialized));
        $body = str_pad($statement, Cmi5Statements::MAX_BYTES, ' ');

        self::assertCount(1, $store->statements->receive($session, $body));

        $this->expectException(StatementRefused::class);
        $this->expectExceptionMessageIsOrContains('bytes of statements at most');
        $store->statements->receive($session, $body.' ');
    }

    public function testAStatementWithoutScoreKeepsNoScoreAndTheCourseIsSatisfiedOnlyOnceCompleted(): void
    {
        $store = new Store();
        $session = $this->openSession($store, 2);
        $context = $store->statements->context($session);
        $store->statements->receive($session, (string) json_encode(Statements::of($context, Cmi5Verb::Initialized)));
        $session = $store->sessionStore->find($session->id) ?? $session;
        $store->statements->receive($session, (string) json_encode(Statements::of($context, Cmi5Verb::Completed, ['completion' => true, 'duration' => 'PT1M'])));

        self::assertNull($store->enrollmentStore->progress($session->enrollment, $session->activity)?->score);
        self::assertSame(0, Store::count($store->connection, 'SELECT COUNT(*) FROM phil_course_statement WHERE body LIKE ?', ['%'.Cmi5Verb::Satisfied->value.'%']));
    }

    public function testTheMasteryOfAUnitIsANumber(): void
    {
        $store = new Store();
        $activity = $store->courses->addActivity($store->courses->addModule($store->courses->create('C')->id, 'M')->id, 'U', 'cmi5', ['au_id' => 'https://example.org/au', 'url' => 'https://example.org/a', 'mastery_score' => 1]);
        $enrollment = $store->enrollments->enroll($activity->course, LearnerId::of('ada'));
        $session = new LaunchSession(LaunchSessionId::generate(), $enrollment->id, $activity->id, $store->clock->now(), $store->clock->now());

        self::assertSame(1.0, Cmi5Context::of($session, $enrollment, $activity, Store::HOME)->masteryScore);
    }

    private function context(): Cmi5Context
    {
        return new Cmi5Context('https://learn.example.org/', 'ada', 'https://learn.example.org/courses/activities/a1', 'https://example.org/au', 'https://learn.example.org/courses/c1', 'r-1', 's-1', CompletionRequirement::Completed, null);
    }

    private function activity(Store $store): Activity
    {
        $course = $store->courses->all()[0] ?? $store->courses->create('C');
        $module = $store->courseStore->modules($course->id)[0] ?? $store->courses->addModule($course->id, 'M');

        return $store->courseStore->activities($course->id)[0] ?? $store->courses->addActivity($module->id, 'U', 'cmi5', ['au_id' => 'https://example.org/au', 'url' => 'https://example.org/a'], CompletionRequirement::Completed);
    }

    private function enrollment(Store $store): Enrollment
    {
        return $store->enrollments->enroll($this->activity($store)->course, LearnerId::of('ada'));
    }

    /**
     * A fetched session of a unit, in a course of that many units.
     */
    private function openSession(Store $store, int $units = 1): LaunchSession
    {
        $unit = $this->activity($store);

        for ($index = 1; $index < $units; ++$index) {
            $store->courses->addActivity($unit->module, 'Other '.$index, 'page');
        }

        $enrollment = $this->enrollment($store);
        $store->learning->launch($enrollment, $unit->id);
        $session = $store->sessionStore->open($enrollment->id, $unit->id)[0];
        $store->tokens->fetch($session->id->value);

        return $store->sessionStore->find($session->id) ?? $session;
    }
}
