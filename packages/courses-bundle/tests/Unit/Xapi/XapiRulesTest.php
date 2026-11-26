<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Xapi;

use Phil\CoursesBundle\Application\Xapi\StatementBatch;
use Phil\CoursesBundle\Application\Xapi\XapiContext;
use Phil\CoursesBundle\Application\Xapi\XapiOutcomes;
use Phil\CoursesBundle\Application\Xapi\XapiRules;
use Phil\CoursesBundle\Domain\Exception\StatementRefused;
use Phil\CoursesBundle\Domain\Xapi\XapiVerb;
use Phil\CoursesBundle\Tests\Unit\Fixture\Json;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The checks of the statements of an xAPI activity, the outcome they report, and the bounds of a request's body.
 */
#[CoversClass(XapiRules::class)]
#[CoversClass(XapiOutcomes::class)]
#[CoversClass(StatementBatch::class)]
#[CoversClass(XapiContext::class)]
final class XapiRulesTest extends TestCase
{
    private const string ACTIVITY = 'https://example.org/xapi/knots';

    private const string REGISTRATION = '6f1c2a3b-4d5e-4f60-8a7b-9c0d1e2f3a4b';

    public function testAStatementOfTheLearnerIsAcceptedAndTakesTheRegistration(): void
    {
        $checked = XapiRules::check(self::statement(XapiVerb::Completed->value), $this->context());

        self::assertSame(['registration' => self::REGISTRATION], $checked['context']);
        self::assertSame(['objectType' => 'Agent', 'account' => ['homePage' => 'https://learn.example.org/', 'name' => 'ada']], $this->context()->actor());

        $withContext = XapiRules::check(['id' => '0a1b2c3d-4e5f-4a6b-8c7d-9e0f1a2b3c4d', 'context' => ['registration' => self::REGISTRATION, 'language' => 'fr']] + self::statement('https://example.org/verbs/read', self::ACTIVITY.'/page-2'), $this->context());
        self::assertSame(['registration' => self::REGISTRATION, 'language' => 'fr'], $withContext['context']);
        self::assertSame('0a1b2c3d-4e5f-4a6b-8c7d-9e0f1a2b3c4d', $withContext['id']);

        $reference = XapiRules::check(['object' => ['objectType' => 'StatementRef', 'id' => '0a1b2c3d-4e5f-4a6b-8c7d-9e0f1a2b3c4d']] + self::statement('https://example.org/verbs/liked'), $this->context());
        self::assertSame('StatementRef', Json::at($reference, 'object', 'objectType'));

        $scored = XapiRules::check(['result' => ['score' => ['scaled' => -1]]] + self::statement(XapiVerb::Failed->value), $this->context());
        self::assertSame(-1, Json::at($scored, 'result', 'score', 'scaled'));
        self::assertSame(['result' => ['score' => ['scaled' => 1.0]]], array_intersect_key(XapiRules::check(['result' => ['score' => ['scaled' => 1.0]]] + self::statement(XapiVerb::Passed->value), $this->context()), ['result' => true]));
        self::assertSame(['result' => ['success' => true]], array_intersect_key(XapiRules::check(['result' => ['success' => true]] + self::statement(XapiVerb::Passed->value), $this->context()), ['result' => true]));
        self::assertSame(['result' => ['score' => []]], array_intersect_key(XapiRules::check(['result' => ['score' => []]] + self::statement(XapiVerb::Passed->value), $this->context()), ['result' => true]));
    }

    /**
     * @param array<array-key, mixed> $statement
     */
    #[DataProvider('refused')]
    public function testAStatementOutsideTheRulesIsRefused(array $statement, string $reason): void
    {
        $this->expectException(StatementRefused::class);
        $this->expectExceptionMessageIsOrContains($reason);

        XapiRules::check($statement, $this->context());
    }

