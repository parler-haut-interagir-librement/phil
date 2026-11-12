<?php

declare(strict_types=1);

namespace Phil\TranslationMemory;

use Phil\TranslationMemory\Exception\InvalidSegment;

/**
 * One segment of a document in one target language: its key, its source text and the fingerprint of that text, the
 * state of its translation, the target and the source the target was written for.
 *
 * A segment is a value: each change returns a copy. A target is never lost when the source changes: the segment
 * becomes `stale`, keeps its target and the source it translated, until a translator writes a new target or confirms
 * that the old one still holds.
 */
final readonly class Segment
{
    public const int MAX_KEY = 255;

    public Fingerprint $fingerprint;

    /**
     * @throws InvalidSegment when the key is empty or longer than 255 bytes, a text is not valid UTF-8, or the state
     *                        and the target disagree — a target without state, or a state without target
     */
    public function __construct(
        public string $key,
        public string $source,
        public SegmentState $state = SegmentState::Untranslated,
        public ?string $target = null,
        public ?string $translatedSource = null,
    ) {
        if ('' === $key || \strlen($key) > self::MAX_KEY || !mb_check_encoding($key, 'UTF-8')) {
            throw InvalidSegment::because(\sprintf('A segment key is 1 to %d bytes of UTF-8.', self::MAX_KEY));
        }

        foreach ([$source, $target ?? '', $translatedSource ?? ''] as $text) {
            if (!mb_check_encoding($text, 'UTF-8')) {
                throw InvalidSegment::because(\sprintf('The texts of segment "%s" must be valid UTF-8.', $key));
            }
        }

        if ($state->hasTarget() !== (null !== $target)) {
            throw InvalidSegment::because(\sprintf('Segment "%s" is %s: it must %s a target.', $key, $state->value, $state->hasTarget() ? 'have' : 'not have'));
        }

        $this->fingerprint = Fingerprint::of($source);
    }

    /**
     * A new segment of a source, untranslated.
     *
     * @throws InvalidSegment
     */
    public static function fromSource(SourceSegment $source): self
    {
        return new self($source->key, $source->text);
    }

    /**
     * The segment with a target written or accepted by a translator, for its current source.
     *
     * @throws InvalidSegment
     */
    public function translate(string $target): self
    {
        return $this->withTarget($target, SegmentState::Translated);
    }

    /**
     * The segment with a proposed target, for its current source.
     *
     * @throws InvalidSegment
     */
    public function suggest(string $target): self
    {
        return $this->withTarget($target, SegmentState::Suggested);
    }

    /**
     * The segment with its target, translated or suggested, checked by a reviewer.
     *
     * @throws InvalidSegment when the segment has no target, or a stale one
     */
    public function review(): self
    {
        if (null === $this->target || SegmentState::Stale === $this->state) {
            throw InvalidSegment::because(\sprintf('Segment "%s" is %s: only a target written for its source can be reviewed.', $this->key, $this->state->value));
        }

        return new self($this->key, $this->source, SegmentState::Reviewed, $this->target, $this->source);
    }

    /**
     * The stale segment whose target still holds for the new source: translated again, the target unchanged.
     *
     * @throws InvalidSegment when the segment is not stale
     */
    public function confirm(): self
    {
        if (SegmentState::Stale !== $this->state || null === $this->target) {
            throw InvalidSegment::because(\sprintf('Segment "%s" is %s: only a stale segment is confirmed.', $this->key, $this->state->value));
        }

        return $this->translate($this->target);
    }

    /**
     * The segment without a target.
     */
    public function clear(): self
    {
        return new self($this->key, $this->source);
    }

    /**
     * The segment for a new text of its source. The same text, white space aside, keeps the segment as it is; another
     * text makes a segment with a target stale, and leaves one without a target untranslated; a stale segment whose
     * source comes back to the text its target was written for is translated again.
     */
    public function withSource(string $source): self
    {
        if (Fingerprint::of($source)->equals($this->fingerprint)) {
            return new self($this->key, $source, $this->state, $this->target, $this->translatedSource);
        }

        if (null === $this->target) {
            return new self($this->key, $source);
        }

        if (null !== $this->translatedSource && Fingerprint::of($source)->equals(Fingerprint::of($this->translatedSource))) {
            return new self($this->key, $source, SegmentState::Translated, $this->target, $source);
        }

        return new self($this->key, $source, SegmentState::Stale, $this->target, $this->translatedSource ?? $this->source);
    }

    /**
     * The segment under another key, everything else kept.
     *
     * @throws InvalidSegment
     */
    public function withKey(string $key): self
    {
        return new self($key, $this->source, $this->state, $this->target, $this->translatedSource);
    }

    /**
     * Whether the target can be published for the current source.
     */
    public function isUsable(): bool
    {
        return $this->state->isUsable();
    }

    private function withTarget(string $target, SegmentState $state): self
    {
        return new self($this->key, $this->source, $state, $target, $this->source);
    }
}
