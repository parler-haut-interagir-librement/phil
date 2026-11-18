<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Cmi5;

use DateTimeImmutable;
use Phil\CoursesBundle\Application\Cmi5\Cmi5Context;
use Phil\CoursesBundle\Application\Cmi5\StatementRules;
use Phil\CoursesBundle\Domain\Cmi5\Cmi5Iri;
use Phil\CoursesBundle\Domain\Cmi5\Cmi5Verb;
use Phil\CoursesBundle\Domain\Cmi5\SessionState;
use Phil\CoursesBundle\Domain\Exception\StatementRefused;
use Phil\CoursesBundle\Domain\Model\Progress;
use Phil\CoursesBundle\Domain\Progress\CompletionRequirement;
use Phil\CoursesBundle\Domain\ValueObject\ActivityId;
use Phil\CoursesBundle\Domain\ValueObject\EnrollmentId;
use Phil\CoursesBundle\Tests\Unit\Fixture\Statements;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(StatementRules::class)]
#[CoversClass(StatementRefused::class)]
final class StatementRulesTest extends TestCase
{
    public static function context(?float $mastery = 0.8): Cmi5Context
    {
        return new Cmi5Context('https://learn.example.org', 'ada', 'https://learn.example.org/courses/activities/a1', 'https://example.org/aus/symbols', 'https://learn.example.org/courses/c1', 'a7d1c6b8-0e0f-4c3a-9a37-2b8f1d4e5c6a', 's-1', CompletionRequirement::CompletedAndPassed, $mastery);
    }

    public function testTheStatementsOfASessionAreAcceptedInOrder(): void
    {
        $context = self::context();

        self::assertSame(Cmi5Verb::Initialized, StatementRules::check(Statements::of($context, Cmi5Verb::Initialized) + ['id' => 'A7D1C6B8-0E0F-4C3A-9A37-2B8F1D4E5C6A'], $context, SessionState::Launched, null));
        self::assertNull(StatementRules::check(Statements::of($context, 'http://adlnet.gov/expapi/verbs/answered'), $context, SessionState::Initialized, null));
        self::assertSame(Cmi5Verb::Completed, StatementRules::check(Statements::completed($context), $context, SessionState::Initialized, null));
        self::assertSame(Cmi5Verb::Failed, StatementRules::check(Statements::failed($context, 0.79), $context, SessionState::Initialized, null));
        self::assertSame(Cmi5Verb::Passed, StatementRules::check(Statements::passed($context, 0.8), $context, SessionState::Initialized, self::progress(completed: true, success: false)));
        self::assertSame(Cmi5Verb::Passed, StatementRules::check(Statements::passed($context, 1), $context, SessionState::Initialized, null));
        self::assertSame(Cmi5Verb::Terminated, StatementRules::check(Statements::of($context, Cmi5Verb::Terminated, ['duration' => 'P1DT2H3M4.5S']), $context, SessionState::Initialized, null));
        self::assertSame(Cmi5Verb::Passed, StatementRules::check(Statements::of($context, Cmi5Verb::Passed, ['success' => true, 'duration' => 'PT1S']), self::context(null), SessionState::Initialized, null));
        self::assertSame(Cmi5Verb::Failed, StatementRules::check(Statements::of($context, Cmi5Verb::Failed, ['success' => false, 'duration' => 'P2W', 'score' => ['scaled' => -1]]), self::context(null), SessionState::Initialized, null));
        self::assertSame(Cmi5Verb::Completed, StatementRules::check(Statements::completed($context), $context, SessionState::Initialized, self::progress()));
    }

