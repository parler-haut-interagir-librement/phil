<?php

declare(strict_types=1);

namespace Phil\TranslationBundle\Application;

use Closure;
use DateTimeImmutable;
use Phil\TranslationBundle\Application\Contract\MachineTranslator;
use Phil\TranslationBundle\Application\Contract\Translators;
use Phil\TranslationBundle\Application\Exception\MachineTranslationFailed;
use Phil\TranslationBundle\Application\Exception\NotFound;
use Phil\TranslationBundle\Domain\Exception\InvalidRecord;
use Phil\TranslationBundle\Domain\Model\SegmentRecord;
use Phil\TranslationBundle\Domain\Repository\SegmentRepository;
use Phil\TranslationMemory\Check\Checked;
use Phil\TranslationMemory\Check\Checker;
use Phil\TranslationMemory\Check\CheckIssue;
use Phil\TranslationMemory\Check\Flags;
use Phil\TranslationMemory\Exception\InvalidConfiguration;
use Phil\TranslationMemory\Exception\InvalidLanguage;
use Phil\TranslationMemory\Exception\InvalidSegment;
use Phil\TranslationMemory\Exception\InvalidXliff;
use Phil\TranslationMemory\Glossary\Glossary;
use Phil\TranslationMemory\Glossary\TermHit;
use Phil\TranslationMemory\Glossary\TermIssue;
use Phil\TranslationMemory\LanguageTag;
use Phil\TranslationMemory\Memory\MatchKind;
use Phil\TranslationMemory\Memory\MemoryMatch;
use Phil\TranslationMemory\Memory\TranslationMemory;
use Phil\TranslationMemory\Segment;
use Phil\TranslationMemory\Segmentation\UjoSegmenter;
use Phil\TranslationMemory\SegmentState;
use Phil\TranslationMemory\SegmentSync;
use Phil\TranslationMemory\SyncResult;
use Phil\TranslationMemory\Text;
use Phil\TranslationMemory\Variants;
use Phil\TranslationMemory\Xliff\MergeResult;
use Phil\TranslationMemory\Xliff\XliffFile;
use Phil\TranslationMemory\Xliff\XliffMerge;
use Phil\TranslationMemory\Xliff\XliffReader;
use Phil\TranslationMemory\Xliff\XliffWriter;
use Phil\Ujo\Document;
use Psr\Clock\ClockInterface;
use Throwable;

/**
 * The translation of documents, segment by segment, in the stores of the bundle.
 *
 * A document is prepared in a target language: its source is cut into segments and the stored ones brought up to
 * date — kept, moved, made stale, added, removed —, and the new ones proposed the exact matches of the memory. Each
 * segment is then translated, proposed, reviewed, confirmed or cleared; a target that becomes usable is learnt by the
 * memory. Segments leave and come back as XLIFF, and the translated document is put together from them.
 *
 * Each row says who wrote its target and who reviewed it — the identifiers the application's `Translators` gives —,
 * whether a machine proposed it, and the flags of its quality checks. With a `TranslationPolicy`, the memory proposes
 * — and prefills — only the units learnt from a document the current user may translate, or learnt from no document. A regional variant reads along its chain: the
 * published `fr-CA` document takes the `fr` targets where `fr-CA` has none, then the source; the raw reading gives the
 * variant's own targets only.
 */
