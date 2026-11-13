<?php

declare(strict_types=1);

namespace Phil\Automations\Tests\Unit\Run;

use DateTimeImmutable;
use Phil\Automations\Definition\AutomationDefinition;
use Phil\Automations\Definition\AutomationId;
use Phil\Automations\Definition\Step;
use Phil\Automations\Definition\StepPath;
use Phil\Automations\Definition\TriggerReference;
use Phil\Automations\Engine\ActionContext;
use Phil\Automations\Engine\Payload;
use Phil\Automations\Engine\TriggerMatch;
use Phil\Automations\Exception\ActionFailed;
use Phil\Automations\Exception\InvalidPayload;
use Phil\Automations\Run\AutomationRun;
use Phil\Automations\Run\CompletedStep;
use Phil\Automations\Run\RunId;
use Phil\Automations\Run\RunState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(AutomationRun::class)]
#[CoversClass(RunId::class)]
#[CoversClass(RunState::class)]
#[CoversClass(TriggerMatch::class)]
#[CoversClass(CompletedStep::class)]
#[CoversClass(ActionContext::class)]
#[CoversClass(ActionFailed::class)]
final class AutomationRunTest extends TestCase
{
    public function testARunStartsWaitingAtTheFirstStep(): void
    {
        $now = new DateTimeImmutable('@1800000000');
        $run = AutomationRun::start($this->definition(), new TriggerMatch('order:1', 'c', Payload::of(['a' => 1])), $now);

        self::assertSame(RunId::for(AutomationId::fromString('welcome'), 'order:1')->value, $run->id->value);
        self::assertSame(32, \strlen($run->id->value));
        self::assertSame(RunState::Waiting, $run->state);
        self::assertSame('0', $run->cursor?->toString());
        self::assertSame($now, $run->resumeAt);
        self::assertSame(2, $run->version);
        self::assertSame('c', $run->subject);
        self::assertSame(['a' => 1], $run->payload->values);
        self::assertSame(0, $run->attempt);
        self::assertSame(0, $run->revision);
        self::assertSame([], $run->completed);
        self::assertNull($run->failure);
        self::assertSame($now, $run->startedAt);
        self::assertSame($now, $run->updatedAt);
        self::assertTrue($run->isDue($now));
        self::assertFalse($run->isDue($now->modify('-1 second')));
    }

    public function testEachChangeGivesANewRun(): void
    {
        $now = new DateTimeImmutable('@1800000000');
        $later = $now->modify('+10 seconds');
        $run = AutomationRun::start($this->definition(), new TriggerMatch('k'), $now);

        $claimed = $run->claimed($later, $later->modify('+60 seconds'));
        self::assertSame(RunState::Running, $claimed->state);
        self::assertSame($later, $claimed->updatedAt);
        self::assertSame('0', $claimed->cursor?->toString());
        self::assertSame($now, $claimed->startedAt);
        self::assertSame(RunState::Waiting, $run->state);

        $waiting = $claimed->waitingUntil($later->modify('+5 seconds'), StepPath::top(1), 2, $later);
        self::assertSame(RunState::Waiting, $waiting->state);
        self::assertSame(2, $waiting->attempt);
        self::assertSame('1', $waiting->cursor?->toString());

        $moved = $waiting->movedTo(null, $later);
        self::assertNull($moved->cursor);
        self::assertSame(0, $moved->attempt);
        self::assertSame(RunState::Waiting, $moved->state);

        $done = $waiting->stepDone(new CompletedStep('one', 'test.act', Payload::of(['x' => 'y'])), null, $later);
        self::assertNull($done->cursor);
        self::assertSame(0, $done->attempt);
        self::assertSame(['one'], array_keys($done->outputs()));
        self::assertSame(['x' => 'y'], $done->outputs()['one']->values);

        $finished = $done->finished(RunState::Failed, str_repeat('é', 600), $later);
        self::assertSame(500, mb_strlen((string) $finished->failure));
        self::assertNull($finished->cursor);
        self::assertNull($finished->resumeAt);
        self::assertCount(1, $finished->completed);
        self::assertFalse($finished->isDue($later->modify('+1 year')));
        self::assertNull($done->finished(RunState::Completed, null, $later)->failure);
        self::assertSame(1, $finished->saved()->revision);
        self::assertSame(RunState::Failed, $finished->saved()->state);
    }

    public function testFinishedStates(): void
    {
        self::assertFalse(RunState::Running->isFinished());
        self::assertFalse(RunState::Waiting->isFinished());
        self::assertTrue(RunState::Completed->isFinished());
        self::assertTrue(RunState::Filtered->isFinished());
        self::assertTrue(RunState::Failed->isFinished());
        self::assertTrue(RunState::CompensationFailed->isFinished());
    }

