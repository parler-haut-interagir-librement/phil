<?php

declare(strict_types=1);

namespace Phil\AiBundle\Application;

use DateTimeImmutable;
use Generator;
use Phil\AiBundle\Application\Exception\MissingPrompt;
use Phil\AiBundle\Application\Exception\NoUserKey;
use Phil\AiBundle\Application\Exception\TaskFailed;
use Phil\AiBundle\Application\Exception\UnknownSuggestion;
use Phil\AiBundle\Application\Exception\UnknownTask;
use Phil\AiBundle\Application\Port\Completions;
use Phil\AiBundle\Application\Port\UserKeys;
use Phil\AiBundle\Domain\Exception\AlreadyDecided;
use Phil\AiBundle\Domain\Journal\Decision;
use Phil\AiBundle\Domain\Journal\JournalTotals;
use Phil\AiBundle\Domain\Journal\KeySource;
use Phil\AiBundle\Domain\Model\JournalEntry;
use Phil\AiBundle\Domain\Repository\JournalRepository;
use Phil\AiBundle\Domain\ValueObject\Subject;
use Phil\UsageMeter\Exception\QuotaExceeded;
use Psr\Clock\ClockInterface;
use SensitiveParameter;
use Symfony\Component\Uid\Uuid;
use Throwable;

/**
 * The bundle in one service: a task runs, and proposes; the application decides.
 *
 * `run()` renders the task's prompts in the locale asked, picks the key — the person's own, or the application's —,
 * checks the subject's quota, calls the model, writes the journal and returns a `Suggestion`; `stream()` does the same
 * with the answer in pieces. Nothing the model said is applied, and no tool it proposes is run: the application shows
 * the suggestion, then calls `accept()`, `acceptEdited()` or `reject()`. The journal keeps the kind, the subject, the
 * tokens, the cost and the decision — never the prompt nor the answer. A failed call is given back to the quota.
 */
