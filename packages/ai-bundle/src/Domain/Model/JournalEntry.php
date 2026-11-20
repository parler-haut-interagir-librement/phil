<?php

declare(strict_types=1);

namespace Phil\AiBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\AiBundle\Domain\Exception\AlreadyDecided;
use Phil\AiBundle\Domain\Journal\Decision;
use Phil\AiBundle\Domain\Journal\KeySource;
use Phil\AiBundle\Domain\Journal\Outcome;
use Phil\AiBundle\Domain\Journal\Output;
use Phil\AiBundle\Domain\ValueObject\Cost;
use Phil\AiBundle\Domain\ValueObject\Subject;
use Phil\AiBundle\Domain\ValueObject\TaskKind;
use Phil\AiBundle\Domain\ValueObject\Tokens;

/**
 * One task in the journal: its kind, what it asked for, the subject it was charged to, the platform and model, whose
 * key paid, how it ended, the tokens and the cost, when it started and how long it took, and what became of its suggestion.
 *
 * The row never holds the prompt, the answer, nor anything the person wrote: what a task said is the application's,
 * kept where it keeps the text it applies to, or nowhere.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
#[ORM\Index(name: 'phil_ai_journal_subject_started', columns: ['subject', 'started_at'])]
final class JournalEntry
{
    public const string TABLE = 'phil_ai_journal';

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36)]
        private readonly string $id,
        #[ORM\Column(name: 'kind', length: TaskKind::MAX_LENGTH)]
        private readonly string $kind,
        #[ORM\Column(name: 'subject', length: Subject::MAX_LENGTH)]
        private readonly string $subject,
        #[ORM\Column(name: 'platform', length: 64)]
        private readonly string $platform,
        #[ORM\Column(name: 'model', length: 128)]
        private readonly string $model,
        #[ORM\Column(name: 'key_source', length: 16, enumType: KeySource::class)]
        private readonly KeySource $keySource,
        #[ORM\Column(name: 'outcome', length: 16, enumType: Outcome::class)]
        private readonly Outcome $outcome,
        #[ORM\Column(name: 'input_tokens')]
        private readonly int $inputTokens,
        #[ORM\Column(name: 'output_tokens')]
        private readonly int $outputTokens,
        #[ORM\Column(name: 'cost_micros', type: 'bigint')]
        private readonly int $costMicros,
        #[ORM\Column(name: 'currency', length: 3)]
        private readonly string $currency,
        #[ORM\Column(name: 'started_at')]
        private readonly DateTimeImmutable $startedAt,
        #[ORM\Column(name: 'duration_ms')]
        private readonly int $durationMs,
        #[ORM\Column(name: 'decision', length: 16, enumType: Decision::class)]
        private Decision $decision,
        #[ORM\Column(name: 'output', length: 16, enumType: Output::class)]
        private readonly Output $output = Output::Text,
        #[ORM\Column(name: 'decided_at', nullable: true)]
        private ?DateTimeImmutable $decidedAt = null,
    ) {
    }

    /**
     * A task that proposed something: its suggestion waits for the application's decision.
     */
    public static function succeeded(string $id, TaskKind $kind, Subject $subject, string $platform, string $model, KeySource $keySource, Tokens $tokens, Cost $cost, DateTimeImmutable $startedAt, int $durationMs, Output $output = Output::Text): self
    {
        return new self($id, $kind->value, $subject->value, $platform, $model, $keySource, Outcome::Succeeded, $tokens->input, $tokens->output, $cost->micros, $cost->currency, $startedAt, $durationMs, Decision::Pending, $output);
    }

    /**
     * A task whose call failed: no tokens are known, nothing was proposed.
     */
    public static function failed(string $id, TaskKind $kind, Subject $subject, string $platform, string $model, KeySource $keySource, string $currency, DateTimeImmutable $startedAt, int $durationMs, Output $output = Output::Text): self
    {
        return new self($id, $kind->value, $subject->value, $platform, $model, $keySource, Outcome::Failed, 0, 0, 0, $currency, $startedAt, $durationMs, Decision::Pending, $output);
    }

    /**
     * Records what the application did with the suggestion.
     *
     * @throws AlreadyDecided when it was decided before, or when the task failed
     */
    public function decide(Decision $decision, DateTimeImmutable $at): void
    {
        if (Outcome::Failed === $this->outcome) {
            throw AlreadyDecided::failedTask($this->id);
        }

        if (Decision::Pending !== $this->decision) {
            throw AlreadyDecided::suggestion($this->id, $this->decision->value);
        }

        $this->decision = $decision;
        $this->decidedAt = $at;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function kind(): TaskKind
    {
        return TaskKind::fromString($this->kind);
    }

    public function subject(): Subject
    {
        return Subject::fromString($this->subject);
    }

    public function platform(): string
    {
        return $this->platform;
    }

    public function model(): string
    {
        return $this->model;
    }

    public function output(): Output
    {
        return $this->output;
    }

    public function keySource(): KeySource
    {
        return $this->keySource;
    }

    public function outcome(): Outcome
    {
        return $this->outcome;
    }

    public function tokens(): Tokens
    {
        return new Tokens($this->inputTokens, $this->outputTokens);
    }

    public function cost(): Cost
    {
        return new Cost($this->costMicros, $this->currency);
    }

    public function startedAt(): DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function durationMs(): int
    {
        return $this->durationMs;
    }

    public function decision(): Decision
    {
        return $this->decision;
    }

    public function decidedAt(): ?DateTimeImmutable
    {
        return $this->decidedAt;
    }
}