    /**
     * @return iterable<string, array{0: array<array-key, mixed>, 1: string}>
     */
    public static function refused(): iterable
    {
        $statement = self::statement(XapiVerb::Completed->value);

        yield 'an identifier of another shape' => [['id' => 'not-a-uuid'] + $statement, 'The "id" of a statement is a UUID.'];
        yield 'an identifier that is no text' => [['id' => 12] + $statement, 'The "id" of a statement is a UUID.'];
        yield 'another learner' => [['actor' => ['account' => ['homePage' => 'https://learn.example.org/', 'name' => 'eve']]] + $statement, 'The actor of a statement is the learner of the session'];
        yield 'another site' => [['actor' => ['account' => ['homePage' => 'https://evil.example.net/', 'name' => 'ada']]] + $statement, 'The actor of a statement is the learner of the session'];
        yield 'a group' => [['actor' => ['objectType' => 'Group', 'account' => ['homePage' => 'https://learn.example.org/', 'name' => 'ada']]] + $statement, 'The actor of a statement is the learner of the session'];
        yield 'an e-mail address' => [['actor' => ['mbox' => 'mailto:ada@example.org']] + $statement, 'The actor of a statement is the learner of the session'];
        yield 'an actor that is no object' => [['actor' => 'ada'] + $statement, 'The actor of a statement is the learner of the session'];
        yield 'no actor' => [array_diff_key($statement, ['actor' => true]), 'The actor of a statement is the learner of the session'];
        yield 'a verb without an IRI' => [['verb' => ['id' => 'completed']] + $statement, 'The verb of a statement is named by an IRI.'];
        yield 'a verb with a space' => [['verb' => ['id' => 'http://example.org/a verb']] + $statement, 'The verb of a statement is named by an IRI.'];
        yield 'a verb too long' => [['verb' => ['id' => 'https://example.org/'.str_repeat('a', 2029)]] + $statement, 'The verb of a statement is named by an IRI.'];
        yield 'a verb that is no object' => [['verb' => XapiVerb::Completed->value] + $statement, 'The verb of a statement is named by an IRI.'];
        yield 'a voiding statement' => [['verb' => ['id' => XapiVerb::Voided->value], 'object' => ['objectType' => 'StatementRef', 'id' => '0a1b2c3d-4e5f-4a6b-8c7d-9e0f1a2b3c4d']] + $statement, 'This store does not void statements'];
        yield 'an agent as object' => [['object' => ['objectType' => 'Agent', 'id' => self::ACTIVITY]] + $statement, 'The object of a statement is an activity, or a reference to a statement.'];
        yield 'an object that is no object' => [['object' => self::ACTIVITY] + $statement, 'The object of a statement is an activity, or a reference to a statement.'];
        yield 'an activity without an identifier' => [['object' => ['objectType' => 'Activity']] + $statement, 'The object of a statement is named by its identifier.'];
        yield 'a reference without a UUID' => [['object' => ['objectType' => 'StatementRef', 'id' => self::ACTIVITY]] + $statement, 'The object of a statement is named by its identifier.'];
        yield 'a reference that is no text' => [['object' => ['objectType' => 'StatementRef', 'id' => 7]] + $statement, 'The object of a statement is named by its identifier.'];
        yield 'a score above 1' => [['result' => ['score' => ['scaled' => 1.01]]] + $statement, 'A scaled score is a number from -1 to 1.'];
        yield 'a score below -1' => [['result' => ['score' => ['scaled' => -1.5]]] + $statement, 'A scaled score is a number from -1 to 1.'];
        yield 'a score as text' => [['result' => ['score' => ['scaled' => '0.5']]] + $statement, 'A scaled score is a number from -1 to 1.'];
        yield 'a context that is no object' => [['context' => 'x'] + $statement, 'The context of a statement is an object.'];
        yield 'another registration' => [['context' => ['registration' => '0a1b2c3d-4e5f-4a6b-8c7d-9e0f1a2b3c4d']] + $statement, 'The registration of a statement is the one of the launch.'];
    }

    public function testAnIriHasASchemeAndNoSpace(): void
    {
        self::assertTrue(XapiRules::isIri('urn:x:1'));
        self::assertTrue(XapiRules::isIri('https://example.org/'.str_repeat('a', 2028)));
        self::assertFalse(XapiRules::isIri('https://example.org/'.str_repeat('a', 2029)));
        self::assertFalse(XapiRules::isIri(''));
        self::assertFalse(XapiRules::isIri('1x:y'));
        self::assertFalse(XapiRules::isIri("x:y\n"));
        self::assertFalse(XapiRules::isIri('x:'));
        self::assertFalse(XapiRules::isIri(' x:y'));
        self::assertFalse(XapiRules::isIri(null));
    }

