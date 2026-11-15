<?php

declare(strict_types=1);

namespace Phil\TimedText\Internal;

use Phil\TimedText\Cue;
use Phil\TimedText\CueText;
use Phil\TimedText\Exception\InvalidTimedText;
use Phil\TimedText\Exception\LimitExceeded;
use Phil\TimedText\Limits;
use Phil\TimedText\Timecode;
use Phil\TimedText\Track;

/**
 * One reading of an SRT file, block by block: a number, the timings, the text up to an empty line or to the number
 * and timings of the next cue. Built for one reading only. Not part of the public API.
 *
 * @internal
 */
final class SrtParser
{
    private const string TIMING = '/^\s*([\d:.,]{1,20})\s*-->\s*([\d:.,]{1,20})(?:\s.*)?$/D';

    /**
     * @var list<string>
     */
    private readonly array $lines;

    private readonly int $count;

    /**
     * @var list<Cue>
     */
    private array $cues = [];

    /**
     * @throws LimitExceeded when the file is larger than the limits allow
     */
    public function __construct(
        string $input,
        private readonly Limits $limits,
        private readonly IssueLog $log,
    ) {
        $this->lines = Input::lines($input, $limits);
        $this->count = \count($this->lines);
    }

    /**
     * @throws InvalidTimedText at a problem of a strict reading
     * @throws LimitExceeded    when the file is larger than the limits allow
     */
    public function parse(): Track
    {
        $index = 0;

        while ($index < $this->count) {
            $index = '' === trim($this->lines[$index]) ? $index + 1 : $this->block($index);
        }

        return new Track($this->cues);
    }

    private function block(int $first): int
    {
        $timing = $first;

        if ($this->isNumber($first)) {
            ++$timing;
        } elseif (str_contains($this->lines[$first], '-->')) {
            $this->log->report($first + 1, 'A cue opens with its number.');
        } else {
            $this->log->report($first + 1, 'A block without timings is ignored.');

            return $this->skip($first);
        }

        if ($timing >= $this->count || 1 !== preg_match(self::TIMING, $this->lines[$timing], $match)) {
            $this->log->report($timing + 1, 'The timings of a cue are "start --> end".');

            return $this->skip($timing);
        }

        $end = $timing + 1;

        while ($end < $this->count && '' !== trim($this->lines[$end]) && !$this->isTiming($end) && (!$this->isNumber($end) || !$this->isTiming($end + 1))) {
            ++$end;
        }

        $this->add($timing + 1, $match[1], $match[2], \array_slice($this->lines, $timing + 1, $end - $timing - 1));

        return $end;
    }

    /**
     * @param list<string> $text
     */
    private function add(int $line, string $from, string $to, array $text): void
    {
        $start = Timecode::parseSrt($from);
        $end = Timecode::parseSrt($to);

        if (null === $start || null === $end) {
            $this->log->report($line, \sprintf('"%s" is not an SRT timestamp.', null === $start ? $from : $to));

            return;
        }

        if ($end < $start) {
            $this->log->report($line, 'A cue ends before it starts.');

            return;
        }

        if (\count($this->cues) >= $this->limits->maxCues) {
            throw LimitExceeded::count('cues', $this->limits->maxCues);
        }

        $previous = array_last($this->cues);

        if (null !== $previous && $start < $previous->start) {
            $this->log->report($line, 'A cue starts before the cue above it.');
        }

        $this->cues[] = new Cue($start, $end, CueText::fromSrt(Input::checkText(implode("\n", $text), $line + 1, $this->limits)));
    }

    private function isNumber(int $index): bool
    {
        return $index < $this->count && 1 === preg_match('/^\s*\d{1,9}\s*$/D', $this->lines[$index]);
    }

    private function isTiming(int $index): bool
    {
        return $index < $this->count && 1 === preg_match(self::TIMING, $this->lines[$index]);
    }

    private function skip(int $from): int
    {
        while ($from < $this->count && '' !== trim($this->lines[$from])) {
            ++$from;
        }

        return $from;
    }
}
