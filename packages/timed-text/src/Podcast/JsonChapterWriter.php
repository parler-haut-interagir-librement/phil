<?php

declare(strict_types=1);

namespace Phil\TimedText\Podcast;

use Phil\TimedText\ChapterList;
use Phil\TimedText\Contract\ChapterWriter;
use Phil\TimedText\Internal\Json;
use Phil\TimedText\Timecode;

/**
 * Writes Podcasting 2.0 JSON chapters: the version and the fields of the file that are set, then each chapter with
 * its `startTime` and only the fields it has — `toc` written when it is false, the default being true.
 */
final readonly class JsonChapterWriter implements ChapterWriter
{
    public function write(ChapterList $chapters): string
    {
        $items = [];

        foreach ($chapters->chapters as $chapter) {
            $item = ['startTime' => Timecode::toSeconds($chapter->start)];

            if ('' !== $chapter->title) {
                $item['title'] = $chapter->title;
            }

            if (null !== $chapter->end) {
                $item['endTime'] = Timecode::toSeconds($chapter->end);
            }

            if (null !== $chapter->url) {
                $item['url'] = $chapter->url->toString();
            }

            if (null !== $chapter->image) {
                $item['img'] = $chapter->image->toString();
            }

            if (!$chapter->toc) {
                $item['toc'] = false;
            }

            if (null !== $chapter->location) {
                $item['location'] = array_filter(
                    ['name' => $chapter->location->name, 'geo' => $chapter->location->geo, 'osm' => $chapter->location->osm],
                    static fn (?string $value): bool => null !== $value,
                );
            }

            $items[] = $item;
        }

        $file = array_filter([
            'version' => $chapters->version,
            'title' => $chapters->title,
            'author' => $chapters->author,
            'podcastName' => $chapters->podcastName,
            'description' => $chapters->description,
            'fileName' => $chapters->fileName,
        ], static fn (?string $value): bool => null !== $value);

        if ($chapters->waypoints) {
            $file['waypoints'] = true;
        }

        return Json::encode($file + ['chapters' => $items]);
    }
}
