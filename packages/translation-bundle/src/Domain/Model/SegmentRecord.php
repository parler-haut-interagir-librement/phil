<?php

declare(strict_types=1);

namespace Phil\TranslationBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\TranslationBundle\Domain\Exception\InvalidRecord;
use Phil\TranslationMemory\Check\Flags;
use Phil\TranslationMemory\LanguageTag;
use Phil\TranslationMemory\Segment;
use Phil\TranslationMemory\SegmentState;

/**
 * One segment of a document in one target language, as a row identified by the document, the target language and the
 * key of the segment: the source language, the position of the segment, its source and fingerprint, its state, target
 * and the source the target was written for; who wrote the target and who reviewed it, whether a machine proposed it,
 * and the flags of its quality checks.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
final class SegmentRecord
{
    public const string TABLE = 'phil_translation_segment';

    public const int MAX_DOCUMENT = 255;

    public const int MAX_PERSON = 255;

    public const int MAX_FLAGS = 1024;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'document', length: self::MAX_DOCUMENT)]
        private readonly string $document,
        #[ORM\Column(name: 'source_language', length: 35)]
        private readonly string $sourceLanguage,
        #[ORM\Id]
        #[ORM\Column(name: 'target_language', length: 35)]
        private readonly string $targetLanguage,
        #[ORM\Id]
        #[ORM\Column(name: 'segment_key', length: Segment::MAX_KEY)]
        private readonly string $segmentKey,
        #[ORM\Column(name: 'position')]
        private int $position,
        #[ORM\Column(name: 'source', type: 'text')]
        private string $source,
        #[ORM\Column(name: 'fingerprint', length: 64)]
        private string $fingerprint,
        #[ORM\Column(name: 'state', length: 16)]
        private string $state,
        #[ORM\Column(name: 'target', type: 'text', nullable: true)]
        private ?string $target,
        #[ORM\Column(name: 'translated_source', type: 'text', nullable: true)]
        private ?string $translatedSource,
        #[ORM\Column(name: 'updated_at')]
        private DateTimeImmutable $updatedAt,
        #[ORM\Column(name: 'translated_by', length: self::MAX_PERSON, nullable: true)]
        private ?string $translatedBy = null,
        #[ORM\Column(name: 'reviewed_by', length: self::MAX_PERSON, nullable: true)]
        private ?string $reviewedBy = null,
        #[ORM\Column(name: 'machine')]
        private bool $machine = false,
        #[ORM\Column(name: 'flags', length: self::MAX_FLAGS)]
        private string $flags = '',
    ) {
    }

    /**
     * @throws InvalidRecord when the document is not 1 to 255 bytes
     */
    public static function of(string $document, LanguageTag $sourceLanguage, LanguageTag $targetLanguage, int $position, Segment $segment, DateTimeImmutable $now): self
    {
        if ('' === $document || \strlen($document) > self::MAX_DOCUMENT) {
            throw InvalidRecord::because(\sprintf('A document is referred to by 1 to %d bytes.', self::MAX_DOCUMENT));
        }

        return new self($document, $sourceLanguage->value, $targetLanguage->value, $segment->key, $position, $segment->source, $segment->fingerprint->value, $segment->state->value, $segment->target, $segment->translatedSource, $now);
    }

    /**
     * Writes a new state of the segment, at its position.
     *
     * @throws InvalidRecord when the segment has another key
     */
    public function update(Segment $segment, int $position, DateTimeImmutable $now): void
    {
        if ($segment->key !== $this->segmentKey) {
            throw InvalidRecord::because(\sprintf('The row of segment "%s" cannot take segment "%s".', $this->segmentKey, $segment->key));
        }

        $this->position = $position;
        $this->source = $segment->source;
        $this->fingerprint = $segment->fingerprint->value;
        $this->state = $segment->state->value;
        $this->target = $segment->target;
        $this->translatedSource = $segment->translatedSource;
        $this->updatedAt = $now;
    }

    /**
     * Says who wrote the target and who reviewed it, and whether a machine proposed it.
     *
     * @throws InvalidRecord when a person is referred to by more than 255 bytes or by none
     */
    public function attribute(?string $translatedBy, ?string $reviewedBy, bool $machine): void
    {
        foreach ([$translatedBy, $reviewedBy] as $person) {
            if (null !== $person && ('' === $person || \strlen($person) > self::MAX_PERSON)) {
                throw InvalidRecord::because(\sprintf('A person is referred to by 1 to %d bytes.', self::MAX_PERSON));
            }
        }

        $this->translatedBy = $translatedBy;
        $this->reviewedBy = $reviewedBy;
        $this->machine = $machine;
    }

    /**
     * Keeps the flags of the segment's quality checks.
     *
     * @throws InvalidRecord when the flags are longer than 1024 bytes once written
     */
    public function flag(Flags $flags): void
    {
        $written = $flags->toString();

        if (\strlen($written) > self::MAX_FLAGS) {
            throw InvalidRecord::because(\sprintf('The flags of a segment are at most %d bytes.', self::MAX_FLAGS));
        }

        $this->flags = $written;
    }

    public function flags(): Flags
    {
        return Flags::parse($this->flags);
    }

    public function translatedBy(): ?string
    {
        return $this->translatedBy;
    }

    public function reviewedBy(): ?string
    {
        return $this->reviewedBy;
    }

    /**
     * Whether the target was proposed by machine translation and nobody has written another since.
     */
    public function isMachine(): bool
    {
        return $this->machine;
    }

    public function segment(): Segment
    {
        return new Segment($this->segmentKey, $this->source, SegmentState::from($this->state), $this->target, $this->translatedSource);
    }

    public function document(): string
    {
        return $this->document;
    }

    public function key(): string
    {
        return $this->segmentKey;
    }

    public function position(): int
    {
        return $this->position;
    }

    public function sourceLanguage(): LanguageTag
    {
        return LanguageTag::of($this->sourceLanguage);
    }

    public function targetLanguage(): LanguageTag
    {
        return LanguageTag::of($this->targetLanguage);
    }

    public function fingerprint(): string
    {
        return $this->fingerprint;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
