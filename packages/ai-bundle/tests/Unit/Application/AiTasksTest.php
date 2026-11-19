<?php

declare(strict_types=1);

namespace Phil\AiBundle\Tests\Unit\Application;

use ArrayObject;
use DateTimeImmutable;
use LogicException;
use Override;
use Phil\AiBundle\Application\AiTasks;
use Phil\AiBundle\Application\Completion;
use Phil\AiBundle\Application\Exception\MissingPrompt;
use Phil\AiBundle\Application\Exception\NoUserKey;
use Phil\AiBundle\Application\Exception\TaskFailed;
use Phil\AiBundle\Application\Exception\UnknownSuggestion;
use Phil\AiBundle\Application\Exception\UnknownTask;
use Phil\AiBundle\Application\KeyPolicy;
use Phil\AiBundle\Application\Media;
use Phil\AiBundle\Application\ModelCall;
use Phil\AiBundle\Application\Port\UserKeys;
use Phil\AiBundle\Application\PriceList;
use Phil\AiBundle\Application\Prompt;
use Phil\AiBundle\Application\ProposedToolCall;
use Phil\AiBundle\Application\Quota;
use Phil\AiBundle\Application\Suggestion;
use Phil\AiBundle\Application\SuggestionStream;
use Phil\AiBundle\Application\TaskCatalog;
use Phil\AiBundle\Application\TaskDefinition;
use Phil\AiBundle\Application\TaskRequest;
use Phil\AiBundle\Domain\Exception\AlreadyDecided;
use Phil\AiBundle\Domain\Journal\Decision;
use Phil\AiBundle\Domain\Journal\KeySource;
use Phil\AiBundle\Domain\Journal\Outcome;
use Phil\AiBundle\Domain\Journal\Output;
use Phil\AiBundle\Domain\Model\JournalEntry;
use Phil\AiBundle\Domain\ValueObject\Price;
use Phil\AiBundle\Domain\ValueObject\Subject;
use Phil\AiBundle\Domain\ValueObject\TaskKind;
use Phil\AiBundle\Domain\ValueObject\Tokens;
use Phil\AiBundle\Infrastructure\Memory\InMemoryJournalRepository;
use Phil\AiBundle\Tests\Unit\Fixture\FixedUserKeys;
use Phil\AiBundle\Tests\Unit\Fixture\Prompts;
use Phil\AiBundle\Tests\Unit\Fixture\ScriptedCompletions;
use Phil\UsageMeter\Exception\QuotaExceeded;
use Phil\UsageMeter\InMemoryUsageStore;
use Phil\UsageMeter\Limit;
use Phil\UsageMeter\Meter;
use Phil\UsageMeter\Meters;
use Phil\UsageMeter\Period\CalendarPeriod;
use Phil\UsageMeter\UsageMeter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Clock\MockClock;

#[CoversClass(AiTasks::class)]
#[CoversClass(Suggestion::class)]
#[CoversClass(ModelCall::class)]
#[CoversClass(Completion::class)]
#[CoversClass(TaskFailed::class)]
#[CoversClass(NoUserKey::class)]
#[CoversClass(UnknownSuggestion::class)]
#[CoversClass(SuggestionStream::class)]
final class AiTasksTest extends TestCase
{
    private MockClock $clock;

    private InMemoryJournalRepository $journal;

    private ?UsageMeter $taskMeter = null;

