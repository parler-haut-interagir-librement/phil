<?php

declare(strict_types=1);

namespace Phil\TimedText\Internal;

use Phil\TimedText\Cue;
use Phil\TimedText\CueSettings;
use Phil\TimedText\Exception\InvalidTimedText;
use Phil\TimedText\Exception\LimitExceeded;
use Phil\TimedText\Limits;
use Phil\TimedText\Note;
use Phil\TimedText\Region;
use Phil\TimedText\StylePolicy;
use Phil\TimedText\Timecode;
use Phil\TimedText\Track;

/**
 * One reading of a WebVTT file, block by block, as the WebVTT parser of the specification reads it: a block it cannot
 * read is reported and stepped over. Built for one reading only. Not part of the public API.
 *
 * @internal
 */
final class WebVttParser
{
    /**
     * @var list<string>
     */
    private readonly array $lines;

    private readonly int $count;

    private int $index = 1;

    /**
     * @var list<Cue>
     */
    private array $cues = [];

    /**
     * @var list<Region>
     */
    private array $regions = [];

    /**
     * @var list<string>
     */
    private array $styles = [];

    /**
     * @var list<Note>
     */
    private array $notes = [];

    /**
     * @var list<string>
     */
    private array $pendingNotes = [];

    /**
     * @throws LimitExceeded when the file is larger than the limits allow
     */
    public function __construct(
        string $input,
        private readonly Limits $limits,
        private readonly StylePolicy $styling,
        private readonly IssueLog $log,
    ) {
        $this->lines = Input::lines($input, $limits);
        $this->count = \count($this->lines);
    }

    /**
     * @throws InvalidTimedText when the file does not open with `WEBVTT`, or at a problem of a strict reading
     * @throws LimitExceeded    when the file is larger than the limits allow
     */
    public function parse(): Track
    {
        $header = $this->header();

        while ($this->index < $this->count) {
            $line = $this->lines[$this->index];

            if ('' === $line) {
                ++$this->index;
            } elseif (1 === preg_match('/^NOTE(?:[ \t]|$)/D', $line)) {
                $this->note();
            } elseif (1 === preg_match('/^(STYLE|REGION)[ \t]*$/D', $line, $match)) {
                $this->definition('STYLE' === $match[1]);
            } else {
                $this->cue();
            }
        }

        foreach ($this->pendingNotes as $text) {
            $this->notes[] = new Note($text);
        }

        return new Track($this->cues, $this->regions, $this->styles, $this->notes, $header);
    }

    private function header(): string
    {
        if (1 !== preg_match('/^WEBVTT(?:[ \t](.*))?$/D', $this->lines[0], $match)) {
            throw InvalidTimedText::at(1, 'A WebVTT file opens with "WEBVTT".');
        }

        $header = trim($match[1] ?? '');

        if (str_contains($header, '-->')) {
            $this->log->report(1, 'The header holds "-->"; it is dropped.');
            $header = '';
        }

        // The lines under the first, up to an empty line, are metadata no cue uses: they are skipped.
        while ($this->index < $this->count && '' !== $this->lines[$this->index] && !str_contains($this->lines[$this->index], '-->')) {
            ++$this->index;
        }

        return $header;
    }

    /**
     * The index of the first line from `$from` that ends the block: an empty line, or a line with `-->`.
     */
    private function blockEnd(int $from): int
    {
        while ($from < $this->count && '' !== $this->lines[$from] && !str_contains($this->lines[$from], '-->')) {
            ++$from;
        }

        return $from;
    }

    /**
     * @return list<string>
     */
    private function body(int $from, int $to): array
    {
        return \array_slice($this->lines, $from, $to - $from);
    }

    private function note(): void
    {
        $first = $this->index;
        $end = $this->blockEnd($first + 1);
        $lines = $this->body($first + 1, $end);
        $opening = ltrim(substr($this->lines[$first], 4), " \t");

        if ('' !== $opening) {
            array_unshift($lines, $opening);
        }

        if ($end < $this->count && '' !== $this->lines[$end]) {
            $this->log->report($end + 1, 'A note ends at a line with "-->", which starts the next block.');
        }

        $this->pendingNotes[] = Input::checkText(implode("\n", $lines), $first + 1, $this->limits);
        $this->index = $end;
    }

