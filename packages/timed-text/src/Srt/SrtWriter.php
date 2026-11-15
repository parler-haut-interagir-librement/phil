<?php

declare(strict_types=1);

namespace Phil\TimedText\Srt;

use Phil\TimedText\Contract\TrackWriter;
use Phil\TimedText\CueText;
use Phil\TimedText\Timecode;
use Phil\TimedText\Track;

/**
 * Writes SubRip (SRT): the cues numbered from 1, their timings, and their text with `<b>`, `<i>` and `<u>` only, the
 * character references decoded. Identifiers, settings, regions, styles and notes have no place in SRT and are left
 * out. Lines end with LF.
 */
final readonly class SrtWriter implements TrackWriter
{
    public function write(Track $track): string
    {
        $blocks = [];

        foreach ($track->cues as $number => $cue) {
            $lines = array_filter(explode("\n", CueText::toSrt($cue->text)), static fn (string $line): bool => '' !== trim($line));
            $blocks[] = ($number + 1)."\n".Timecode::formatSrt($cue->start).' --> '.Timecode::formatSrt($cue->end)
                .([] === $lines ? '' : "\n".implode("\n", $lines));
        }

        return [] === $blocks ? '' : implode("\n\n", $blocks)."\n";
    }
}
