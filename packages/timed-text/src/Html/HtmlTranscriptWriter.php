<?php

declare(strict_types=1);

namespace Phil\TimedText\Html;

use Phil\TimedText\Contract\TrackWriter;
use Phil\TimedText\Timecode;
use Phil\TimedText\Track;

/**
 * Writes an HTML transcript, `text/html`, the shape Podcasting 2.0 describes: the speaker in a `<cite>` when it
 * changes, the start in a `<time>` whose `datetime` is an ISO 8601 duration, the text in a `<p>`. The fragment is
 * escaped by construction: every text and attribute goes through `htmlspecialchars()`, and no markup of a cue is
 * copied, only its plain text with `<br>` at its line breaks. It goes inside the application's own page.
 */
final readonly class HtmlTranscriptWriter implements TrackWriter
{
    public function write(Track $track): string
    {
        $html = '';
        $speaker = null;

        foreach ($track->cues as $cue) {
            $voice = $cue->speaker();

            if (null !== $voice && $voice !== $speaker) {
                $html .= '<cite>'.$this->escape($voice).":</cite>\n";
            }

            $speaker = $voice ?? $speaker;
            $html .= '<time datetime="'.Timecode::formatDuration($cue->start).'">'.Timecode::formatClock($cue->start)."</time>\n"
                .'<p>'.str_replace("\n", '<br>', $this->escape($cue->plainText()))."</p>\n";
        }

        return $html;
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, \ENT_QUOTES | \ENT_HTML5 | \ENT_SUBSTITUTE, 'UTF-8');
    }
}