    public function testTheOutcomeFollowsTheVerbAndTheResult(): void
    {
        $context = $this->context();

        self::assertEquals([true, null, null], $this->outcome(self::statement(XapiVerb::Completed->value), $context));
        self::assertEquals([true, true, 0.5], $this->outcome(['result' => ['success' => true, 'score' => ['scaled' => 0.5]]] + self::statement(XapiVerb::Completed->value), $context));
        self::assertEquals([true, false, null], $this->outcome(['result' => ['success' => false]] + self::statement(XapiVerb::Completed->value), $context));
        self::assertEquals([true, null, null], $this->outcome(['result' => ['success' => 'yes']] + self::statement(XapiVerb::Completed->value), $context));
        self::assertEquals([false, true, 1.0], $this->outcome(['result' => ['score' => ['scaled' => 1]]] + self::statement(XapiVerb::Passed->value), $context));
        self::assertEquals([true, true, null], $this->outcome(['result' => ['completion' => true]] + self::statement(XapiVerb::Passed->value), $context));
        self::assertEquals([false, true, null], $this->outcome(['result' => ['completion' => 'true']] + self::statement(XapiVerb::Passed->value), $context));
        self::assertEquals([false, false, null], $this->outcome(['result' => ['success' => true]] + self::statement(XapiVerb::Failed->value), $context));
        self::assertNull(XapiOutcomes::of(self::statement('https://example.org/verbs/read'), $context));
        self::assertNull(XapiOutcomes::of(self::statement(XapiVerb::Completed->value, self::ACTIVITY.'/page-2'), $context));
        self::assertNull(XapiOutcomes::of(['verb' => 'x'] + self::statement(XapiVerb::Completed->value), $context));
        self::assertNull(XapiOutcomes::of(['object' => 'x'] + self::statement(XapiVerb::Completed->value), $context));
        self::assertEquals([true, null, null], $this->outcome(['result' => 'x'] + self::statement(XapiVerb::Completed->value), $context));
        self::assertEquals([true, null, null], $this->outcome(['result' => ['score' => 'x']] + self::statement(XapiVerb::Completed->value), $context));
    }

    public function testAMasteryScoreDecidesSuccessFromTheScaledScore(): void
    {
        $context = new XapiContext('https://learn.example.org/', 'ada', self::ACTIVITY, self::REGISTRATION, 'session', 0.8);

        self::assertEquals([true, true, 0.8], $this->outcome(['result' => ['score' => ['scaled' => 0.8]]] + self::statement(XapiVerb::Completed->value), $context));
        self::assertEquals([true, false, 0.79], $this->outcome(['result' => ['success' => true, 'score' => ['scaled' => 0.79]]] + self::statement(XapiVerb::Completed->value), $context));
        self::assertEquals([false, false, 0.5], $this->outcome(['result' => ['score' => ['scaled' => 0.5]]] + self::statement(XapiVerb::Passed->value), $context));
        self::assertEquals([false, false, 0.9], $this->outcome(['result' => ['score' => ['scaled' => 0.9]]] + self::statement(XapiVerb::Failed->value), $context));
        self::assertEquals([false, true, null], $this->outcome(self::statement(XapiVerb::Passed->value), $context));
    }

