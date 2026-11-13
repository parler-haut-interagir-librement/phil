<?php

declare(strict_types=1);

namespace Phil\Epub;

use Phil\Epub\Exception\InvalidBook;

/**
 * The narration synchronised with the text of a chapter: its clips in reading order, each highlighting an element
 * while its audio plays. The writer turns it into a SMIL document beside the chapter and declares its duration.
 */
final readonly class MediaOverlay
{
    /**
     * @var list<Clip>
     */
    public array $clips;

    /**
     * @throws InvalidBook
     */
    public function __construct(Clip ...$clips)
    {
        if ([] === $clips) {
            throw InvalidBook::because('A media overlay needs at least one clip.');
        }

        $this->clips = array_values($clips);
    }

    /**
     * How long the overlay plays, in milliseconds: the sum of its clips.
     */
    public function duration(): int
    {
        return array_sum(array_map(static fn (Clip $clip): int => $clip->duration(), $this->clips));
    }
}
