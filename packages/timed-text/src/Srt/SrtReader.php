<?php

declare(strict_types=1);

namespace Phil\TimedText\Srt;

use Phil\TimedText\Contract\TrackReader;
use Phil\TimedText\Inspection;
use Phil\TimedText\Internal\IssueLog;
use Phil\TimedText\Internal\SrtParser;
use Phil\TimedText\Limits;
use Phil\TimedText\Track;

/**
 * Reads SubRip (SRT): numbered cues, `hh:mm:ss,mmm` timings — a full stop or fewer digits accepted —, and their text.
 * `<b>`, `<i>` and `<u>` are kept as cue markup; `<font>`, every other tag and the `{\an8}` overrides are dropped. The
 * numbers are not kept: a writer numbers the cues again.
 *
 * `read()` refuses a file at its first problem — a cue without its number, a block it cannot read, a cue out of
 * order; `inspect()` reads every cue it can and lists the rest.
 */
final readonly class SrtReader implements TrackReader
{
    public function __construct(private Limits $limits = new Limits())
    {
    }

    public function read(string $input): Track
    {
        return new SrtParser($input, $this->limits, new IssueLog(true, $this->limits))->parse();
    }

    public function inspect(string $input): Inspection
    {
        $log = new IssueLog(false, $this->limits);
        $track = new SrtParser($input, $this->limits, $log)->parse();

        return new Inspection($track, $log->issues());
    }
}