    /**
     * @return iterable<string, array{callable(Cmi5Context): array<array-key, mixed>, SessionState, ?Progress, string}>
     */
    public static function refusals(): iterable
    {
        $set = self::with(...);
        $without = static function (array $statement, string ...$keys): array {
            foreach ($keys as $key) {
                unset($statement[$key]);
            }

            return $statement;
        };
        $initialized = static fn (Cmi5Context $context): array => Statements::of($context, Cmi5Verb::Initialized);

        yield 'id' => [static fn (Cmi5Context $context): array => ['id' => 'not-a-uuid'] + $initialized($context), SessionState::Launched, null, 'The id of a statement is a UUID.'];
        yield 'id not text' => [static fn (Cmi5Context $context): array => ['id' => 12] + $initialized($context), SessionState::Launched, null, 'The id of a statement is a UUID.'];
        yield 'no actor' => [static fn (Cmi5Context $context): array => $without($initialized($context), 'actor'), SessionState::Launched, null, 'A statement has its "actor" as an object.'];
        yield 'no account' => [static fn (Cmi5Context $context): array => $set($initialized($context), ['actor'], ['mbox' => 'mailto:ada@example.org']), SessionState::Launched, null, 'A statement has its "account" as an object.'];
        yield 'mbox beside' => [static fn (Cmi5Context $context): array => $set($initialized($context), ['actor', 'mbox'], 'mailto:ada@example.org'), SessionState::Launched, null, 'The actor of a statement is the agent the unit was launched with.'];
        yield 'openid beside' => [static fn (Cmi5Context $context): array => $set($initialized($context), ['actor', 'openid'], 'https://id.example.org/ada'), SessionState::Launched, null, 'The actor of a statement is the agent the unit was launched with.'];
        yield 'group' => [static fn (Cmi5Context $context): array => $set($initialized($context), ['actor', 'objectType'], 'Group'), SessionState::Launched, null, 'The actor of a statement is the agent the unit was launched with.'];
        yield 'another learner' => [static fn (Cmi5Context $context): array => $set($initialized($context), ['actor', 'account', 'name'], 'bob'), SessionState::Launched, null, 'The actor of a statement is the agent the unit was launched with.'];
        yield 'another site' => [static fn (Cmi5Context $context): array => $set($initialized($context), ['actor', 'account', 'homePage'], 'https://evil.example.org'), SessionState::Launched, null, 'The actor of a statement is the agent the unit was launched with.'];
        yield 'no context' => [static fn (Cmi5Context $context): array => $without($initialized($context), 'context'), SessionState::Launched, null, 'A statement has its "context" as an object.'];
        yield 'registration' => [static fn (Cmi5Context $context): array => $set($initialized($context), ['context', 'registration'], 'other'), SessionState::Launched, null, 'The registration of a statement is the one the unit was launched with.'];
        yield 'no extensions' => [static fn (Cmi5Context $context): array => $set($initialized($context), ['context', 'extensions'], null), SessionState::Launched, null, 'A statement has its "extensions" as an object.'];
        yield 'session' => [static fn (Cmi5Context $context): array => $set($initialized($context), ['context', 'extensions', Cmi5Iri::SESSION], 's-2'), SessionState::Launched, null, 'The session of a statement is the one the unit was launched with.'];
        yield 'no object' => [static fn (Cmi5Context $context): array => $without($initialized($context), 'object'), SessionState::Launched, null, 'A statement has its "object" as an object.'];
        yield 'object without id' => [static fn (Cmi5Context $context): array => $set($initialized($context), ['object', 'id'], ''), SessionState::Launched, null, 'The object of a statement has its "id".'];
        yield 'no verb' => [static fn (Cmi5Context $context): array => $without($initialized($context), 'verb'), SessionState::Launched, null, 'A statement has its "verb" as an object.'];
        yield 'verb without id' => [static fn (Cmi5Context $context): array => $set($initialized($context), ['verb', 'id'], 3), SessionState::Launched, null, 'The verb of a statement has its "id".'];
        yield 'other verb first' => [static fn (Cmi5Context $context): array => Statements::of($context, 'http://adlnet.gov/expapi/verbs/answered'), SessionState::Launched, null, 'A statement of another verb comes between "initialized" and "terminated".'];
        yield 'other verb after' => [static fn (Cmi5Context $context): array => Statements::of($context, 'http://adlnet.gov/expapi/verbs/answered'), SessionState::Terminated, null, 'A statement of another verb comes between "initialized" and "terminated".'];
        yield 'launched by the unit' => [static fn (Cmi5Context $context): array => Statements::of($context, Cmi5Verb::Launched), SessionState::Launched, null, 'Only the platform issues "launched".'];
        yield 'satisfied by the unit' => [static fn (Cmi5Context $context): array => Statements::of($context, Cmi5Verb::Satisfied), SessionState::Initialized, null, 'Only the platform issues "satisfied".'];
        yield 'initialized twice' => [$initialized, SessionState::Initialized, null, 'A session is initialized once.'];
        yield 'completed first' => [Statements::completed(...), SessionState::Launched, null, 'The first statement of a session is "initialized".'];
        yield 'after terminated' => [Statements::completed(...), SessionState::Terminated, null, 'The session is ended: no statement follows "terminated".'];
        yield 'after abandoned' => [$initialized, SessionState::Abandoned, null, 'The session is ended'];
        yield 'another activity' => [static fn (Cmi5Context $context): array => $set(Statements::completed($context), ['object', 'id'], 'https://example.org/aus/symbols'), SessionState::Initialized, null, 'The object of a statement cmi5 defines is the activity the unit was launched as.'];
        yield 'no category' => [static fn (Cmi5Context $context): array => $set(Statements::completed($context), ['context', 'contextActivities'], []), SessionState::Initialized, null, 'A statement cmi5 defines carries the "cmi5" category.'];
        yield 'odd categories' => [static fn (Cmi5Context $context): array => $set(Statements::completed($context), ['context', 'contextActivities', 'category'], ['x', ['id' => Cmi5Iri::MOVE_ON_CATEGORY]]), SessionState::Initialized, null, 'A statement cmi5 defines carries the "cmi5" category.'];
        yield 'no move on' => [static fn (Cmi5Context $context): array => $set(Statements::completed($context), ['context', 'contextActivities', 'category'], [['id' => Cmi5Iri::CATEGORY]]), SessionState::Initialized, null, 'A "completed" statement carries the "moveon" category.'];
        yield 'no duration' => [static fn (Cmi5Context $context): array => Statements::of($context, Cmi5Verb::Completed, ['completion' => true]), SessionState::Initialized, null, 'A "completed" statement gives its duration in ISO 8601.'];
        yield 'bad duration' => [static fn (Cmi5Context $context): array => Statements::of($context, Cmi5Verb::Completed, ['completion' => true, 'duration' => 'P']), SessionState::Initialized, null, 'gives its duration in ISO 8601'];
        yield 'duration with T only' => [static fn (Cmi5Context $context): array => Statements::of($context, Cmi5Verb::Terminated, ['duration' => 'PT']), SessionState::Initialized, null, 'A "terminated" statement gives its duration in ISO 8601.'];
        yield 'duration in words' => [static fn (Cmi5Context $context): array => Statements::of($context, Cmi5Verb::Terminated, ['duration' => 'five minutes']), SessionState::Initialized, null, 'A "terminated" statement gives its duration'];
        yield 'duration trailing' => [static fn (Cmi5Context $context): array => Statements::of($context, Cmi5Verb::Terminated, ['duration' => "PT5M\n"]), SessionState::Initialized, null, 'A "terminated" statement gives its duration'];
        yield 'no completion' => [static fn (Cmi5Context $context): array => Statements::of($context, Cmi5Verb::Completed, ['duration' => 'PT1M']), SessionState::Initialized, null, 'A "completed" statement sets its completion to true.'];
        yield 'completed twice' => [Statements::completed(...), SessionState::Initialized, self::progress(completed: true), 'The unit is completed already in this registration.'];
        yield 'passed without success' => [static fn (Cmi5Context $context): array => Statements::of($context, Cmi5Verb::Passed, ['duration' => 'PT1M', 'score' => ['scaled' => 0.9]]), SessionState::Initialized, null, 'A "passed" statement sets its success to true.'];
        yield 'failed with success' => [static fn (Cmi5Context $context): array => Statements::of($context, Cmi5Verb::Failed, ['success' => true, 'duration' => 'PT1M']), SessionState::Initialized, null, 'A "failed" statement sets its success to false.'];
        yield 'passed twice' => [static fn (Cmi5Context $context): array => Statements::passed($context), SessionState::Initialized, self::progress(success: true), 'The unit is passed already in this registration: no "passed" follows.'];
        yield 'failed after passed' => [static fn (Cmi5Context $context): array => Statements::failed($context), SessionState::Initialized, self::progress(success: true), 'The unit is passed already in this registration: no "failed" follows.'];
        yield 'passed without score' => [static fn (Cmi5Context $context): array => Statements::of($context, Cmi5Verb::Passed, ['success' => true, 'duration' => 'PT1M']), SessionState::Initialized, null, 'A "passed" statement of a unit with a mastery score gives its scaled score.'];
        yield 'passed below' => [static fn (Cmi5Context $context): array => Statements::passed($context, 0.79), SessionState::Initialized, null, 'The scaled score of a "passed" statement is at least the mastery score.'];
        yield 'failed above' => [static fn (Cmi5Context $context): array => Statements::failed($context, 0.8), SessionState::Initialized, null, 'The scaled score of a "failed" statement is below the mastery score.'];
        yield 'score above one' => [static fn (Cmi5Context $context): array => Statements::of($context, Cmi5Verb::Completed, ['completion' => true, 'duration' => 'PT1M', 'score' => ['scaled' => 1.5]]), SessionState::Initialized, null, 'A scaled score is a number from -1 to 1.'];
        yield 'score below minus one' => [static fn (Cmi5Context $context): array => Statements::of($context, Cmi5Verb::Completed, ['completion' => true, 'duration' => 'PT1M', 'score' => ['scaled' => -1.01]]), SessionState::Initialized, null, 'A scaled score is a number from -1 to 1.'];
        yield 'score as text' => [static fn (Cmi5Context $context): array => Statements::of($context, Cmi5Verb::Completed, ['completion' => true, 'duration' => 'PT1M', 'score' => ['scaled' => '0.5']]), SessionState::Initialized, null, 'A scaled score is a number from -1 to 1.'];
    }

