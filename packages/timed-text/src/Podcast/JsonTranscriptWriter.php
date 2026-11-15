<?php

declare(strict_types=1);

namespace Phil\TimedText\Podcast;

use Phil\TimedText\Contract\TrackWriter;
use Phil\TimedText\Internal\Json;
use Phil\TimedText\Timecode;
use Phil\TimedText\Track;

/**
 * Writes a Podcasting 2.0 JSON transcript, version 1.0.0: one segment per cue, its `speaker` from the voice that opens
 * the text, its times in seconds, and its `body` as plain text.
 */
final readonly class JsonTranscriptWriter implements TrackWriter
{
    public const string VERSION = '1.0.0';

    public function write(Track $track): string
    {
        $segments = [];

        foreach ($track->cues as $cue) {
            $speaker = $cue->speaker();
            $segments[] = (null === $speaker ? [] : ['speaker' => $speaker]) + [
                'startTime' => Timecode::toSeconds($cue->start),
                'endTime' => Timecode::toSeconds($cue->end),
                'body' => $cue->plainText(),
            ];
        }

        return Json::encode(['version' => self::VERSION, 'segments' => $segments]);
    }
}
