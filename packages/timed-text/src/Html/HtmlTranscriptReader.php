<?php

declare(strict_types=1);

namespace Phil\TimedText\Html;

use Dom\Element;
use Dom\HTMLDocument;
use Phil\TimedText\Contract\TrackReader;
use Phil\TimedText\Cue;
use Phil\TimedText\CueText;
use Phil\TimedText\Exception\LimitExceeded;
use Phil\TimedText\Inspection;
use Phil\TimedText\Internal\Input;
use Phil\TimedText\Internal\IssueLog;
use Phil\TimedText\Limits;
use Phil\TimedText\Timecode;
use Phil\TimedText\Track;

/**
 * Reads an HTML transcript of the shape Podcasting 2.0 describes, with the HTML parser of PHP: each `<p>` becomes a
 * cue that starts at the `<time>` above it — its `datetime` duration, else its text as a clock — and ends where the
 * next later paragraph starts, the last ending where it starts; the `<cite>` above it gives the speaker. Nothing of
 * the markup is kept but the line breaks. A problem names the paragraph or time, counted from 1.
 */
final readonly class HtmlTranscriptReader implements TrackReader
{
    private const string BREAK = "\u{E000}";

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
        $document = HTMLDocument::createFromString(
            '<!DOCTYPE html><html><body>'.str_replace(self::BREAK, '', Input::text($input, $this->limits)),
            \LIBXML_NOERROR,
            'UTF-8',
        );

        foreach ($document->querySelectorAll('br') as $break) {
            $break->replaceWith(self::BREAK);
        }

        $entries = $this->entries($document, $log);
        $cues = [];
        $nextStart = null;
        $nextEnd = 0;

        // From the last paragraph up: one ends where the next later start is, which the paragraphs of the same start
        // below it share.
        foreach (array_reverse($entries) as [$start, $text]) {
            $end = null === $nextStart ? $start : ($nextStart > $start ? $nextStart : $nextEnd);
            $cues[] = new Cue($start, $end, $text);
            $nextStart = $start;
            $nextEnd = $end;
        }

        return new Track(array_reverse($cues));
    }

    /**
     * @return list<array{int, string}>
     */
    private function entries(HTMLDocument $document, IssueLog $log): array
    {
        $entries = [];
        $speaker = null;
        $start = 0;
        $times = 0;

        foreach ($document->querySelectorAll('cite, time, p') as $element) {
            if ('CITE' === $element->tagName) {
                $speaker = rtrim($this->text($element), ': ');
            } elseif ('TIME' === $element->tagName) {
                ++$times;
                $time = $this->time($element);

                if (null === $time) {
                    $log->report(null, \sprintf('Time %d: it is neither an ISO 8601 duration nor a clock.', $times));
                } elseif ($time < $start) {
                    $log->report(null, \sprintf('Time %d: it comes before the time above it.', $times));
                } else {
                    $start = $time;
                }
            } else {
                $text = Input::checkText($this->text($element), null, $this->limits);

                if ('' !== $text) {
                    if (\count($entries) >= $this->limits->maxCues) {
                        throw LimitExceeded::count('paragraphs', $this->limits->maxCues);
                    }

                    $entries[] = [$start, CueText::fromPlain($text, $speaker)];
                }
            }
        }

        return $entries;
    }

    private function time(Element $element): ?int
    {
        $datetime = $element->getAttribute('datetime');

        return null === $datetime ? Timecode::parseClock($this->text($element)) : Timecode::parseDuration(trim($datetime));
    }

    /**
     * The text of an element, its white space collapsed as a browser shows it, its line breaks kept.
     */
    private function text(Element $element): string
    {
        $lines = explode(self::BREAK, (string) preg_replace('/\s+/u', ' ', (string) $element->textContent));

        return implode("\n", array_filter(array_map(trim(...), $lines), static fn (string $line): bool => '' !== $line));
    }
}