    /**
     * @param callable(Cmi5Context): array<array-key, mixed> $statement
     */
    #[DataProvider('refusals')]
    public function testAStatementBreakingARuleIsRefused(callable $statement, SessionState $state, ?Progress $progress, string $message): void
    {
        $this->expectException(StatementRefused::class);
        $this->expectExceptionMessageIsOrContains($message);

        StatementRules::check($statement(self::context()), self::context(), $state, $progress);
    }

    public function testAScoreWithoutScaledIsNoScore(): void
    {
        $context = self::context(null);

        self::assertSame(Cmi5Verb::Completed, StatementRules::check(Statements::of($context, Cmi5Verb::Completed, ['completion' => true, 'duration' => 'PT1M', 'score' => ['raw' => 3]]), $context, SessionState::Initialized, null));
        self::assertSame(Cmi5Verb::Completed, StatementRules::check(Statements::of($context, Cmi5Verb::Completed, ['completion' => true, 'duration' => 'PT1M', 'score' => 'high']), $context, SessionState::Initialized, null));

        $this->expectException(StatementRefused::class);
        $this->expectExceptionMessageIsOrContains('A "completed" statement gives its duration in ISO 8601.');
        StatementRules::check(['result' => 'odd'] + Statements::completed($context), $context, SessionState::Initialized, null);
    }

    /**
     * The statement with the value at that path.
     *
     * @param array<array-key, mixed> $statement
     * @param list<string>            $path
     *
     * @return array<array-key, mixed>
     */
    private static function with(array $statement, array $path, mixed $value): array
    {
        $key = array_shift($path);

        if (null === $key) {
            return $statement;
        }

        $statement[$key] = [] === $path ? $value : self::with(\is_array($statement[$key] ?? null) ? $statement[$key] : [], $path, $value);

        return $statement;
    }

    private static function progress(bool $completed = false, ?bool $success = null): Progress
    {
        return new Progress(EnrollmentId::of('e'), ActivityId::of('a1'), new DateTimeImmutable('2026-11-13 10:00 UTC'), $completed, $success);
    }
}
