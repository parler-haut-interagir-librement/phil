<?php

declare(strict_types=1);

namespace Phil\TimedText;

use Phil\TimedText\Exception\InvalidCue;

/**
 * One timed text: its start and end in milliseconds, its text in WebVTT cue text — markup such as `<v Alice>`,
 * `<i>` and `&amp;` included —, an identifier and its WebVTT settings. Every format is read into this one model; the
 * text stays as written, so that a WebVTT file read and written again keeps its markup.
 *
 * The text carries what every format can write back: no carriage return, no empty line, no `-->`.
 */
final readonly class Cue
{
    /**
     * @throws InvalidCue when an instant is out of range, the end comes before the start, or the text or the identifier
     *                    holds what would break a WebVTT block
     */
    public function __construct(
        public int $start,
        public int $end,
        public string $text,
        public ?string $id = null,
        public CueSettings $settings = new CueSettings(),
    ) {
        Timecode::check($start);
        Timecode::check($end);

        if ($end < $start) {
            throw new InvalidCue(\sprintf('A cue ends at %d ms, before its start at %d ms.', $end, $start));
        }

        if (!self::isWritableText($text)) {
            throw new InvalidCue('The text of a cue holds no carriage return, no empty line and no "-->"; build it with CueText.');
        }

        if (null !== $id && ('' === $id || 1 === preg_match('/[\r\n]/', $id) || str_contains($id, '-->') || 1 === preg_match('/^(?:NOTE(?:[ \t]|$)|STYLE[ \t]*$|REGION[ \t]*$)/D', $id))) {
            throw new InvalidCue('The identifier of a cue is one line, not empty, without "-->", and does not open as a NOTE, STYLE or REGION block.');
        }
    }

    /**
     * Whether a text can be the text of a cue as it is.
     */
    public static function isWritableText(string $text): bool
    {
        return !str_contains($text, "\r") && !str_contains($text, '-->')
            && ('' === $text || !\in_array('', explode("\n", $text), true));
    }

    public function duration(): int
    {
        return $this->end - $this->start;
    }

    /**
     * Whether the cue is shown at some instant of `[$from, $to)`, or at `$from` when both are equal.
     */
    public function overlaps(int $from, int $to): bool
    {
        return $from === $to
            ? $this->start === $from || ($this->start < $from && $from < $this->end)
            : $this->start < $to && $this->end > $from;
    }

    /**
     * The text without its markup, its character references decoded.
     */
    public function plainText(): string
    {
        return CueText::plain($this->text);
    }

    /**
     * The voice that opens the text, `Alice` for `<v Alice>Hello`; null without one.
     */
    public function speaker(): ?string
    {
        return CueText::speaker($this->text);
    }

    public function withText(string $text): self
    {
        return new self($this->start, $this->end, $text, $this->id, $this->settings);
    }

    public function withTimes(int $start, int $end): self
    {
        return new self($start, $end, $this->text, $this->id, $this->settings);
    }

    /**
     * The cue moved by a number of milliseconds, earlier when negative.
     *
     * @throws InvalidCue when it would start before 0 or end past `Timecode::MAX`
     */
    public function shifted(int $milliseconds): self
    {
        return $this->withTimes($this->start + $milliseconds, $this->end + $milliseconds);
    }

    /**
     * The cue cut in two at an instant strictly inside it: both halves keep the text and the settings; the
     * identifier stays on the first.
     *
     * @return array{self, self}
     *
     * @throws InvalidCue when the instant is not strictly between the start and the end
     */
    public function splitAt(int $at): array
    {
        if ($at <= $this->start || $at >= $this->end) {
            throw new InvalidCue(\sprintf('%d ms is not inside the cue from %d to %d ms.', $at, $this->start, $this->end));
        }

        return [
            new self($this->start, $at, $this->text, $this->id, $this->settings),
            new self($at, $this->end, $this->text, null, $this->settings),
        ];
    }

    /**
     * One cue from this one and the next: from the earlier start to the later end, the texts joined by a line break
     * or the separator given; the identifier and settings of this one kept.
     */
    public function mergedWith(self $next, string $separator = "\n"): self
    {
        return new self(
            min($this->start, $next->start),
            max($this->end, $next->end),
            '' === $next->text ? $this->text : ('' === $this->text ? $next->text : $this->text.$separator.$next->text),
            $this->id,
            $this->settings,
        );
    }
}
