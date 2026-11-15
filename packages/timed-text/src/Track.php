<?php

declare(strict_types=1);

namespace Phil\TimedText;

use Phil\TimedText\Exception\InvalidCue;

/**
 * The neutral model every format is read into and written from: the cues in their order, and what only WebVTT
 * carries — the text after `WEBVTT`, the regions, the `STYLE` blocks as text and the `NOTE` comments. Every change
 * returns a new track.
 */
final readonly class Track
{
    /**
     * @param list<Cue>    $cues
     * @param list<Region> $regions
     * @param list<string> $styles  the CSS of each `STYLE` block, never interpreted
     * @param list<Note>   $notes
     *
     * @throws InvalidCue when the header is not one line, or a style holds an empty line or `-->`
     */
    public function __construct(
        public array $cues = [],
        public array $regions = [],
        public array $styles = [],
        public array $notes = [],
        public string $header = '',
    ) {
        if (1 === preg_match('/[\r\n]/', $header) || str_contains($header, '-->')) {
            throw new InvalidCue('The header of a track is one line, without "-->".');
        }

        foreach ($styles as $style) {
            if ('' === $style || !Cue::isWritableText($style)) {
                throw new InvalidCue('A style is not empty, and holds no carriage return, no empty line and no "-->".');
            }
        }
    }

    public function count(): int
    {
        return \count($this->cues);
    }

    public function isEmpty(): bool
    {
        return [] === $this->cues;
    }

    /**
     * The latest end of its cues; 0 without any.
     */
    public function duration(): int
    {
        return [] === $this->cues ? 0 : max(array_map(static fn (Cue $cue): int => $cue->end, $this->cues));
    }

    /**
     * The cues shown at an instant.
     *
     * @return list<Cue>
     */
    public function at(int $milliseconds): array
    {
        return $this->between($milliseconds, $milliseconds);
    }

    /**
     * The cues shown at some instant of `[$from, $to)`.
     *
     * @return list<Cue>
     */
    public function between(int $from, int $to): array
    {
        return array_values(array_filter($this->cues, static fn (Cue $cue): bool => $cue->overlaps($from, $to)));
    }

    /**
     * @param list<Cue> $cues
     */
    public function withCues(array $cues): self
    {
        return new self($cues, $this->regions, $this->styles, $this->notes, $this->header);
    }

    public function withoutStyles(): self
    {
        return new self($this->cues, $this->regions, [], $this->notes, $this->header);
    }

    /**
     * The cues ordered by start, then by end; cues that tie keep their order.
     */
    public function sorted(): self
    {
        $cues = $this->cues;
        usort($cues, static fn (Cue $a, Cue $b): int => [$a->start, $a->end] <=> [$b->start, $b->end]);

        return $this->withCues($cues);
    }

    /**
     * Every cue and note moved by a number of milliseconds, earlier when negative. Moved earlier, a cue that would end
     * before 0, or at 0 after lasting, is dropped, and one that would start before 0 starts at 0.
     *
     * @throws InvalidCue when a cue would end past `Timecode::MAX`
     */
    public function shifted(int $milliseconds): self
    {
        $cues = [];

        foreach ($this->cues as $cue) {
            $end = $cue->end + $milliseconds;

            if ($end > 0 || (0 === $end && 0 === $cue->duration())) {
                $cues[] = $cue->withTimes(max(0, $cue->start + $milliseconds), $end);
            }
        }

        return new self(
            $cues,
            $this->regions,
            $this->styles,
            array_map(static fn (Note $note): Note => $note->shifted($milliseconds), $this->notes),
            $this->header,
        );
    }

    /**
     * This track and another as one, the cues ordered by start; the regions of the other whose identifier this one
     * does not use, its styles not already here, and its notes are added.
     */
    public function mergedWith(self $other): self
    {
        $ids = array_map(static fn (Region $region): string => $region->id, $this->regions);
        $regions = $this->regions;

        foreach ($other->regions as $region) {
            if (!\in_array($region->id, $ids, true)) {
                $regions[] = $region;
            }
        }

        return new self(
            [...$this->cues, ...$other->cues],
            $regions,
            array_values(array_unique([...$this->styles, ...$other->styles])),
            [...$this->notes, ...$other->notes],
            $this->header,
        )->sorted();
    }

    /**
     * The track cut in two at an instant: the cues that end by then, and those that start from then; a cue across the
     * instant is split between both. With `$rebase`, the second track starts at 0.
     *
     * @return array{self, self}
     *
     * @throws InvalidCue when the instant is out of range
     */
    public function splitAt(int $at, bool $rebase = false): array
    {
        Timecode::check($at);
        $before = [];
        $after = [];

        foreach ($this->cues as $cue) {
            if ($cue->end <= $at && ($cue->start !== $at || $cue->end !== $at)) {
                $before[] = $cue;
            } elseif ($cue->start >= $at) {
                $after[] = $cue;
            } else {
                [$before[], $after[]] = $cue->splitAt($at);
            }
        }

        $first = new self(
            $before,
            $this->regions,
            $this->styles,
            array_values(array_filter($this->notes, static fn (Note $note): bool => null !== $note->at && $note->at < $at)),
            $this->header,
        );
        $second = new self(
            $after,
            $this->regions,
            $this->styles,
            array_values(array_filter($this->notes, static fn (Note $note): bool => null === $note->at || $note->at >= $at)),
            $this->header,
        );

        return [$first, $rebase ? $second->shifted(-$at) : $second];
    }
}