    #[Override]
    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-06 10:00:00.123456 UTC');
        $this->journal = new InMemoryJournalRepository();
    }

    public function testATaskProposesWhatTheModelAnswered(): void
    {
        $model = $this->model();
        $suggestion = $this->tasks($model)->run(new TaskRequest('draft.rewrite', 'project-1', 'fr', ['genre' => 'fantasy', 'text' => 'Il était une fois']));

        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $suggestion->id);
        self::assertSame(['draft.rewrite', 'project-1', 'Une fois, il y a longtemps', KeySource::Application], [$suggestion->kind->value, $suggestion->subject->value, $suggestion->text, $suggestion->keySource]);
        self::assertEquals(new Tokens(12, 5), $suggestion->tokens);
        self::assertSame([111, 'USD'], [$suggestion->cost->micros, $suggestion->cost->currency]);
        self::assertSame('2026-11-06 10:00:00.123456', $suggestion->proposedAt->format('Y-m-d H:i:s.u'));

        self::assertCount(1, $model->calls);
        [$call, $key] = $model->calls[0];
        self::assertSame(['main', 'model-a', ['max_tokens' => 512]], [$call->platform, $call->model, $call->options]);
        self::assertSame(['You edit fantasy prose.', 'Réécris : Il était une fois'], [$call->prompt->system, $call->prompt->user]);
        self::assertNull($key);

        $entry = $this->journal->get($suggestion->id);
        self::assertNotNull($entry);
        self::assertSame(['draft.rewrite', 'project-1', 'main', 'model-a', KeySource::Application, Outcome::Succeeded, Decision::Pending], [$entry->kind()->value, $entry->subject()->value, $entry->platform(), $entry->model(), $entry->keySource(), $entry->outcome(), $entry->decision()]);
        self::assertEquals(new Tokens(12, 5), $entry->tokens());
        self::assertSame([111, 'USD'], [$entry->cost()->micros, $entry->cost()->currency]);
        self::assertSame('2026-11-06 10:00:00.123456', $entry->startedAt()->format('Y-m-d H:i:s.u'));
        self::assertSame(1_500, $entry->durationMs());
    }

    public function testATaskOfAPersonRunsOnTheirOwnKeyWhenTheyHaveOne(): void
    {
        $keys = new FixedUserKeys(['member-1/main' => 'sk-member']);
        $model = $this->model();
        $tasks = $this->tasks($model, $keys);

        $own = $tasks->run(new TaskRequest('draft.rewrite', 'project-1', 'en', ['genre' => 'g', 'text' => 't'], 'member-1'));
        $other = $tasks->run(new TaskRequest('draft.rewrite', 'project-1', 'en', ['genre' => 'g', 'text' => 't'], 'member-2'));
        $application = $tasks->run(new TaskRequest('draft.rewrite', 'project-1', 'en', ['genre' => 'g', 'text' => 't']));

        self::assertSame([KeySource::User, KeySource::Application, KeySource::Application], [$own->keySource, $other->keySource, $application->keySource]);
        self::assertSame(['sk-member', null, null], array_map(static fn (array $call): ?string => $call[1], $model->calls));
        self::assertSame([['member-1', 'main', 'AI task "draft.rewrite"'], ['member-2', 'main', 'AI task "draft.rewrite"']], $keys->requests);
        self::assertSame(KeySource::User, $this->journal->get($own->id)?->keySource());
    }

    public function testTheApplicationPolicyNeverReadsThePersonsKeys(): void
    {
        $keys = new FixedUserKeys(['member-1/main' => 'sk-member']);
        $model = $this->model();

        $suggestion = $this->tasks($model, $keys, KeyPolicy::Application)->run(new TaskRequest('draft.rewrite', 'project-1', 'en', [], 'member-1'));

        self::assertSame(KeySource::Application, $suggestion->keySource);
        self::assertNull($model->calls[0][1]);
        self::assertSame([], $keys->requests);
    }

    public function testTheRequireUserPolicyRefusesATaskWithoutThePersonsKey(): void
    {
        $keys = new FixedUserKeys(['member-1/main' => 'sk-member']);
        $model = $this->model();
        $tasks = $this->tasks($model, $keys, KeyPolicy::RequireUser);

        self::assertSame(KeySource::User, $tasks->run(new TaskRequest('draft.rewrite', 'project-1', 'en', [], 'member-1'))->keySource);

        foreach ([null, 'member-2'] as $owner) {
            try {
                $tasks->run(new TaskRequest('draft.rewrite', 'project-1', 'en', [], $owner));
                self::fail("A task ran without the person's key.");
            } catch (NoUserKey $noUserKey) {
                self::assertSame('The task runs only on the person\'s own key, and they have none for the platform "main".', $noUserKey->getMessage());
            }
        }

        self::assertCount(1, $model->calls);
        self::assertSame(1, $tasks->totals('project-1', new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2027-01-01'))->tasks);
    }

    public function testAFailedCallIsJournaledAndReportedWithoutTheProvidersMessage(): void
    {
        $failure = new RuntimeException('The prompt "Il était une fois" was refused.');
        $model = new ScriptedCompletions(null, $failure, $this->clock, 0.04);
        $tasks = $this->tasks($model, new FixedUserKeys(['member-1/main' => 'sk-member']), quota: $this->quota());

        try {
            $tasks->run(new TaskRequest('draft.rewrite', 'project-1', 'en', [], 'member-1'));
            self::fail('A failed call proposed something.');
        } catch (TaskFailed $taskFailed) {
            self::assertSame('The task "draft.rewrite" failed at the model (RuntimeException).', $taskFailed->getMessage());
            self::assertSame($failure, $taskFailed->getPrevious());
        }

        $entries = $this->journal->ofSubject(Subject::fromString('project-1'), new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2027-01-01'));
        self::assertCount(1, $entries);
        self::assertSame([Outcome::Failed, KeySource::User, 'main', 'model-a', 0, 'USD', 40], [$entries[0]->outcome(), $entries[0]->keySource(), $entries[0]->platform(), $entries[0]->model(), $entries[0]->tokens()->total(), $entries[0]->cost()->currency, $entries[0]->durationMs()]);
        self::assertSame('2026-11-06 10:00:00.123456', $entries[0]->startedAt()->format('Y-m-d H:i:s.u'));
    }

    public function testNothingRunsForAnUnknownTaskOrAMissingPrompt(): void
    {
        $model = $this->model();
        $keys = new FixedUserKeys();
        $tasks = $this->tasks($model, $keys, catalog: new TaskCatalog(new TaskDefinition(TaskKind::fromString('draft.rewrite'), 'main', 'model-a'), new TaskDefinition(TaskKind::fromString('draft.expand'), 'main', 'model-a')));

        foreach ([['draft.shorten', UnknownTask::class], ['draft.expand', MissingPrompt::class]] as [$kind, $exception]) {
            try {
                $tasks->run(new TaskRequest($kind, 'project-1', 'en', [], 'member-1'));
                self::fail($kind);
            } catch (UnknownTask|MissingPrompt $refused) {
                self::assertInstanceOf($exception, $refused);
            }
        }

        self::assertSame([[], []], [$model->calls, $keys->requests]);
        self::assertSame(0, $tasks->totals('project-1', new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2027-01-01'))->tasks);
    }

    public function testTheQuotaIsCheckedBeforeTheCallAndChargedAfter(): void
    {
        $model = $this->model();
        $quota = $this->quota();
        $tasks = $this->tasks($model, quota: $quota);

        $tasks->run(new TaskRequest('draft.rewrite', 'project-1', 'en'));
        $tasks->run(new TaskRequest('draft.rewrite', 'project-1', 'en'));
        $tasks->run(new TaskRequest('draft.rewrite', 'project-1', 'en'));

        try {
            $tasks->run(new TaskRequest('draft.rewrite', 'project-1', 'en'));
            self::fail('A task past the quota ran.');
        } catch (QuotaExceeded $quotaExceeded) {
            self::assertSame([51, 50], [$quotaExceeded->usage->used, $quotaExceeded->requested]);
        }

        self::assertCount(3, $model->calls);
        self::assertSame(3, $tasks->totals('project-1', new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2027-01-01'))->tasks);
    }

    public function testTheApplicationDecidesOnEachSuggestion(): void
    {
        $tasks = $this->tasks($this->model());
        $ids = array_map(static fn (int $n): string => $tasks->run(new TaskRequest('draft.rewrite', 'project-1', 'en'))->id, [1, 2, 3, 4]);
        $this->clock->modify('+1 minute');

        $tasks->accept($ids[0]);
        $tasks->acceptEdited($ids[1]);
        $tasks->reject($ids[2]);

        self::assertSame([Decision::Accepted, Decision::Edited, Decision::Rejected, Decision::Pending], array_map(fn (string $id): ?Decision => $this->journal->get($id)?->decision(), $ids));
        self::assertSame('2026-11-06 10:01:06.123456', $this->journal->get($ids[0])?->decidedAt()?->format('Y-m-d H:i:s.u'));
        self::assertSame(['pending' => 1, 'accepted' => 1, 'edited' => 1, 'rejected' => 1], $tasks->totals('project-1', new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2027-01-01'))->decisions);

        try {
            $tasks->reject($ids[0]);
            self::fail('A suggestion was decided twice.');
        } catch (AlreadyDecided $alreadyDecided) {
            self::assertSame(\sprintf('The suggestion "%s" is already accepted.', $ids[0]), $alreadyDecided->getMessage());
        }

        try {
            $tasks->accept('no-such-task');
            self::fail('An unknown suggestion was accepted.');
        } catch (UnknownSuggestion $unknownSuggestion) {
            self::assertSame('The journal holds no task "no-such-task".', $unknownSuggestion->getMessage());
        }
    }

    public function testTheTotalsSpanAndTheErasureOfASubject(): void
    {
        $tasks = $this->tasks($this->model());
        $tasks->run(new TaskRequest('draft.rewrite', 'project-1', 'en'));
        $tasks->run(new TaskRequest('draft.rewrite', 'project-2', 'en'));

        $this->clock->modify('+1 day');
        $tasks->run(new TaskRequest('draft.rewrite', 'project-1', 'en'));

        $day = $tasks->totals('project-1', new DateTimeImmutable('2026-11-06 00:00:00 UTC'), new DateTimeImmutable('2026-11-07 00:00:00 UTC'));
        self::assertSame([1, 17, ['USD' => 111]], [$day->tasks, $day->tokens->total(), $day->costs]);

        self::assertSame(2, $tasks->erase('project-1'));
        self::assertSame(0, $tasks->totals('project-1', new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2027-01-01'))->tasks);
        self::assertSame(1, $tasks->totals('project-2', new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2027-01-01'))->tasks);
    }

    public function testAFailedCallGivesItsTaskBackToTheQuota(): void
    {
        $tasks = $this->tasks(new ScriptedCompletions(null, new RuntimeException('down')), quota: $this->taskQuota());

        foreach ([1, 2, 3] as $attempt) {
            try {
                $tasks->run(new TaskRequest('draft.rewrite', 'project-1', 'en'));
                self::fail('A failed call proposed something.');
            } catch (TaskFailed) {
                self::assertSame(0, $this->tasksUsed(), \sprintf('Attempt %d kept its task.', $attempt));
            }
        }

        $this->tasks($this->model(), quota: $this->taskQuota())->run(new TaskRequest('draft.rewrite', 'project-1', 'en'));
        self::assertSame(1, $this->tasksUsed());
    }

    public function testAStreamYieldsThePiecesThenTheSuggestion(): void
    {
        $model = new ScriptedCompletions(new Completion('Une fois, il y a longtemps', new Tokens(12, 5)), null, $this->clock, 0.25, ['Une fois, ', 'il y a longtemps']);
        $tasks = $this->tasks($model, quota: $this->taskQuota());
        $stream = $tasks->stream(new TaskRequest('draft.rewrite', 'project-1', 'en', ['genre' => 'fantasy', 'text' => 'x']));

        self::assertSame(1, $this->tasksUsed(), 'The quota is admitted before the first piece.');
        self::assertSame(['Une fois, ', 'il y a longtemps'], iterator_to_array($stream));

        $suggestion = $stream->suggestion();
        self::assertSame(['Une fois, il y a longtemps', 111, 'draft.rewrite'], [$suggestion->text, $suggestion->cost->micros, $suggestion->kind->value]);
        self::assertEquals(new ModelCall('main', 'model-a', new Prompt('You edit fantasy prose.', 'Rewrite: x'), ['max_tokens' => 512]), $model->calls[0][0]);

        $entry = $this->journal->get($suggestion->id);
        self::assertSame([Outcome::Succeeded, 17, 250], [$entry?->outcome(), $entry?->tokens()->total(), $entry?->durationMs()]);
        self::assertSame(1, $this->tasksUsed());

        try {
            iterator_to_array($stream);
            self::fail('A stream was read twice.');
        } catch (LogicException $logicException) {
            self::assertSame('A stream of a suggestion is read once.', $logicException->getMessage());
        }
    }

    public function testAStreamThatFailsIsJournaledAndGivenBack(): void
    {
        $failure = new RuntimeException('The prompt was refused.');
        $tasks = $this->tasks(new ScriptedCompletions(null, $failure, $this->clock, 0.5, ['Une ']), quota: $this->taskQuota());
        $stream = $tasks->stream(new TaskRequest('draft.rewrite', 'project-1', 'en'));
        $pieces = new ArrayObject();

        try {
            foreach ($stream as $piece) {
                $pieces->append($piece);
            }

            self::fail('A failed stream ended.');
        } catch (TaskFailed $taskFailed) {
            self::assertSame([['Une '], $failure], [$pieces->getArrayCopy(), $taskFailed->getPrevious()]);
        }

        $entries = $this->entries();
        self::assertSame([[Outcome::Failed], [500], 0], [array_map(static fn (JournalEntry $entry): Outcome => $entry->outcome(), $entries), array_map(static fn (JournalEntry $entry): int => $entry->durationMs(), $entries), $this->tasksUsed()]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('A suggestion is whole once its stream was read to the end.');
        $stream->suggestion();
    }

    public function testAStreamLeftBeforeItsEndIsJournaledAsFailedAndGivenBack(): void
    {
        $tasks = $this->tasks(new ScriptedCompletions(new Completion('ab', Tokens::none()), null, null, 0.0, ['a', 'b']), quota: $this->taskQuota());
        $stream = $tasks->stream(new TaskRequest('draft.rewrite', 'project-1', 'en'));

        foreach ($stream as $piece) {
            self::assertSame('a', $piece);

            break;
        }

        unset($stream, $piece);
        gc_collect_cycles();

        self::assertSame([[Outcome::Failed], 0], [array_map(static fn (JournalEntry $entry): Outcome => $entry->outcome(), $this->entries()), $this->tasksUsed()]);
    }

    public function testAStreamNeverReadIsJournaledAsFailedAndGivenBack(): void
    {
        $model = new ScriptedCompletions(new Completion('ab', Tokens::none()), null, null, 0.0, ['a']);
        $stream = $this->tasks($model, quota: $this->taskQuota())->stream(new TaskRequest('draft.rewrite', 'project-1', 'en'));
        self::assertSame(1, $this->tasksUsed());

        unset($stream);

        self::assertSame([[Outcome::Failed], 0, []], [array_map(static fn (JournalEntry $entry): Outcome => $entry->outcome(), $this->entries()), $this->tasksUsed(), $model->calls]);
    }

    public function testAStreamReadToItsEndIsNotGivenBackWhenDropped(): void
    {
        $stream = $this->tasks(new ScriptedCompletions(new Completion('a', Tokens::none()), null, null, 0.0, ['a']), quota: $this->taskQuota())->stream(new TaskRequest('draft.rewrite', 'project-1', 'en'));
        iterator_to_array($stream);

        unset($stream);
        gc_collect_cycles();

        self::assertSame([[Outcome::Succeeded], 1], [array_map(static fn (JournalEntry $entry): Outcome => $entry->outcome(), $this->entries()), $this->tasksUsed()]);
    }

    public function testWhatTheModelProposesBeyondTextReachesTheSuggestion(): void
    {
        $media = new Media('PNG', 'image/png');
        $calls = [new ProposedToolCall('call-1', 'look_up', ['rule' => 'grapple'])];
        $data = ['name' => 'Ysolde'];
        $model = new ScriptedCompletions(new Completion('', new Tokens(0, 0), $data, $calls, $media));
        $definition = new TaskDefinition(TaskKind::fromString('draft.rewrite'), 'main', 'model-a', [], 1, Output::Image, purpose: 'Draws a cover.');
        $suggestion = $this->tasks($model, catalog: new TaskCatalog($definition), prices: new PriceList('USD', ['model-a' => new Price(0, 0, 'USD', 40_000)]))->run(new TaskRequest('draft.rewrite', 'project-1', 'en'));

        self::assertSame([Output::Image, $data, $calls, $media, 40_000], [$suggestion->output, $suggestion->data, $suggestion->toolCalls, $suggestion->media, $suggestion->cost->micros]);
        self::assertSame(Output::Image, $this->entries()[0]->output());
        self::assertSame(Output::Image, $model->calls[0][0]->output);
    }

    public function testAFailedTaskKeepsWhatItAskedFor(): void
    {
        $definition = new TaskDefinition(TaskKind::fromString('draft.rewrite'), 'main', 'model-a', [], 1, Output::Speech);

        try {
            $this->tasks(new ScriptedCompletions(null, new RuntimeException('down')), catalog: new TaskCatalog($definition))->run(new TaskRequest('draft.rewrite', 'project-1', 'en'));
            self::fail('A failed call proposed something.');
        } catch (TaskFailed) {
            self::assertSame(Output::Speech, $this->entries()[0]->output());
        }
    }

    /**
     * @return list<JournalEntry>
     *
     * @phpstan-impure
     */
    private function entries(): array
    {
        return $this->journal->ofSubject(Subject::fromString('project-1'), new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2027-01-01'));
    }

    private function taskQuota(): Quota
    {
        $this->taskMeter ??= new UsageMeter(new Meters(new Meter('ai_tasks', CalendarPeriod::month(), Limit::of(10))), new InMemoryUsageStore(), $this->clock);

        return new Quota($this->taskMeter, null, 'ai_tasks');
    }

    /**
     * @phpstan-impure
     */
    private function tasksUsed(): int
    {
        return $this->taskMeter?->usage('project-1', 'ai_tasks')->used ?? 0;
    }

    private function model(): ScriptedCompletions
    {
        return new ScriptedCompletions(new Completion('Une fois, il y a longtemps', new Tokens(12, 5)), null, $this->clock, 1.5);
    }

    private function quota(): Quota
    {
        return new Quota(new UsageMeter(new Meters(new Meter('ai_tokens', CalendarPeriod::month(), Limit::of(100))), new InMemoryUsageStore(), $this->clock), 'ai_tokens');
    }

    private function tasks(ScriptedCompletions $model, ?UserKeys $keys = null, KeyPolicy $policy = KeyPolicy::PreferUser, ?Quota $quota = null, ?TaskCatalog $catalog = null, ?PriceList $prices = null): AiTasks
    {
        return new AiTasks(
            $catalog ?? new TaskCatalog(new TaskDefinition(TaskKind::fromString('draft.rewrite'), 'main', 'model-a', ['max_tokens' => 512], 50)),
            Prompts::templates(),
            $model,
            $this->journal,
            $prices ?? new PriceList('USD', ['model-a' => new Price(3_000_000, 15_000_000, 'USD')]),
            $this->clock,
            $keys ?? new FixedUserKeys(),
            $policy,
            $quota,
        );
    }
}
