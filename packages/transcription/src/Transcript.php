<?php

declare(strict_types=1);

namespace Phil\Transcription;

use Phil\TimedText\Cue;
use Phil\TimedText\CueText;
use Phil\TimedText\Track;
use Phil\TimedText\TranscriptFormat;
use Phil\Transcription\Exception\NotReviewed;

/**
 * The timed segments of a recording in its declared language, and whether a person reviewed them. It becomes a
 * phil/timed-text `Track` at any time, a speaker as the voice of each cue, for an editor to correct; it is written as
 * WebVTT, SRT, a Podcasting 2.0 JSON transcript or HTML only once reviewed.
 */
final readonly class Transcript
{
    /**
     * @param list<Segment> $segments in the order of the recording
     * @param string|null   $engine   what transcribed it, for the record: `whisper.cpp`, a service and its model
     */
    public function __construct(
        public Language $language,
        public array $segments,
        public ReviewState $review = ReviewState::ToReview,
        public ?string $engine = null,
    ) {
    }

    /**
     * The transcript of a track an editor corrected, each cue a segment, its voice the speaker.
     */
    public static function fromTrack(Track $track, Language $language, ReviewState $review = ReviewState::ToReview, ?string $engine = null): self
    {
        $segments = [];

        foreach ($track->cues as $cue) {
            if ('' !== trim($cue->plainText())) {
                $segments[] = new Segment($cue->start, $cue->end, $cue->plainText(), $cue->speaker());
            }
        }

        return new self($language, $segments, $review, $engine);
    }

    /**
     * The same transcript, reviewed by a person.
     */
    public function reviewed(): self
    {
        return new self($this->language, $this->segments, ReviewState::Reviewed, $this->engine);
    }

    /**
     * The transcript with other segments: to review again.
     *
     * @param list<Segment> $segments
     */
    public function withSegments(array $segments): self
    {
        return new self($this->language, $segments, ReviewState::ToReview, $this->engine);
    }

    public function isPublishable(): bool
    {
        return ReviewState::Reviewed === $this->review;
    }

    /**
     * The text of the whole recording, a segment per line.
     */
    public function text(): string
    {
        return implode("\n", array_map(static fn (Segment $segment): string => $segment->text, $this->segments));
    }

    /**
     * The segments as the cues of a track, reviewed or not: what an editor corrects.
     */
    public function toTrack(): Track
    {
        return new Track(array_map(
            static fn (Segment $segment): Cue => new Cue($segment->start, $segment->end, CueText::fromPlain($segment->text, $segment->speaker)),
            $this->segments,
        ));
    }

    /**
     * The transcript written in a format, once reviewed.
     *
     * @throws NotReviewed when no person reviewed it
     */
    public function publish(TranscriptFormat $format): string
    {
        if (!$this->isPublishable()) {
            throw new NotReviewed('A transcript is reviewed by a person before it is published.');
        }

        return $format->writer()->write($this->toTrack());
    }
}