    public function testAnotherVerbReportsThroughItsResultOnly(): void
    {
        $context = $this->context();
        $mastered = 'http://adlnet.gov/expapi/verbs/mastered';

        self::assertEquals([true, null, null], $this->outcome(['result' => ['completion' => true]] + self::statement('http://adlnet.gov/expapi/verbs/experienced'), $context));
        self::assertEquals([false, true, 0.9], $this->outcome(['result' => ['success' => true, 'score' => ['scaled' => 0.9]]] + self::statement($mastered), $context));
        self::assertEquals([false, false, null], $this->outcome(['result' => ['success' => false]] + self::statement('https://example.org/verbs/finished'), $context));
        self::assertEquals([true, true, null], $this->outcome(['result' => ['completion' => true, 'success' => true]] + self::statement($mastered), $context));
        self::assertNull(XapiOutcomes::of(['result' => ['completion' => false, 'score' => ['scaled' => 1]]] + self::statement($mastered), $context));
        self::assertNull(XapiOutcomes::of(['result' => ['completion' => 'true', 'success' => 'yes']] + self::statement($mastered), $context));
        self::assertNull(XapiOutcomes::of(['result' => ['completion' => true]] + self::statement($mastered, self::ACTIVITY.'/page-2'), $context));

        $withMastery = new XapiContext('https://learn.example.org/', 'ada', self::ACTIVITY, self::REGISTRATION, 'session', 0.8);
        self::assertEquals([true, false, 0.5], $this->outcome(['result' => ['completion' => true, 'score' => ['scaled' => 0.5]]] + self::statement($mastered), $withMastery));
        self::assertEquals([false, true, 0.85], $this->outcome(['result' => ['success' => false, 'score' => ['scaled' => 0.85]]] + self::statement($mastered), $withMastery));
        self::assertNull(XapiOutcomes::of(['result' => ['score' => ['scaled' => 0.9]]] + self::statement($mastered), $withMastery));
    }

    public function testABodyIsBoundedAndHoldsObjects(): void
    {
        self::assertSame([['a' => 1]], StatementBatch::decode('{"a":1}'));
        self::assertSame([['a' => 1], ['b' => 2]], StatementBatch::decode('[{"a":1},{"b":2}]'));
        self::assertSame([['a' => 1, 'id' => 'x']], StatementBatch::decode('{"a":1}', 'x'));
        self::assertSame([['id' => 'x']], StatementBatch::decode('{"id":"x"}', 'x'));
        self::assertSame(['big' => '123456789012345678901234567890'], StatementBatch::decode('{"big":123456789012345678901234567890}')[0]);
        self::assertCount(50, StatementBatch::decode('['.implode(',', array_fill(0, 50, '{"a":1}')).']'));
        self::assertCount(1, StatementBatch::decode(str_pad('{"a":1}', StatementBatch::MAX_BYTES, ' ')));

        foreach ([
            [str_pad('{}', StatementBatch::MAX_BYTES + 1, ' '), null, 'A request holds 1048576 bytes of statements at most.'],
            ['{', null, 'The body is not JSON of a bounded depth.'],
            [str_repeat('[', 31).'{"a":1}'.str_repeat(']', 31), null, 'The body is not JSON of a bounded depth.'],
            ['[]', null, 'The body is a statement, or a list of them.'],
            ['"x"', null, 'The body is a statement, or a list of them.'],
            ['['.implode(',', array_fill(0, 51, '{}')).']', null, 'A request holds 50 statements at most.'],
            ['[{},{}]', 'x', 'A statement put at an identifier is one statement of that identifier.'],
            ['[1]', 'x', 'A statement put at an identifier is one statement of that identifier.'],
            ['{"id":"y"}', 'x', 'A statement put at an identifier is one statement of that identifier.'],
            ['[{},1]', null, 'Each statement is an object.'],
        ] as [$body, $id, $reason]) {
            try {
                StatementBatch::decode($body, $id);
                self::fail('Accepted: '.substr($body, 0, 40));
            } catch (StatementRefused $statementRefused) {
                self::assertSame($reason, $statementRefused->getMessage());
            }
        }

        self::assertCount(1, StatementBatch::decode(str_repeat('[', 30).'{"a":1}'.str_repeat(']', 30)));
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function statement(string $verb, string $object = self::ACTIVITY): array
    {
        return [
            'actor' => ['objectType' => 'Agent', 'account' => ['homePage' => 'https://learn.example.org/', 'name' => 'ada']],
            'verb' => ['id' => $verb],
            'object' => ['id' => $object],
        ];
    }

    private function context(): XapiContext
    {
        return new XapiContext('https://learn.example.org/', 'ada', self::ACTIVITY, self::REGISTRATION, 'session');
    }

    /**
     * @param array<array-key, mixed> $statement
     *
     * @return array{0: bool, 1: ?bool, 2: ?float}
     */
    private function outcome(array $statement, XapiContext $context): array
    {
        $outcome = XapiOutcomes::of($statement, $context);
        self::assertNotNull($outcome);

        return [$outcome->completed, $outcome->success, $outcome->score?->scaled];
    }
}
