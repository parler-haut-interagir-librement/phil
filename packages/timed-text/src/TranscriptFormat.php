<?php

declare(strict_types=1);

namespace Phil\TimedText;

use Phil\TimedText\Contract\TrackReader;
use Phil\TimedText\Contract\TrackWriter;
use Phil\TimedText\Html\HtmlTranscriptReader;
use Phil\TimedText\Html\HtmlTranscriptWriter;
use Phil\TimedText\Podcast\JsonTranscriptReader;
use Phil\TimedText\Podcast\JsonTranscriptWriter;
use Phil\TimedText\Srt\SrtReader;
use Phil\TimedText\Srt\SrtWriter;
use Phil\TimedText\WebVtt\WebVttReader;
use Phil\TimedText\WebVtt\WebVttWriter;

/**
 * The formats of a track: the media types a Podcasting 2.0 `<podcast:transcript>` declares, and the reader and writer
 * of each.
 */
enum TranscriptFormat: string
{
    case WebVtt = 'text/vtt';
    case Srt = 'application/srt';
    case Json = 'application/json';
    case Html = 'text/html';

    /**
     * The format of a media type, its parameters and case ignored, `application/x-subrip` and `text/srt` read as SRT;
     * null for any other.
     */
    public static function fromMediaType(string $mediaType): ?self
    {
        $type = strtolower(trim(explode(';', $mediaType, 2)[0]));

        return self::tryFrom(match ($type) {
            'application/x-subrip', 'text/srt' => self::Srt->value,
            default => $type,
        });
    }

    public function extension(): string
    {
        return match ($this) {
            self::WebVtt => 'vtt',
            self::Srt => 'srt',
            self::Json => 'json',
            self::Html => 'html',
        };
    }

    public function reader(Limits $limits = new Limits()): TrackReader
    {
        return match ($this) {
            self::WebVtt => new WebVttReader($limits),
            self::Srt => new SrtReader($limits),
            self::Json => new JsonTranscriptReader($limits),
            self::Html => new HtmlTranscriptReader($limits),
        };
    }

    public function writer(): TrackWriter
    {
        return match ($this) {
            self::WebVtt => new WebVttWriter(),
            self::Srt => new SrtWriter(),
            self::Json => new JsonTranscriptWriter(),
            self::Html => new HtmlTranscriptWriter(),
        };
    }
}