final readonly class Translations
{
    public function __construct(
        private SegmentRepository $segments,
        private TranslationMemory $memory,
        private Glossaries $glossaries,
        private UjoSegmenter $segmenter,
        private ClockInterface $clock,
        private bool $prefill = true,
        private Variants $variants = new Variants(),
        private ?Translators $translators = null,
        private ?MachineTranslator $machine = null,
        private ?Checker $checker = null,
        private ?TranslationPolicy $policy = null,
    ) {
    }

    /**
     * The most segments sent to the machine translator in one call.
     */
    public const int MACHINE_BATCH = 50;

    /**
     * Cuts the source of a document and brings its segments in a target language up to date.
     */
    public function prepare(string $document, Document $source, LanguageTag $sourceLanguage, LanguageTag $targetLanguage): SyncResult
    {
        [$records, $result] = $this->sync($document, $source, $sourceLanguage, $targetLanguage);
        $byKey = [];

        foreach ($records as $record) {
            $byKey[$record->key()] = $record;
        }

        $now = $this->now();
        $kept = [];

        foreach ($result->segments as $position => $segment) {
            $record = $byKey[$segment->key] ?? SegmentRecord::of($document, $sourceLanguage, $targetLanguage, $position, $segment, $now);
            $record->update($segment, $position, $now);
            $kept[] = $record;
            unset($byKey[$segment->key]);
        }

        $this->segments->remove(...$byKey);
        $this->segments->save(...$kept);

        return $result;
    }

    /**
     * The segments a preparation would give, without storing anything: what a screen or an export shows on a request
     * that only reads.
     */
    public function preview(string $document, Document $source, LanguageTag $sourceLanguage, LanguageTag $targetLanguage): SyncResult
    {
        return $this->sync($document, $source, $sourceLanguage, $targetLanguage)[1];
    }

    /**
     * The XLIFF file of a document in a target language as a preparation would leave it, without storing anything.
     */
    public function exportPreview(string $document, Document $source, LanguageTag $sourceLanguage, LanguageTag $targetLanguage): string
    {
        return new XliffWriter()->write(XliffFile::fromSegments($document, $sourceLanguage, $targetLanguage, $this->preview($document, $source, $sourceLanguage, $targetLanguage)->segments));
    }

    /**
     * The segments of a document in a target language, in the order of its source.
     *
     * @return list<Segment>
     */
    public function segments(string $document, LanguageTag $targetLanguage): array
    {
        return array_map(static fn (SegmentRecord $record): Segment => $record->segment(), $this->segments->forDocument($document, $targetLanguage));
    }

    /**
     * @throws NotFound
     */
    public function segment(string $document, LanguageTag $targetLanguage, string $key): Segment
    {
        return $this->record($document, $targetLanguage, $key)->segment();
    }

    /**
     * @throws NotFound
     * @throws InvalidSegment
     */
    public function translate(string $document, LanguageTag $targetLanguage, string $key, string $target): Segment
    {
        return $this->change($document, $targetLanguage, $key, static fn (Segment $segment): Segment => $segment->translate($target), fn (): array => [$this->me(), null, false]);
    }

    /**
     * @throws NotFound
     * @throws InvalidSegment
     */
    public function suggest(string $document, LanguageTag $targetLanguage, string $key, string $target): Segment
    {
        return $this->change($document, $targetLanguage, $key, static fn (Segment $segment): Segment => $segment->suggest($target), fn (): array => [$this->me(), null, false]);
    }

    /**
     * @throws NotFound
     * @throws InvalidSegment when the segment has no target, or a stale one
     */
    public function review(string $document, LanguageTag $targetLanguage, string $key): Segment
    {
        return $this->change($document, $targetLanguage, $key, static fn (Segment $segment): Segment => $segment->review(), fn (SegmentRecord $record): array => [$record->translatedBy(), $this->me(), $record->isMachine()]);
    }

    /**
     * @throws NotFound
     * @throws InvalidSegment when the segment is not stale
     */
    public function confirm(string $document, LanguageTag $targetLanguage, string $key): Segment
    {
        return $this->change($document, $targetLanguage, $key, static fn (Segment $segment): Segment => $segment->confirm(), static fn (SegmentRecord $record): array => [$record->translatedBy(), null, $record->isMachine()]);
    }

    /**
     * @throws NotFound
     */
    public function clear(string $document, LanguageTag $targetLanguage, string $key): Segment
    {
        return $this->change($document, $targetLanguage, $key, static fn (Segment $segment): Segment => $segment->clear(), static fn (): array => [null, null, false]);
    }

    /**
     * What the memory proposes for the source of a segment.
     *
     * @return list<MemoryMatch>
     *
     * @throws NotFound
     */
    public function suggestions(string $document, LanguageTag $targetLanguage, string $key): array
    {
        $record = $this->record($document, $targetLanguage, $key);

        return $this->matches($record->segment(), $record->sourceLanguage(), $targetLanguage);
    }

    /**
     * What the memory proposes for the source of a segment at hand: with a policy, only the units learnt from a
     * document the current user may translate, or from no document.
     *
     * @return list<MemoryMatch>
     */
    public function matches(Segment $segment, LanguageTag $sourceLanguage, LanguageTag $targetLanguage): array
    {
        return array_values(array_filter(
            $this->memory->lookup($segment->source, $sourceLanguage, $targetLanguage),
            fn (MemoryMatch $match): bool => null === $match->unit->origin || !$this->policy instanceof TranslationPolicy || $this->policy->canTranslate($match->unit->origin, $targetLanguage),
        ));
    }

    /**
     * The glossary of a pair of languages, to find and check the terms of many segments.
     */
    public function glossary(LanguageTag $sourceLanguage, LanguageTag $targetLanguage): Glossary
    {
        return $this->glossaries->glossary($sourceLanguage, $targetLanguage);
    }

    /**
     * The glossary terms of the source of a segment.
     *
     * @return list<TermHit>
     *
     * @throws NotFound
     */
    public function terms(string $document, LanguageTag $targetLanguage, string $key): array
    {
        $record = $this->record($document, $targetLanguage, $key);

        return $this->glossaries->glossary($record->sourceLanguage(), $targetLanguage)->find($record->segment()->source);
    }

    /**
     * The issues of the target of a segment against the glossary; none without a target.
     *
     * @return list<TermIssue>
     *
     * @throws NotFound
     */
    public function issues(string $document, LanguageTag $targetLanguage, string $key): array
    {
        $record = $this->record($document, $targetLanguage, $key);
        $segment = $record->segment();

        return null === $segment->target ? [] : $this->glossaries->glossary($record->sourceLanguage(), $targetLanguage)->check($segment->source, $segment->target);
    }

    public function progress(string $document, LanguageTag $targetLanguage): Progress
    {
        return Progress::of($this->segments($document, $targetLanguage));
    }

    /**
     * The segments of a document as an XLIFF 2.1 file.
     *
     * @throws NotFound when the document has no segments in the language
     */
    public function export(string $document, LanguageTag $targetLanguage): string
    {
        $records = $this->prepared($document, $targetLanguage);

        return new XliffWriter()->write(XliffFile::fromSegments($document, $records[0]->sourceLanguage(), $targetLanguage, array_map(static fn (SegmentRecord $record): Segment => $record->segment(), $records)));
    }

    /**
     * Merges an XLIFF file into the segments of a document; the targets it makes usable are learnt by the memory.
     *
     * A unit the file marks `final` or `signed-off` is merged as reviewed only when whoever imports may review: without
     * that right, it is merged as translated, as if they had written it on the screen.
     *
     * @param bool $canReview whether whoever imports may mark targets as reviewed; refused by default
     *
     * @throws NotFound        when the document has no segments in the language
     * @throws InvalidXliff    when the file cannot be read
     * @throws InvalidLanguage when the file is of other languages
     */
    public function import(string $document, LanguageTag $targetLanguage, string $xliff, bool $canReview = false): MergeResult
    {
        $records = $this->prepared($document, $targetLanguage);
        $sourceLanguage = $records[0]->sourceLanguage();
        $merged = XliffMerge::merge(array_map(static fn (SegmentRecord $record): Segment => $record->segment(), $records), new XliffReader()->read($xliff), $sourceLanguage, $targetLanguage);
        $result = $canReview ? $merged : new MergeResult(array_map($this->unreviewed(...), $merged->segments), $merged->applied, $merged->skipped);
        $now = $this->now();
        $changed = [];

        foreach ($result->segments as $position => $segment) {
            if (\in_array($segment->key, $result->applied, true)) {
                $records[$position]->update($segment, $records[$position]->position(), $now);
                $records[$position]->attribute($this->me(), SegmentState::Reviewed === $segment->state ? $this->me() : null, false);
                $changed[] = $records[$position];
                $this->memory->learn($segment, $sourceLanguage, $targetLanguage, $document);
            }
        }

        $this->segments->save(...$changed);

        return $result;
    }

    /**
     * The document a site publishes in the target language: each piece translated where the language — or, for a
     * regional variant, the nearest language of its chain — has a usable segment made from that very text, kept in
     * the source language elsewhere. `fr-CA` reads `fr-CA`, then `fr`, then the source.
     *
     * @param list<SegmentState> $states the states whose targets are used
     */
    public function translated(string $document, Document $source, LanguageTag $targetLanguage, array $states = [SegmentState::Translated, SegmentState::Reviewed]): Document
    {
        return $this->segmenter->assemble($source, $this->resolved($document, $targetLanguage), $states);
    }

    /**
     * The document in the target language from its own segments only, without the parent of a variant: what the
     * administration reads to see what a variant changes.
     *
     * @param list<SegmentState> $states the states whose targets are used
     */
    public function raw(string $document, Document $source, LanguageTag $targetLanguage, array $states = [SegmentState::Translated, SegmentState::Reviewed]): Document
    {
        return $this->segmenter->assemble($source, $this->segments($document, $targetLanguage), $states);
    }

    /**
     * The segments of a document in a target language with the usable targets its chain inherits: a `fr-CA` segment
     * without a usable target takes the `fr` one, made from the same source.
     *
     * @return list<Segment>
     */
    public function resolved(string $document, LanguageTag $targetLanguage): array
    {
        $resolved = [];

        foreach (array_reverse($this->variants->chain($targetLanguage)) as $language) {
            $resolved = $this->variants->inherit($this->segments($document, $language), $resolved);
        }

        return $resolved;
    }

    /**
     * The language a target language inherits from; `null` for a language that is not a regional variant.
     */
    public function parentOf(LanguageTag $targetLanguage): ?LanguageTag
    {
        return $this->variants->parentOf($targetLanguage);
    }

    /**
     * The rows of a document in a target language, by segment key: who wrote and reviewed each target, whether a
     * machine proposed it, and its flags.
     *
     * @return array<string, SegmentRecord>
     */
    public function records(string $document, LanguageTag $targetLanguage): array
    {
        $byKey = [];

        foreach ($this->segments->forDocument($document, $targetLanguage) as $record) {
            $byKey[$record->key()] = $record;
        }

        return $byKey;
    }

    /**
     * The issues the quality checks find in a segment at hand, its flags dismissing some; none without a target.
     *
     * @return list<CheckIssue>
     */
    public function check(Segment $segment, LanguageTag $sourceLanguage, LanguageTag $targetLanguage, Flags $flags = new Flags()): array
    {
        $checked = Checked::segment($segment, $sourceLanguage, $targetLanguage, $flags);

        return $checked instanceof Checked ? ($this->checker ?? Checker::defaults())->check($checked) : [];
    }

    /**
     * Keeps the flags of a segment's quality checks: `ignore-<check>`, `max-size:<pixels>`.
     *
     * @throws NotFound
     * @throws InvalidRecord when the flags are too long once written
     */
    public function flag(string $document, LanguageTag $targetLanguage, string $key, Flags $flags): Flags
    {
        $record = $this->record($document, $targetLanguage, $key);
        $record->flag($flags);

        $this->segments->save($record);

        return $flags;
    }

    /**
     * Dismisses a quality check on a segment, with its flag `ignore-<check>`.
     *
     * @throws NotFound
     * @throws InvalidConfiguration when the check is not a name a flag can carry
     * @throws InvalidRecord        when the flags are too long once written
     */
    public function dismiss(string $document, LanguageTag $targetLanguage, string $key, string $check): Flags
    {
        return $this->flag($document, $targetLanguage, $key, $this->record($document, $targetLanguage, $key)->flags()->with('ignore-'.$check));
    }

    /**
     * Whether a machine translator is configured.
     */
    public function canMachineTranslate(): bool
    {
        return $this->machine instanceof MachineTranslator;
    }

    /**
     * Proposes the targets of a machine translator to the untranslated segments of a prepared document, marked as
     * machine output; segments are sent by batches of 50, and a target without words is left out.
     *
     * @return int how many segments were proposed a target
     *
     * @throws MachineTranslationFailed when machine translation is off, or the translator fails or does not answer one
     *                                  target per source
     * @throws NotFound                 when the document has no segments in the language
     */
    public function machineTranslate(string $document, LanguageTag $targetLanguage): int
    {
        $machine = $this->machine ?? throw MachineTranslationFailed::off();
        $records = $this->prepared($document, $targetLanguage);
        $sourceLanguage = $records[0]->sourceLanguage();
        $waiting = array_filter($records, static fn (SegmentRecord $record): bool => SegmentState::Untranslated === $record->segment()->state);
        $now = $this->now();
        $proposed = [];

        foreach (array_chunk($waiting, self::MACHINE_BATCH) as $batch) {
            $sources = array_map(static fn (SegmentRecord $record): string => $record->segment()->source, $batch);

            try {
                $targets = $machine->translate($sources, $sourceLanguage, $targetLanguage);
            } catch (Throwable $throwable) {
                throw MachineTranslationFailed::because($machine->name(), $throwable);
            }

            if (\count($targets) !== \count($sources)) {
                throw MachineTranslationFailed::answer($machine->name(), \count($sources), \count($targets));
            }

            foreach ($batch as $index => $record) {
                if (Text::hasWords($targets[$index]) && mb_check_encoding($targets[$index], 'UTF-8')) {
                    $record->update($record->segment()->suggest($targets[$index]), $record->position(), $now);
                    $record->attribute(null, null, true);
                    $proposed[] = $record;
                }
            }
        }

        $this->segments->save(...$proposed);

        return \count($proposed);
    }

    /**
     * The people thanked in public for the translation of a document: those who wrote or reviewed its usable targets
     * and accept a credit, most segments first, then by name. Machine output credits nobody.
     *
     * @return list<Credit>
     */
    public function credits(string $document, LanguageTag $targetLanguage): array
    {
        $counts = [];

        foreach ($this->segments->forDocument($document, $targetLanguage) as $record) {
            if (!$record->segment()->isUsable()) {
                continue;
            }

            // Keyed with a prefix, so that a numeric identifier stays a string.
            if (null !== $record->translatedBy() && !$record->isMachine()) {
                $counts['#'.$record->translatedBy()][0] = ($counts['#'.$record->translatedBy()][0] ?? 0) + 1;
            }

            if (null !== $record->reviewedBy()) {
                $counts['#'.$record->reviewedBy()][1] = ($counts['#'.$record->reviewedBy()][1] ?? 0) + 1;
            }
        }

        $credits = [];

        foreach ($counts as $person => $count) {
            $name = $this->translators?->credit(substr($person, 1));

            if (null !== $name && '' !== trim($name)) {
                $credits[] = new Credit(trim($name), $count[0] ?? 0, $count[1] ?? 0);
            }
        }

        usort($credits, static fn (Credit $a, Credit $b): int => [$b->translated + $b->reviewed, $a->name] <=> [$a->translated + $a->reviewed, $b->name]);

        return $credits;
    }

    /**
     * The stored segments of a document in a target language and the result of bringing them up to date with its
     * source, the new ones proposed the exact matches of the memory; nothing is changed.
     *
     * @return array{list<SegmentRecord>, SyncResult}
     */
    private function sync(string $document, Document $source, LanguageTag $sourceLanguage, LanguageTag $targetLanguage): array
    {
        $records = $this->segments->forDocument($document, $targetLanguage);
        $result = SegmentSync::sync(array_map(static fn (SegmentRecord $record): Segment => $record->segment(), $records), $this->segmenter->segment($source));
        $segments = array_map(fn (Segment $segment): Segment => $this->prefilled($segment, $sourceLanguage, $targetLanguage), $result->segments);

        return [$records, new SyncResult($segments, $result->added, $result->stale, $result->moved, $result->removed)];
    }

    /**
     * A reviewed segment as translated, its target kept; any other segment as it is.
     */
    private function unreviewed(Segment $segment): Segment
    {
        return SegmentState::Reviewed === $segment->state ? $segment->translate((string) $segment->target) : $segment;
    }

    private function prefilled(Segment $segment, LanguageTag $sourceLanguage, LanguageTag $targetLanguage): Segment
    {
        if (!$this->prefill || SegmentState::Untranslated !== $segment->state) {
            return $segment;
        }

        $match = $this->matches($segment, $sourceLanguage, $targetLanguage)[0] ?? null;

        return $match instanceof MemoryMatch && MatchKind::Exact === $match->kind ? $segment->suggest($match->unit->target) : $segment;
    }

    /**
     * @param Closure(Segment): Segment                             $change
     * @param Closure(SegmentRecord): array{?string, ?string, bool} $attribution who wrote the target, who reviewed it,
     *                                                                           and whether a machine proposed it
     */
    private function change(string $document, LanguageTag $targetLanguage, string $key, Closure $change, Closure $attribution): Segment
    {
        $record = $this->record($document, $targetLanguage, $key);
        $segment = $change($record->segment());
        [$translatedBy, $reviewedBy, $machine] = $attribution($record);
        $record->update($segment, $record->position(), $this->now());
        $record->attribute($translatedBy, $reviewedBy, $machine);

        $this->segments->save($record);
        $this->memory->learn($segment, $record->sourceLanguage(), $targetLanguage, $document);

        return $segment;
    }

    private function record(string $document, LanguageTag $targetLanguage, string $key): SegmentRecord
    {
        foreach ($this->segments->forDocument($document, $targetLanguage) as $record) {
            if ($record->key() === $key) {
                return $record;
            }
        }

        throw NotFound::segment($document, $targetLanguage->value, $key);
    }

    /**
     * @return non-empty-list<SegmentRecord>
     */
    private function prepared(string $document, LanguageTag $targetLanguage): array
    {
        $records = $this->segments->forDocument($document, $targetLanguage);

        if ([] === $records) {
            throw NotFound::document($document, $targetLanguage->value);
        }

        return $records;
    }

    private function me(): ?string
    {
        return $this->translators?->current();
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('@'.$this->clock->now()->getTimestamp());
    }
}
