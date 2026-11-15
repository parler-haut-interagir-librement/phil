<?php

declare(strict_types=1);

namespace Phil\TimedText\WebVtt;

use Phil\TimedText\Contract\TrackReader;
use Phil\TimedText\Inspection;
use Phil\TimedText\Internal\IssueLog;
use Phil\TimedText\Internal\WebVttParser;
use Phil\TimedText\Limits;
use Phil\TimedText\StylePolicy;
use Phil\TimedText\Track;

/**
 * Reads WebVTT: the header, the regions, the `STYLE` blocks kept as text or dropped, the `NOTE` comments, and each cue
 * with its identifier, timings, settings and text as written. The byte order mark is removed and invalid UTF-8
 * replaced.
 *
 * `read()` refuses a file at its first problem — a block it cannot read, an unknown setting, a cue out of order;
 * `inspect()` reads what the WebVTT parser of the specification would, and lists the rest.
 */
final readonly class WebVttReader implements TrackReader
{
    public function __construct(
        private Limits $limits = new Limits(),
        private StylePolicy $styles = StylePolicy::Keep,
    ) {
    }

    public function read(string $input): Track
    {
        return new WebVttParser($input, $this->limits, $this->styles, new IssueLog(true, $this->limits))->parse();
    }

    public function inspect(string $input): Inspection
    {
        $log = new IssueLog(false, $this->limits);
        $track = new WebVttParser($input, $this->limits, $this->styles, $log)->parse();

        return new Inspection($track, $log->issues());
    }
}
