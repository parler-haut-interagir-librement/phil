<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Scorm;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Phil\CoursesBundle\Application\Scorm\CommitAnswer;
use Phil\CoursesBundle\Application\Scorm\ScormLaunch;
use Phil\CoursesBundle\Application\Scorm\ScormRuntime;
use Phil\CoursesBundle\Application\Scorm\ScormTokens;
use Phil\CoursesBundle\Domain\Event\ActivityProgressed;
use Phil\CoursesBundle\Domain\Exception\AttemptRefused;
use Phil\CoursesBundle\Domain\Model\ScormAttempt;
use Phil\CoursesBundle\Domain\ValueObject\ActivityId;
use Phil\CoursesBundle\Domain\ValueObject\EnrollmentId;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\CoursesBundle\Domain\ValueObject\ScormAttemptId;
use Phil\CoursesBundle\Infrastructure\Doctrine\CourseTables;
use Phil\CoursesBundle\Tests\Unit\Fixture\Store;
use Phil\ElearningPackages\Runtime\Duration;
use Phil\ElearningPackages\Runtime\ScormVersion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * What the first tests of the SCORM run time left open: the outline that navigation requests follow, the attempts whose
 * records are gone, the signature of the tokens, and the defaults of the answers.
 */
#[CoversClass(ScormRuntime::class)]
#[CoversClass(ScormTokens::class)]
#[CoversClass(ScormLaunch::class)]
#[CoversClass(CommitAnswer::class)]
#[CoversClass(ScormAttempt::class)]
#[CoversClass(AttemptRefused::class)]
#[CoversClass(CourseTables::class)]
final class ScormSurvivorsTest extends TestCase
{
    private Store $store;

    protected function setUp(): void
    {
        $this->store = new Store();
    }

    public function testNavigationRequestsFollowTheScormActivitiesOfTheOutline(): void
    {
        $course = $this->store->courses->create('Outline');
        $module = $this->store->courses->addModule($course->id, 'M');
        $this->store->courses->addActivity($module->id, 'Intro', 'page');
        $first = $this->store->courses->addActivity($module->id, 'One', 'scorm', ['path' => 'a.html', 'scorm_version' => 'scorm2004'], source: 'one');
        $this->store->courses->addActivity($module->id, 'Between', 'page');
        $second = $this->store->courses->addActivity($module->id, 'Two', 'scorm', ['path' => 'b.html', 'scorm_version' => 'scorm2004']);
        $third = $this->store->courses->addActivity($module->id, 'Three', 'scorm', ['path' => 'c.html', 'scorm_version' => 'scorm2004'], source: 'three');
        $enrollment = $this->store->enrollments->enroll($course->id, LearnerId::of('ada'));

        $flags = [];
        $answers = [];

        foreach ([[$first, 'continue'], [$second, 'previous'], [$second, 'continue'], [$third, 'continue'], [$third, 'previous'], [$first, '{target=three}choice'], [$first, '{target=nowhere}jump'], [$first, 'exit']] as $index => [$activity, $request]) {
            $launch = $this->store->scorm->launch($activity, $enrollment);
            $flags[$index] = [$launch->values['adl.nav.request_valid.previous'], $launch->values['adl.nav.request_valid.continue']];
            $answer = $this->store->scorm->commit($launch->token, ['adl.nav.request' => $request], true);
            $answers[$index] = [$answer->next?->value, $answer->exit];
        }

        self::assertSame([['false', 'true'], ['true', 'true'], ['true', 'true'], ['true', 'false'], ['true', 'false'], ['false', 'true'], ['false', 'true'], ['false', 'true']], $flags);
        self::assertSame([[$second->id->value, false], [$first->id->value, false], [$third->id->value, false], [null, true], [$second->id->value, false], [$third->id->value, false], [null, true], [null, true]], $answers);
    }

    public function testARequestKeptWithSomethingAfterItsTargetLeavesForTheOutline(): void
    {
        $course = $this->store->courses->create('Targets');
        $module = $this->store->courses->addModule($course->id, 'M');
        $first = $this->store->courses->addActivity($module->id, 'One', 'scorm', ['path' => 'a.html', 'scorm_version' => 'scorm2004']);
        $this->store->courses->addActivity($module->id, 'Two', 'scorm', ['path' => 'b.html', 'scorm_version' => 'scorm2004'], source: 'two');
        $enrollment = $this->store->enrollments->enroll($course->id, LearnerId::of('ada'));

        foreach (['{target=two}choice' => false, "{target=two}choice\n" => true, '{target=two}choicex' => true] as $request => $leaves) {
            $launch = $this->store->scorm->launch($first, $enrollment);
            $attempt = $this->store->attemptStore->find($launch->attempt);
            self::assertNotNull($attempt);
            $attempt->open($attempt->data + ['adl.nav.request' => $request], $this->store->clock->now());
            $this->store->attemptStore->save($attempt);

            $answer = $this->store->scorm->commit($this->store->scormTokens->issue($attempt->id, $attempt->session), [], true);

            self::assertSame([$leaves, !$leaves], [$answer->exit, null !== $answer->next], $request);
        }
    }

    public function testASessionThatReportsNothingRecordsNothingAndAnAnswerWithoutRequestStays(): void
    {
        $course = $this->store->courses->create('Silent');
        $activity = $this->store->courses->addActivity($this->store->courses->addModule($course->id, 'M')->id, 'A', 'scorm', ['path' => 'a.html', 'scorm_version' => 'scorm2004']);
        $enrollment = $this->store->enrollments->enroll($course->id, LearnerId::of('ada'));
        $launch = $this->store->scorm->launch($activity, $enrollment);
        $this->store->events->events = [];

        $answer = $this->store->scorm->commit($launch->token, [], true);

        self::assertEquals(new CommitAnswer([], null, true), $answer);
        self::assertSame([], $this->store->enrollments->progresses($enrollment));
        self::assertSame([], $this->store->events->of(ActivityProgressed::class));
    }