final readonly class AiTasks
{
    public function __construct(
        private TaskCatalog $tasks,
        private PromptTemplates $prompts,
        private Completions $completions,
        private JournalRepository $journal,
        private PriceList $prices,
        private ClockInterface $clock,
        private UserKeys $userKeys = new NoUserKeys(),
        private KeyPolicy $keyPolicy = KeyPolicy::PreferUser,
        private ?Quota $quota = null,
    ) {
    }

    /**
     * @throws UnknownTask   when the configuration does not define the kind
     * @throws MissingPrompt when the user prompt has no translation
     * @throws NoUserKey     when the task must run on a key of the person, who has none
     * @throws QuotaExceeded when the subject's quota is reached; nothing was counted nor called
     * @throws TaskFailed    when the model call failed; the failure is in the journal, the task given back
     */
    public function run(TaskRequest $request): Suggestion
    {
        [$id, $definition, $call, $source, $userKey] = $this->admit($request);
        $startedAt = $this->clock->now();

        try {
            $completion = $this->completions->complete($call, $userKey);
        } catch (Throwable $throwable) {
            $this->fail($id, $request, $definition, $source, $startedAt);

            throw TaskFailed::of($request->kind->value, $throwable);
        }

        return $this->succeed($id, $request, $definition, $source, $startedAt, $completion);
    }

    /**
     * The task's answer in pieces as the model writes them; the suggestion once the last piece is read.
     *
     * @throws UnknownTask   when the configuration does not define the kind
     * @throws MissingPrompt when the user prompt has no translation
     * @throws NoUserKey     when the task must run on a key of the person, who has none
     * @throws QuotaExceeded when the subject's quota is reached; nothing was counted nor called
     */
    public function stream(TaskRequest $request): SuggestionStream
    {
        [$id, $definition, $call, $source, $userKey] = $this->admit($request);
        $startedAt = $this->clock->now();

        return new SuggestionStream(
            $this->pieces($id, $request, $definition, $call, $source, $userKey, $startedAt),
            fn () => $this->fail($id, $request, $definition, $source, $startedAt),
        );
    }

    /**
     * The suggestion was applied as proposed.
     *
     * @throws UnknownSuggestion
     * @throws AlreadyDecided
     */
    public function accept(string $id): void
    {
        $this->decide($id, Decision::Accepted);
    }

    /**
     * The suggestion was applied after the person changed it.
     *
     * @throws UnknownSuggestion
     * @throws AlreadyDecided
     */
    public function acceptEdited(string $id): void
    {
        $this->decide($id, Decision::Edited);
    }

    /**
     * The suggestion was set aside.
     *
     * @throws UnknownSuggestion
     * @throws AlreadyDecided
     */
    public function reject(string $id): void
    {
        $this->decide($id, Decision::Rejected);
    }

    /**
     * What the subject's tasks added up to, from `$from` included to `$before` excluded, summed by the journal's store.
     */
    public function totals(string $subject, DateTimeImmutable $from, DateTimeImmutable $before): JournalTotals
    {
        return $this->journal->totals(Subject::fromString($subject), $from, $before);
    }

    /**
     * Forgets the subject's journal, when the account or project it names is erased.
     *
     * @return int how many entries were forgotten
     */
    public function erase(string $subject): int
    {
        return $this->journal->eraseSubject(Subject::fromString($subject));
    }

    /**
     * Everything a call needs, its quota admitted.
     *
     * @return array{string, TaskDefinition, ModelCall, KeySource, string|null}
     */
    private function admit(TaskRequest $request): array
    {
        $definition = $this->tasks->get($request->kind);
        $prompt = $this->prompts->render($request->kind, $request->locale, $request->parameters);
        $userKey = $this->userKey($request, $definition);
        $source = null === $userKey ? KeySource::Application : KeySource::User;
        $id = Uuid::v7()->toRfc4122();
        $this->quota?->admit($request->subject, $source, $definition->reserve, $id);

        return [$id, $definition, ModelCall::of($definition, $prompt), $source, $userKey];
    }

    /**
     * @return Generator<int, string, mixed, Suggestion>
     */
    private function pieces(string $id, TaskRequest $request, TaskDefinition $definition, ModelCall $call, KeySource $source, #[SensitiveParameter] ?string $userKey, DateTimeImmutable $startedAt): Generator
    {
        $completion = null;

        try {
            try {
                $pieces = $this->completions->stream($call, $userKey);

                foreach ($pieces as $piece) {
                    yield $piece;
                }

                $completion = $pieces->getReturn();
            } catch (Throwable $throwable) {
                throw TaskFailed::of($request->kind->value, $throwable);
            }
        } finally {
            // A failure, or a stream left before its end: the generator is torn down without a completion.
            if (!$completion instanceof Completion) {
                $this->fail($id, $request, $definition, $source, $startedAt);
            }
        }

        return $this->succeed($id, $request, $definition, $source, $startedAt, $completion);
    }

    private function succeed(string $id, TaskRequest $request, TaskDefinition $definition, KeySource $source, DateTimeImmutable $startedAt, Completion $completion): Suggestion
    {
        $cost = $this->prices->costOf($definition->model, $completion->tokens);
        $this->journal->save(JournalEntry::succeeded($id, $request->kind, $request->subject, $definition->platform, $definition->model, $source, $completion->tokens, $cost, $startedAt, $this->since($startedAt), $definition->output));
        $this->quota?->charge($request->subject, $source, $completion->tokens, $cost, $id);

        return new Suggestion($id, $request->kind, $request->subject, $completion->text, $completion->tokens, $cost, $source, $startedAt, $definition->output, $completion->data, $completion->toolCalls, $completion->media);
    }

    private function fail(string $id, TaskRequest $request, TaskDefinition $definition, KeySource $source, DateTimeImmutable $startedAt): void
    {
        $this->journal->save(JournalEntry::failed($id, $request->kind, $request->subject, $definition->platform, $definition->model, $source, $this->prices->currency(), $startedAt, $this->since($startedAt), $definition->output));
        $this->quota?->release($request->subject, $source, $id);
    }

    private function userKey(TaskRequest $request, TaskDefinition $definition): ?string
    {
        if (KeyPolicy::Application === $this->keyPolicy) {
            return null;
        }

        $key = null === $request->owner ? null : $this->userKeys->keyFor($request->owner, $definition->platform, \sprintf('AI task "%s"', $definition->kind->value));

        if (null === $key && KeyPolicy::RequireUser === $this->keyPolicy) {
            throw NoUserKey::for($definition->platform);
        }

        return $key;
    }

    private function decide(string $id, Decision $decision): void
    {
        $entry = $this->journal->get($id) ?? throw UnknownSuggestion::of($id);
        $entry->decide($decision, $this->clock->now());

        $this->journal->save($entry);
    }

    private function since(DateTimeImmutable $start): int
    {
        return $this->milliseconds($this->clock->now()) - $this->milliseconds($start);
    }

    private function milliseconds(DateTimeImmutable $at): int
    {
        return $at->getTimestamp() * 1000 + intdiv((int) $at->format('u'), 1000);
    }
}
