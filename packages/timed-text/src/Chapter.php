<?php

declare(strict_types=1);

namespace Phil\TimedText;

use Phil\TimedText\Exception\InvalidCue;
use Phil\Url\Url;

/**
 * One chapter of an episode or a video: its start in milliseconds, its title as plain text, and what Podcasting 2.0
 * chapters add — an end, a link, an image, a place, and whether it shows in the table of contents. The link and the
 * image are `http` or `https` addresses read by phil/url: a `javascript:` link never reaches a player.
 */
final readonly class Chapter
{
    /**
     * @throws InvalidCue when an instant is out of range or the end comes before the start
     */
    public function __construct(
        public int $start,
        public string $title,
        public ?int $end = null,
        public ?Url $url = null,
        public ?Url $image = null,
        public bool $toc = true,
        public ?ChapterLocation $location = null,
    ) {
        Timecode::check($start);

        if (null !== $end) {
            Timecode::check($end);

            if ($end < $start) {
                throw new InvalidCue(\sprintf('A chapter ends at %d ms, before its start at %d ms.', $end, $start));
            }
        }
    }

    public function shifted(int $milliseconds): self
    {
        return new self(
            $this->start + $milliseconds,
            $this->title,
            null === $this->end ? null : $this->end + $milliseconds,
            $this->url,
            $this->image,
            $this->toc,
            $this->location,
        );
    }
}