    public function testTheRunTimeWorksWithoutADispatcher(): void
    {
        $runtime = new ScormRuntime($this->store->attemptStore, $this->store->courseStore, $this->store->enrollmentStore, $this->store->enrollments, $this->store->scormTokens, $this->store->clock);
        $course = $this->store->courses->create('Quiet');
        $activity = $this->store->courses->addActivity($this->store->courses->addModule($course->id, 'M')->id, 'A', 'scorm', ['path' => 'a.html', 'scorm_version' => 'scorm12']);
        $launch = $runtime->launch($activity, $this->store->enrollments->enroll($course->id, LearnerId::of('ada')));

        self::assertTrue($runtime->commit($launch->token, ['cmi.core.lesson_status' => 'passed'], true)->ended);
    }

    public function testACompletionThresholdBetweenZeroAndOneIsKept(): void
    {
        $course = $this->store->courses->create('Threshold');
        $activity = $this->store->courses->addActivity($this->store->courses->addModule($course->id, 'M')->id, 'A', 'scorm', ['path' => 'a.html', 'scorm_version' => 'scorm2004', 'completion_threshold' => 0.5]);
        $launch = $this->store->scorm->launch($activity, $this->store->enrollments->enroll($course->id, LearnerId::of('ada')));

        self::assertSame('0.5', $launch->values['cmi.completion_threshold']);
    }

    public function testAnAttemptWhoseEnrollmentOrActivityIsGoneIsRefused(): void
    {
        $course = $this->store->courses->create('Gone');
        $activity = $this->store->courses->addActivity($this->store->courses->addModule($course->id, 'M')->id, 'A', 'scorm', ['path' => 'a.html', 'scorm_version' => 'scorm12']);
        $enrollment = $this->store->enrollments->enroll($course->id, LearnerId::of('ada'));

        foreach ([[EnrollmentId::generate(), $activity->id], [$enrollment->id, ActivityId::generate()]] as [$enrolled, $named]) {
            $attempt = new ScormAttempt(ScormAttemptId::generate(), $enrolled, $named, 1, ScormVersion::Scorm12, $this->store->clock->now(), session: 1);
            $this->store->attemptStore->save($attempt);

            try {
                $this->store->scorm->commit($this->store->scormTokens->issue($attempt->id, 1), [], false);
                self::fail('A commit of an attempt without its records is taken.');
            } catch (AttemptRefused $attemptRefused) {
                self::assertSame('The attempt of this token is unknown.', $attemptRefused->getMessage());
            }
        }
    }

    public function testTheTokensAreSignedUnderTheirOwnDomainAndReadWhole(): void
    {
        $id = ScormAttemptId::of('01890000-0000-7000-8000-000000000000');
        $token = new ScormTokens($this->store->clock, Store::SECRET, 600)->issue($id, 7);
        [$payload, $signature] = [substr($token, 0, -44), substr($token, -43)];

        self::assertSame(sodium_bin2base64(hash_hmac('sha256', 'phil_courses.scorm|'.$payload, Store::SECRET, true), \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING), $signature);
        self::assertSame($id->value.'.7.'.($this->store->clock->now()->getTimestamp() + 600), $payload);

        foreach (['zz'.$token, $token."\n"] as $refused) {
            try {
                $this->store->scormTokens->verify($refused);
                self::fail('The token is accepted: '.$refused);
            } catch (AttemptRefused $attemptRefused) {
                self::assertSame('The attempt token is not one this site gives.', $attemptRefused->getMessage());
            }
        }
    }

    public function testTheAnswersAndTheAttemptsStartFromTheirDefaults(): void
    {
        $answer = new CommitAnswer();
        self::assertSame([[], null, false, null, false], [$answer->errors, $answer->token, $answer->ended, $answer->next, $answer->exit]);
        self::assertFalse(new ScormLaunch(ScormAttemptId::generate(), 1, 't', ScormVersion::Scorm12, [], [])->resumed);
        self::assertFalse(new AttemptRefused('x')->closed);

        $attempt = new ScormAttempt(ScormAttemptId::generate(), EnrollmentId::generate(), ActivityId::generate(), 1, ScormVersion::Scorm12, new DateTimeImmutable('2026-11-13 10:00:00'), totalTime: Duration::ofCentiseconds(500));
        self::assertSame(500, $attempt->totalTime->centiseconds);
        self::assertSame(0, new ScormAttempt(ScormAttemptId::generate(), EnrollmentId::generate(), ActivityId::generate(), 1, ScormVersion::Scorm12, new DateTimeImmutable('2026-11-13 10:00:00'))->totalTime->centiseconds);
    }

    public function testTheTablesOfAMySqlConnectionCompareTheKeysOfTheScormTablesAsBytes(): void
    {
        $platform = new MySQLPlatform();
        $manager = self::createStub(AbstractSchemaManager::class);
        $manager->method('tablesExist')->willReturn(false);
        $manager->method('createComparator')->willReturn(Store::sqlite()->createSchemaManager()->createComparator());
        $connection = self::createStub(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($platform);
        $connection->method('createSchemaManager')->willReturn($manager);

        self::assertStringContainsString('utf8mb4_bin', implode("\n", CourseTables::upgrade($connection)));
    }
}
