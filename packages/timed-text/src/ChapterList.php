<?php

declare(strict_types=1);

namespace Phil\TimedText;

/**
 * The chapters of one episode or video in their order, and what a Podcasting 2.0 chapters file says of itself: its
 * version, title, author, podcast name, description, the media file it belongs to, and whether its places form a
 * route.
 */
final readonly class ChapterList
{
    public const string VERSION = '1.2.0';

    /**
     * @param list<Chapter> $chapters
     */
    public function __construct(
        public array $chapters = [],
        public string $version = self::VERSION,
        public ?string $title = null,
        public ?string $author = null,
        public ?string $podcastName = null,
        public ?string $description = null,
        public ?string $fileName = null,
        public bool $waypoints = false,
    ) {
    }

    public function count(): int
    {
        return \count($this->chapters);
    }

    /**
     * The chapter under way at an instant: the last that started by then and has not ended; null before the first.
     */
    public function at(int $milliseconds): ?Chapter
    {
        $current = null;

        foreach ($this->chapters as $chapter) {
            if ($chapter->start <= $milliseconds && (null === $chapter->end || $chapter->end > $milliseconds)) {
                $current = $chapter;
            }
        }

        return $current;
    }

    /**
     * The chapters a table of contents lists: those whose `toc` is true.
     *
     * @return list<Chapter>
     */
    public function tableOfContents(): array
    {
        return array_values(array_filter($this->chapters, static fn (Chapter $chapter): bool => $chapter->toc));
    }

    /**
     * Each chapter with an end: its own, else the start of the next, else the duration given; null for the last when
     * it has none and no duration is given.
     *
     * @return list<array{Chapter, ?int}>
     */
    public function withEnds(?int $duration = null): array
    {
        $ends = [];

        foreach ($this->chapters as $index => $chapter) {
            $next = $this->chapters[$index + 1] ?? null;
            $ends[] = [$chapter, $chapter->end ?? ($next->start ?? $duration)];
        }

        return $ends;
    }
}
