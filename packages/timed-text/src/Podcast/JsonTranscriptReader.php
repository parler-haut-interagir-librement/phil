<?php

declare(strict_types=1);

namespace Phil\TimedText\Podcast;

use Phil\TimedText\Contract\TrackReader;
use Phil\TimedText\Cue;
use Phil\TimedText\CueText;
use Phil\TimedText\Inspection;
use Phil\TimedText\Internal\IssueLog;
use Phil\TimedText\Internal\Json;
use Phil\TimedText\Limits;
use Phil\TimedText\Timecode;
use Phil\TimedText\Track;

/**
 * Reads a Podcasting 2.0 JSON transcript, `application/json`: its `segments`, each with a `startTime` and an
 * `endTime` in seconds, a `body` and a `speaker`. Each segment becomes a cue whose text is the body escaped, opened
 * by the voice of the speaker. JSON has no lines: a problem names the segment, counted from 1.
 */
final readonly class JsonTranscriptReader implements TrackReader
{
    public function __construct(private Limits $limits = new Limits())
    {
    }

    public function read(string $input): Track
    {
        return $this->parse($input, new IssueLog(true, $this->limits));
    }

    public function inspect(string $input): Inspection
    {
        $log = new IssueLog(false, $this->limits);
        $track = $this->parse($input, $log);

        return new Inspection($track, $log->issues());
    }

    private function parse(string $input, IssueLog $log): Track
    {
        [, $segments] = Json::document($input, 'segments', $this->limits);
        $cues = [];

        foreach ($segments as $index => $segment) {
            $cue = $this->segment($segment, \sprintf('Segment %d: ', $index + 1), $log);

            if (!$cue instanceof Cue) {
                continue;
            }

            $previous = array_last($cues);

            if (null !== $previous && $cue->start < $previous->start) {
                $log->report(null, \sprintf('Segment %d: it starts before the segment above it.', $index + 1));
            }

            $cues[] = $cue;
        }

        return new Track($cues);
    }

    private function segment(mixed $segment, string $where, IssueLog $log): ?Cue
    {
        if (!\is_array($segment)) {
            return $this->refuse($log, $where.'a segment is an object.');
        }

        $start = $this->seconds($segment['startTime'] ?? null);

        if (null === $start) {
            return $this->refuse($log, $where.'"startTime" is a number of seconds from 0.');
        }

        $end = $this->seconds($segment['endTime'] ?? null);

        if (null === $end) {
            return $this->refuse($log, $where.'"endTime" is a number of seconds from 0.');
        }

        if ($end < $start) {
            return $this->refuse($log, $where.'it ends before it starts.');
        }

        $body = Json::string($segment, 'body', $this->limits);

        if (!\is_string($body)) {
            return $this->refuse($log, $where.'"body" is a string.');
        }

        $speaker = Json::string($segment, 'speaker', $this->limits);

        if (false === $speaker) {
            return $this->refuse($log, $where.'"speaker" is a string.');
        }

        return new Cue($start, $end, CueText::fromPlain($body, $speaker));
    }

    private function refuse(IssueLog $log, string $message): null
    {
        $log->report(null, $message);

        return null;
    }

    private function seconds(mixed $value): ?int
    {
        return \is_int($value) || \is_float($value) ? Timecode::fromSeconds($value) : null;
    }
}
