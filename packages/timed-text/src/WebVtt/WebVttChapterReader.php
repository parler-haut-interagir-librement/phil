<?php

declare(strict_types=1);

namespace Phil\TimedText\WebVtt;

use Phil\TimedText\Chapter;
use Phil\TimedText\ChapterList;
use Phil\TimedText\Contract\ChapterReader;
use Phil\TimedText\Cue;
use Phil\TimedText\Limits;

/**
 * Reads WebVTT chapters, a WebVTT file whose cues are the chapters: each cue gives a start, an end and, from its plain
 * text, a title. The file is read strictly, as `WebVttReader::read()` does.
 */
final readonly class WebVttChapterReader implements ChapterReader
{
    private WebVttReader $reader;

    public function __construct(Limits $limits = new Limits())
    {
        $this->reader = new WebVttReader($limits);
    }

    public function read(string $input): ChapterList
    {
        return new ChapterList(array_map(
            static fn (Cue $cue): Chapter => new Chapter($cue->start, $cue->plainText(), $cue->end),
            $this->reader->read($input)->cues,
        ));
    }
}
