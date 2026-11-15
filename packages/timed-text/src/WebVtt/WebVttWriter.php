<?php

declare(strict_types=1);

namespace Phil\TimedText\WebVtt;

use Phil\TimedText\Contract\TrackWriter;
use Phil\TimedText\Cue;
use Phil\TimedText\Note;
use Phil\TimedText\StylePolicy;
use Phil\TimedText\Timecode;
use Phil\TimedText\Track;

/**
 * Writes WebVTT: `WEBVTT` and the header, the regions, the styles unless they are dropped, then the cues in their
 * order, each note before the first cue that starts at or after its anchor. Timestamps always carry their hours;
 * lines end with LF.
 */
final readonly class WebVttWriter implements TrackWriter
{
    public function __construct(private StylePolicy $styles = StylePolicy::Keep)
    {
    }

    public function write(Track $track): string
    {
        $blocks = ['' === $track->header ? 'WEBVTT' : 'WEBVTT '.$track->header];

        foreach ($track->regions as $region) {
            $blocks[] = "REGION\n".implode("\n", $region->lines());
        }

        if (StylePolicy::Keep === $this->styles) {
            foreach ($track->styles as $style) {
                $blocks[] = "STYLE\n".$style;
            }
        }

        $notes = $track->notes;

        foreach ($track->cues as $cue) {
            foreach ($notes as $key => $note) {
                if (null !== $note->at && $note->at <= $cue->start) {
                    $blocks[] = $this->note($note);
                    unset($notes[$key]);
                }
            }

            $blocks[] = $this->cue($cue);
        }

        foreach ($notes as $note) {
            $blocks[] = $this->note($note);
        }

        return implode("\n\n", $blocks)."\n";
    }

    private function note(Note $note): string
    {
        return '' === $note->text ? 'NOTE' : 'NOTE '.$note->text;
    }

    private function cue(Cue $cue): string
    {
        $timing = Timecode::formatWebVtt($cue->start).' --> '.Timecode::formatWebVtt($cue->end);

        if (!$cue->settings->isEmpty()) {
            $timing .= ' '.$cue->settings->toString();
        }

        return (null === $cue->id ? '' : $cue->id."\n").$timing.('' === $cue->text ? '' : "\n".$cue->text);
    }
}
