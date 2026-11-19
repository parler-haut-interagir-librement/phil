<?php

declare(strict_types=1);

namespace Phil\AiBundle\Infrastructure\Privacy;

use DateTimeImmutable;
use DateTimeInterface;
use Override;
use Phil\AiBundle\Domain\Model\JournalEntry;
use Phil\AiBundle\Domain\Repository\JournalRepository;
use Phil\AiBundle\Domain\ValueObject\Subject;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\Application\Contract\PersonalDataExporter;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;

/**
 * The journal's part of an access or erasure request of `phil/privacy-bundle`, the data subject being the subject the
 * tasks were charged to: the export lists each task — kind, what it asked for, platform, model, whose key, outcome,
 * tokens, cost, when, how long, decision —, which is all the journal holds, never a prompt nor an answer; the erasure
 * deletes the subject's entries, and a second erasure finds nothing left.
 */
final readonly class AiJournalPrivacy implements PersonalDataExporter, PersonalDataEraser
{
    public const string MODULE = 'ai';

    public function __construct(
        private JournalRepository $journal,
    ) {
    }

    #[Override]
    public function module(): ModuleName
    {
        return ModuleName::fromString(self::MODULE);
    }

    #[Override]
    public function export(DataSubject $subject): array
    {
        $entries = $this->journal->ofSubject(Subject::fromString($subject->value), new DateTimeImmutable('@0'), new DateTimeImmutable('9999-12-31 23:59:59 UTC'));

        return [] === $entries ? [] : ['tasks' => array_map(static fn (JournalEntry $entry): array => [
            'id' => $entry->id(),
            'kind' => $entry->kind()->value,
            'output' => $entry->output()->value,
            'platform' => $entry->platform(),
            'model' => $entry->model(),
            'key' => $entry->keySource()->value,
            'outcome' => $entry->outcome()->value,
            'input_tokens' => $entry->tokens()->input,
            'output_tokens' => $entry->tokens()->output,
            'cost_micros' => $entry->cost()->micros,
            'currency' => $entry->cost()->currency,
            'started_at' => $entry->startedAt()->format(DateTimeInterface::ATOM),
            'duration_ms' => $entry->durationMs(),
            'decision' => $entry->decision()->value,
            'decided_at' => $entry->decidedAt()?->format(DateTimeInterface::ATOM),
        ], $entries)];
    }

    #[Override]
    public function erase(DataSubject $subject): ErasureReport
    {
        $this->journal->eraseSubject(Subject::fromString($subject->value));

        return ErasureReport::of(self::MODULE, [JournalEntry::TABLE => ErasureEffect::Deleted]);
    }
}