    public function testRunIds(): void
    {
        $id = RunId::fromString(str_repeat('a', 32));

        self::assertSame(str_repeat('a', 32), (string) $id);
        self::assertTrue($id->equals(RunId::fromString(str_repeat('a', 32))));
        self::assertFalse($id->equals(RunId::fromString(str_repeat('b', 32))));
        self::assertNotSame(RunId::for(AutomationId::fromString('a'), 'bc')->value, RunId::for(AutomationId::fromString('ab'), 'c')->value);

        $this->expectException(InvalidPayload::class);
        $this->expectExceptionMessageIsOrContains('"ABC" is not a run id: 32 lowercase hexadecimal digits.');
        RunId::fromString('ABC');
    }

    #[DataProvider('badMatches')]
    public function testAMatchRefusesABadKey(string $key, ?string $subject, string $message): void
    {
        $this->expectException(InvalidPayload::class);
        $this->expectExceptionMessageIsOrContains($message);

        new TriggerMatch($key, $subject);
    }

    /**
     * @return iterable<string, array{string, string|null, string}>
     */
    public static function badMatches(): iterable
    {
        yield 'an empty key' => ['', null, 'The event key of a match is 1 to 255 bytes, without control characters.'];
        yield 'a long key' => [str_repeat('k', 256), null, 'The event key of a match'];
        yield 'a key with a new line' => ["k\n", null, 'The event key of a match'];
        yield 'an empty subject' => ['k', '', 'The subject of a match is 1 to 255 bytes'];
        yield 'a subject with a delete' => ['k', "s\x7F", 'The subject of a match'];
    }

    public function testAMatchKeepsItsParts(): void
    {
        $match = new TriggerMatch(str_repeat('k', 255), str_repeat('s', 255));

        self::assertSame([], $match->payload->values);
        self::assertSame(255, \strlen((string) $match->subject));
        self::assertNull(new TriggerMatch('k')->subject);
    }

    public function testFailures(): void
    {
        $retryable = ActionFailed::retryable('down');
        $permanent = ActionFailed::permanent('no', $retryable);

        self::assertTrue($retryable->retryable);
        self::assertFalse($permanent->retryable);
        self::assertSame('no', $permanent->getMessage());
        self::assertSame($retryable, $permanent->getPrevious());
    }

    private function definition(): AutomationDefinition
    {
        return new AutomationDefinition(AutomationId::fromString('welcome'), 2, 'Welcome', TriggerReference::of('t'), [Step::delay('wait', 5)]);
    }

    public function testEachStepDoneIsKeptAndEachOutputIsListed(): void
    {
        $now = new DateTimeImmutable('@1800000000');
        $run = AutomationRun::start($this->definition(), new TriggerMatch('k'), $now)
            ->stepDone(new CompletedStep('one', 'test.act', Payload::of(['n' => 1])), StepPath::top(1), $now)
            ->stepDone(new CompletedStep('two', 'test.act', Payload::of(['n' => 2])), null, $now);

        self::assertSame(['one', 'two'], array_map(static fn (CompletedStep $step): string => $step->stepId, $run->completed));
        self::assertSame(['one', 'two'], array_keys($run->outputs()));
        self::assertSame(['n' => 1], $run->outputs()['one']->values);
        self::assertSame(['n' => 2], $run->outputs()['two']->values);
    }

    public function testAFailureReasonIsKeptFromItsFirstCharacter(): void
    {
        $now = new DateTimeImmutable('@1800000000');
        $run = AutomationRun::start($this->definition(), new TriggerMatch('k'), $now);

        self::assertSame('Reason', $run->finished(RunState::Failed, 'Reason', $now)->failure);
        self::assertSame('abcd', $run->finished(RunState::Failed, 'abcd', $now)->failure);
        self::assertSame(str_repeat('a', 499).'b', $run->finished(RunState::Failed, str_repeat('a', 499).'bcd', $now)->failure);
    }

    public function testAClaimReplacesTheInstantTheRunWaitedFor(): void
    {
        $now = new DateTimeImmutable('@1800000000');
        $run = AutomationRun::start($this->definition(), new TriggerMatch('k'), $now);
        $leaseEnd = $now->modify('+60 seconds');

        self::assertSame($leaseEnd, $run->claimed($now, $leaseEnd)->resumeAt);
        self::assertSame($leaseEnd, $run->claimed($now, $leaseEnd)->movedTo(null, $now)->resumeAt);
    }

    public function testAnActionFailureHasNoErrorCode(): void
    {
        self::assertSame(0, ActionFailed::permanent('No.')->getCode());
        self::assertSame(0, ActionFailed::retryable('No.')->getCode());
    }
}
