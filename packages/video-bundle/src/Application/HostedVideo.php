<?php

declare(strict_types=1);

namespace Phil\VideoBundle\Application;

use Phil\TimedText\ChapterList;
use Phil\TimedText\Track;
use Phil\Url\Url;
use Phil\VideoBundle\Application\Exception\InvalidVideo;

/**
 * A video the site serves, or a service it connects serves for it: its files, played by the browser's video element,
 * an HLS stream the player switches to where the browser plays HLS, and its WebVTT text tracks. Nothing transcodes it
 * here: the files come ready, from the site's storage or from the service.
 */
final readonly class HostedVideo extends Video
{
    /**
     * @param list<VideoSource> $sources in the order the browser tries them
     * @param list<TextTrack>   $tracks
     *
     * @throws InvalidVideo when there is no source
     */
    public function __construct(
        string $id,
        string $title,
        public array $sources,
        int $width = 1280,
        int $height = 720,
        ?Url $poster = null,
        public ?Url $hls = null,
        public array $tracks = [],
        ?Track $transcript = null,
        ?string $transcriptLanguage = null,
        ?ChapterList $chapters = null,
    ) {
        if ([] === $sources) {
            throw new InvalidVideo('A hosted video has a file, which a browser without HLS plays.');
        }

        parent::__construct($id, $title, $width, $height, $poster, $transcript, $transcriptLanguage, $chapters);
    }
}
