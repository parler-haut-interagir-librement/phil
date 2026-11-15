<?php

declare(strict_types=1);

namespace Phil\TimedText\WebVtt;

use Phil\TimedText\ChapterList;
use Phil\TimedText\Contract\ChapterWriter;
use Phil\TimedText\Cue;
use Phil\TimedText\CueText;
use Phil\TimedText\Exception\InvalidCue;
use Phil\TimedText\Track;

/**
 * Writes WebVTT chapters: one cue per chapter, its title as text. A cue needs an end: a chapter without one ends where
 * the next starts, and the last at the duration of the media, given here. Links, images, places and the table of
 * contents have no place in WebVTT and are left out.
 */
final readonly class WebVttChapterWriter implements ChapterWriter
{
    public function __construct(private ?int $duration = null)
    {
    }

    /**
     * @throws InvalidCue when the last chapter has no end and no duration is given
     */
    public function write(ChapterList $chapters): string
    {
        $cues = [];

        foreach ($chapters->withEnds($this->duration) as [$chapter, $end]) {
            if (null === $end) {
                throw new InvalidCue('The last chapter has no end: give the duration of the media to the writer.');
            }

            $cues[] = new Cue($chapter->start, $end, CueText::fromPlain($chapter->title));
        }

        return new WebVttWriter()->write(new Track($cues));
    }
}