    private function definition(bool $style): void
    {
        $first = $this->index;
        $end = $this->blockEnd($first + 1);
        $this->index = $end;
        $lines = $this->body($first + 1, $end);

        if ([] !== $this->cues) {
            $this->log->report($first + 1, ($style ? 'A STYLE' : 'A REGION').' block after the first cue is ignored.');

            return;
        }

        if ($end < $this->count && '' !== $this->lines[$end]) {
            $this->log->report($first + 1, ($style ? 'A STYLE' : 'A REGION').' block holds "-->"; it is ignored.');

            return;
        }

        if ($style) {
            $this->style($lines, $first + 1);
        } else {
            $this->region($lines, $first + 1);
        }
    }

    /**
     * @param list<string> $lines
     */
    private function style(array $lines, int $line): void
    {
        $css = Input::checkText(implode("\n", $lines), $line, $this->limits);

        if ('' !== $css && StylePolicy::Keep === $this->styling) {
            $this->styles[] = $css;
        }
    }

    /**
     * @param list<string> $lines
     */
    private function region(array $lines, int $line): void
    {
        [$region, $rejected] = Region::parse($lines);

        foreach ($rejected as $setting) {
            $this->log->report($line, \sprintf('"%s" is not a region setting; it is ignored.', mb_substr($setting, 0, 60)));
        }

        if (null === $region) {
            $this->log->report($line, 'A region without an identifier is ignored.');

            return;
        }

        foreach ($this->regions as $known) {
            if ($known->id === $region->id) {
                $this->log->report($line, \sprintf('The region "%s" is already defined; this one is ignored.', $region->id));

                return;
            }
        }

        $this->regions[] = $region;
    }

    private function cue(): void
    {
        $first = $this->index;
        $id = null;
        $timing = $first;

        if (!str_contains($this->lines[$first], '-->')) {
            $id = $this->lines[$first];
            $timing = $first + 1;

            if ($timing >= $this->count || !str_contains($this->lines[$timing], '-->')) {
                $this->log->report($first + 1, 'A block is neither a cue, a note, a style nor a region; it is ignored.');
                $this->index = $this->skip($timing);

                return;
            }
        }

        $end = $this->blockEnd($timing + 1);
        $this->index = $end;
        $cue = $this->timedCue($id, $timing, Input::checkText(implode("\n", $this->body($timing + 1, $end)), $timing + 2, $this->limits));

        if (!$cue instanceof Cue) {
            return;
        }

        if (\count($this->cues) >= $this->limits->maxCues) {
            throw LimitExceeded::count('cues', $this->limits->maxCues);
        }

        $previous = array_last($this->cues);

        if (null !== $previous && $cue->start < $previous->start) {
            $this->log->report($timing + 1, 'A cue starts before the cue above it.');
        }

        foreach ($this->pendingNotes as $text) {
            $this->notes[] = new Note($text, $cue->start);
        }

        $this->pendingNotes = [];
        $this->cues[] = $cue;
    }

    private function timedCue(?string $id, int $timing, string $text): ?Cue
    {
        $line = $timing + 1;

        if (1 !== preg_match('/^([\d:.]{1,20})[ \t]*-->[ \t]*([\d:.]{1,20})(?:[ \t]+(.*))?$/D', $this->lines[$timing], $match)) {
            $this->log->report($line, 'The timings of a cue are "start --> end", then its settings.');

            return null;
        }

        $start = Timecode::parseWebVtt($match[1]);
        $end = Timecode::parseWebVtt($match[2]);

        if (null === $start || null === $end) {
            $this->log->report($line, \sprintf('"%s" is not a WebVTT timestamp.', null === $start ? $match[1] : $match[2]));

            return null;
        }

        if ($end < $start) {
            $this->log->report($line, 'A cue ends before it starts.');

            return null;
        }

        [$settings, $rejected] = CueSettings::parse($match[3] ?? '');

        foreach ($rejected as $setting) {
            $this->log->report($line, \sprintf('"%s" is not a cue setting; it is ignored.', mb_substr($setting, 0, 60)));
        }

        return new Cue($start, $end, $text, $id, $settings);
    }

    /**
     * Steps over the lines of a block that is not read, up to its empty line.
     */
    private function skip(int $from): int
    {
        while ($from < $this->count && '' !== $this->lines[$from]) {
            ++$from;
        }

        return $from;
    }
}
