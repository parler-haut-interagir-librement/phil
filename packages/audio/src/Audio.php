<?php

declare(strict_types=1);

namespace Phil\Audio;

use Phil\Audio\Exception\InvalidAudio;
use Phil\TimedText\ChapterList;

/**
 * A track as a player shows it: its title, its files, the creator's listening policy, its duration, its waveform, its
 * chapters and its transcripts, the last two in the model of phil/timed-text.
 */
final readonly class Audio
{
    /**
     * @param list<AudioSource>     $sources     in the order a browser tries them
     * @param int|null              $duration    in milliseconds
     * @param list<AudioTranscript> $transcripts
     *
     * @throws InvalidAudio when the title is empty or the duration negative
     */
    public function __construct(
        public string $title,
        public array $sources,
        public ListeningPolicy $policy = new ListeningPolicy(),
        public ?int $duration = null,
        public ?Peaks $peaks = null,
        public ?ChapterList $chapters = null,
        public array $transcripts = [],
    ) {
        if ('' === trim($title)) {
            throw new InvalidAudio('A track has a title.');
        }

        if (null !== $duration && $duration < 0) {
            throw new InvalidAudio('A duration is not negative.');
        }
    }

    /**
     * What this listener may hear: what the policy lets them hear, the whole track or its excerpt.
     */
    public function mode(bool $entitled): ListeningMode
    {
        return $this->policy->forListener($entitled);
    }

    /**
     * The files this listener's player may fetch: the whole files when they may hear the whole track, the files of the
     * excerpt when they may hear only it, none before a purchase.
     *
     * @return list<AudioSource>
     */
    public function playable(bool $entitled): array
    {
        $mode = $this->mode($entitled);

        if (ListeningMode::PurchaseOnly === $mode) {
            return [];
        }

        $excerpt = ListeningMode::Excerpt === $mode;

        return array_values(array_filter($this->sources, static fn (AudioSource $source): bool => $source->excerpt === $excerpt));
    }
}
