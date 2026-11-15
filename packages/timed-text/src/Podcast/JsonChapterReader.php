<?php

declare(strict_types=1);

namespace Phil\TimedText\Podcast;

use Phil\TimedText\Chapter;
use Phil\TimedText\ChapterList;
use Phil\TimedText\ChapterLocation;
use Phil\TimedText\Contract\ChapterReader;
use Phil\TimedText\Exception\InvalidTimedText;
use Phil\TimedText\Internal\Json;
use Phil\TimedText\Limits;
use Phil\TimedText\Timecode;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;

/**
 * Reads Podcasting 2.0 JSON chapters, `application/json+chapters`: the `version` and the fields that describe the
 * file, and each chapter's `startTime`, `endTime`, `title`, `url`, `img`, `toc` and `location`. Links and images are
 * `http` or `https` addresses, or the file is refused; the chapters are in the order of their starts. A problem names
 * the chapter, counted from 1. Fields the specification does not name are not kept.
 */
final readonly class JsonChapterReader implements ChapterReader
{
    public function __construct(private Limits $limits = new Limits())
    {
    }

    public function read(string $input): ChapterList
    {
        [$data, $items] = Json::document($input, 'chapters', $this->limits);
        $chapters = [];

        foreach ($items as $index => $item) {
            $chapter = $this->chapter($item, \sprintf('Chapter %d: ', $index + 1));
            $previous = array_last($chapters);

            if (null !== $previous && $chapter->start < $previous->start) {
                throw InvalidTimedText::at(null, \sprintf('Chapter %d: it starts before the chapter above it.', $index + 1));
            }

            $chapters[] = $chapter;
        }

        $version = $this->string($data, 'version', '');

        if (null === $version) {
            throw InvalidTimedText::at(null, 'The chapters name their "version".');
        }

        $waypoints = $data['waypoints'] ?? false;

        if (!\is_bool($waypoints)) {
            throw InvalidTimedText::at(null, '"waypoints" is true or false.');
        }

        return new ChapterList(
            $chapters,
            $version,
            $this->string($data, 'title', ''),
            $this->string($data, 'author', ''),
            $this->string($data, 'podcastName', ''),
            $this->string($data, 'description', ''),
            $this->string($data, 'fileName', ''),
            $waypoints,
        );
    }

    private function chapter(mixed $item, string $where): Chapter
    {
        if (!\is_array($item)) {
            throw InvalidTimedText::at(null, $where.'a chapter is an object.');
        }

        $start = $this->seconds($item['startTime'] ?? null);

        if (null === $start) {
            throw InvalidTimedText::at(null, $where.'"startTime" is a number of seconds from 0.');
        }

        $end = null;

        if (null !== ($item['endTime'] ?? null)) {
            $end = $this->seconds($item['endTime']);

            if (null === $end || $end < $start) {
                throw InvalidTimedText::at(null, $where.'"endTime" is a number of seconds from its start.');
            }
        }

        $toc = $item['toc'] ?? true;

        if (!\is_bool($toc)) {
            throw InvalidTimedText::at(null, $where.'"toc" is true or false.');
        }

        return new Chapter(
            $start,
            $this->string($item, 'title', $where) ?? '',
            $end,
            $this->url($item, 'url', $where),
            $this->url($item, 'img', $where),
            $toc,
            $this->location($item['location'] ?? null, $where),
        );
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function string(array $data, string $key, string $where): ?string
    {
        $value = Json::string($data, $key, $this->limits);

        if (false === $value) {
            throw InvalidTimedText::at(null, \sprintf('%s"%s" is a string.', $where, $key));
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function url(array $data, string $key, string $where): ?Url
    {
        $value = $this->string($data, $key, $where);

        try {
            return null === $value ? null : Url::fromString($value);
        } catch (InvalidUrl $invalidUrl) {
            throw InvalidTimedText::at(null, \sprintf('%s"%s" is an http or https address.', $where, $key), $invalidUrl);
        }
    }

    private function location(mixed $location, string $where): ?ChapterLocation
    {
        if (null === $location) {
            return null;
        }

        if (!\is_array($location)) {
            throw InvalidTimedText::at(null, $where.'"location" is an object.');
        }

        $name = $this->string($location, 'name', $where.'location ');
        $geo = $this->string($location, 'geo', $where.'location ');

        if (null === $name || null === $geo || !str_starts_with($geo, 'geo:')) {
            throw InvalidTimedText::at(null, $where.'a location has a "name" and a "geo" URI.');
        }

        return new ChapterLocation($name, $geo, $this->string($location, 'osm', $where.'location '));
    }

    private function seconds(mixed $value): ?int
    {
        return \is_int($value) || \is_float($value) ? Timecode::fromSeconds($value) : null;
    }
}
