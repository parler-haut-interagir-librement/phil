<?php

declare(strict_types=1);

namespace Phil\LegalNotices;

use DateTimeImmutable;
use DateTimeInterface;
use Phil\LegalNotices\Exception\InvalidLegalNotice;

/**
 * One version of a document: its number, its body in each locale and the format the body is written in, the moment it
 * takes effect, where it stands, the moment it was published and, maybe, a short summary of what changed. Nothing of
 * it changes once it is built: publishing or withdrawing it gives a new value, and a correction is a new version.
 */
final readonly class LegalDocumentVersion
{
    public const int MAX_NUMBER = 999999;

    public const int MAX_BODY_BYTES = 262144;

    public const int MAX_SUMMARY_BYTES = 1000;

    public const string FORMAT_PATTERN = '/^[a-z][a-z0-9_-]{0,31}$/D';

    /**
     * @var non-empty-array<string, non-empty-string> the body of each locale
     */
    public array $bodies;

    /**
     * @var array<string, non-empty-string> the summary of the changes in each locale, maybe none
     */
    public array $summaries;

    /**
     * @param array<array-key, mixed> $bodies    by locale
     * @param array<array-key, mixed> $summaries by locale
     *
     * @throws InvalidLegalNotice
     */
    public function __construct(
        public DocumentType $type,
        public int $number,
        array $bodies,
        public string $format,
        public DateTimeImmutable $effectiveAt,
        public PublicationState $state = PublicationState::Draft,
        public ?DateTimeImmutable $publishedAt = null,
        array $summaries = [],
    ) {
        if ($number < 1 || $number > self::MAX_NUMBER) {
            throw InvalidLegalNotice::because('version_number', 'A version number goes from 1 to 999999.');
        }

        if (1 !== preg_match(self::FORMAT_PATTERN, $format)) {
            throw InvalidLegalNotice::because('format', \sprintf('%s is not the name of a format such as "text" or "markdown".', InvalidLegalNotice::quote($format)));
        }

        $this->checkDates($state, $effectiveAt, $publishedAt);

        $bodies = Locales::texts($bodies, self::MAX_BODY_BYTES, 'body');
        $this->bodies = [] !== $bodies ? $bodies : throw InvalidLegalNotice::because('no_body', 'A version has the body of one locale at least.');
        $this->summaries = Locales::texts($summaries, self::MAX_SUMMARY_BYTES, 'summary');
    }

    /**
     * The body in a locale, else in its language, else in the fallback locale, else in the first locale.
     */
    public function body(string $locale, string $fallback): string
    {
        return Locales::pick($this->bodies, $locale, $fallback);
    }

    /**
     * The summary of the changes in a locale, as the body is found, or `null` when the version has none.
     */
    public function summary(string $locale, string $fallback): ?string
    {
        return [] === $this->summaries ? null : Locales::pick($this->summaries, $locale, $fallback);
    }

    /**
     * @return non-empty-list<string>
     */
    public function locales(): array
    {
        return array_keys($this->bodies);
    }

    /**
     * Whether the version is shown to the public: published or withdrawn, never a draft.
     */
    public function isPublic(): bool
    {
        return PublicationState::Draft !== $this->state;
    }

    /**
     * Whether the version is published and has taken effect at a moment.
     */
    public function isInForceAt(DateTimeInterface $at): bool
    {
        return PublicationState::Published === $this->state && $this->effectiveAt <= $at;
    }

    /**
     * The draft published at a moment, which may not come after its effective date.
     *
     * @throws InvalidLegalNotice
     */
    public function publish(DateTimeImmutable $at): self
    {
        if (PublicationState::Draft !== $this->state) {
            throw InvalidLegalNotice::because('not_a_draft', \sprintf('Version %d is %s: only a draft is published.', $this->number, $this->state->value));
        }

        return new self($this->type, $this->number, $this->bodies, $this->format, $this->effectiveAt, PublicationState::Published, $at, $this->summaries);
    }

    /**
     * The published version withdrawn: still listed, never in force again.
     *
     * @throws InvalidLegalNotice
     */
    public function withdraw(): self
    {
        if (PublicationState::Published !== $this->state) {
            throw InvalidLegalNotice::because('not_published', \sprintf('Version %d is %s: only a published version is withdrawn.', $this->number, $this->state->value));
        }

        return new self($this->type, $this->number, $this->bodies, $this->format, $this->effectiveAt, PublicationState::Withdrawn, $this->publishedAt, $this->summaries);
    }

    private function checkDates(PublicationState $state, DateTimeImmutable $effectiveAt, ?DateTimeImmutable $publishedAt): void
    {
        if (PublicationState::Draft === $state) {
            if ($publishedAt instanceof DateTimeImmutable) {
                throw InvalidLegalNotice::because('draft_published_at', 'A draft has no publication date.');
            }

            return;
        }

        if (!$publishedAt instanceof DateTimeImmutable) {
            throw InvalidLegalNotice::because('no_publication_date', 'A published or withdrawn version has its publication date.');
        }

        if ($effectiveAt < $publishedAt) {
            throw InvalidLegalNotice::because('effective_before_publication', 'A version takes effect when it is published or later.');
        }
    }
}
