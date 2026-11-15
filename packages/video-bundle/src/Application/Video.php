<?php

declare(strict_types=1);

namespace Phil\VideoBundle\Application;

use Phil\TimedText\ChapterList;
use Phil\TimedText\Track;
use Phil\Url\Url;
use Phil\VideoBundle\Application\Exception\InvalidVideo;

/**
 * What every video shows: its identifier, its title, its size, the image shown before it plays, its transcript and its
 * chapters, the last two in the model of phil/timed-text.
 */
abstract readonly class Video
{
    /**
     * @param string      $id                 letters, digits, `_` and `-`, 64 at most: the name of its viewings
     * @param int         $width              in pixels, 1 or more
     * @param int         $height             in pixels, 1 or more
     * @param string|null $transcriptLanguage the BCP 47 tag of the transcript
     *
     * @throws InvalidVideo when a value is outside these
     */
    public function __construct(
        public string $id,
        public string $title,
        public int $width = 1280,
        public int $height = 720,
        public ?Url $poster = null,
        public ?Track $transcript = null,
        public ?string $transcriptLanguage = null,
        public ?ChapterList $chapters = null,
    ) {
        if (1 !== preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $id)) {
            throw new InvalidVideo('The identifier of a video holds letters, digits, "_" and "-", 64 at most.');
        }

        if ('' === trim($title)) {
            throw new InvalidVideo('A video has a title.');
        }

        if ($width < 1 || $height < 1) {
            throw new InvalidVideo('A video is at least one pixel wide and high.');
        }

        if (null !== $transcriptLanguage && 1 !== preg_match('/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/D', $transcriptLanguage)) {
            throw new InvalidVideo(\sprintf('"%s" is no language tag.', $transcriptLanguage));
        }
    }
}
