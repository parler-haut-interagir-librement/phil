<?php

declare(strict_types=1);

namespace Phil\TimedText;

use Phil\TimedText\Contract\ChapterReader;
use Phil\TimedText\Contract\ChapterWriter;
use Phil\TimedText\Podcast\JsonChapterReader;
use Phil\TimedText\Podcast\JsonChapterWriter;
use Phil\TimedText\WebVtt\WebVttChapterReader;
use Phil\TimedText\WebVtt\WebVttChapterWriter;

/**
 * The formats of chapters: Podcasting 2.0 JSON chapters, as `<podcast:chapters>` declares them, and WebVTT chapters.
 */
enum ChapterFormat: string
{
    case Json = 'application/json+chapters';
    case WebVtt = 'text/vtt';

    /**
     * The format of a media type, its parameters and case ignored; null for any other.
     */
    public static function fromMediaType(string $mediaType): ?self
    {
        return self::tryFrom(strtolower(trim(explode(';', $mediaType, 2)[0])));
    }

    public function reader(Limits $limits = new Limits()): ChapterReader
    {
        return match ($this) {
            self::Json => new JsonChapterReader($limits),
            self::WebVtt => new WebVttChapterReader($limits),
        };
    }

    /**
     * @param ?int $duration the duration of the media, which ends the last WebVTT chapter when it has no end
     */
    public function writer(?int $duration = null): ChapterWriter
    {
        return match ($this) {
            self::Json => new JsonChapterWriter(),
            self::WebVtt => new WebVttChapterWriter($duration),
        };
    }
}
