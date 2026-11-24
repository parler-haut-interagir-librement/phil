<?php

declare(strict_types=1);

namespace Phil\FeedBundle\Domain\ValueObject;

use Phil\FeedBundle\Domain\Exception\InvalidFeed;
use Phil\Url\Url;

/**
 * A file an entry carries, as the episode of a podcast: its address, its media type, and perhaps its size in bytes,
 * its title and its duration in seconds.
 *
 * Atom writes it as a `rel="enclosure"` link, RSS as its `<enclosure>` — with a length of 0 when the size is unknown,
 * as RSS asks for one —, JSON Feed as an attachment.
 */
final readonly class Attachment
{
    public const int MAX_TITLE_LENGTH = 255;

    private const string MEDIA_TYPE = '~^[a-z0-9][a-z0-9!#$&^_.+-]{0,126}/[a-z0-9][a-z0-9!#$&^_.+-]{0,126}$~D';

    public string $mediaType;

    public ?string $title;

    /**
     * @throws InvalidFeed when the media type is not `type/subtype`, the size or the duration is negative, or the
     *                     title is empty, longer than 255 characters or holds a control character
     */
    public function __construct(
        public Url $url,
        string $mediaType,
        public ?int $sizeInBytes = null,
        ?string $title = null,
        public ?int $durationInSeconds = null,
    ) {
        $type = strtolower($mediaType);

        if (1 !== preg_match(self::MEDIA_TYPE, $type)) {
            throw InvalidFeed::because('attachment media type', 'a media type such as audio/mpeg, without parameters', $mediaType);
        }

        if (null !== $sizeInBytes && $sizeInBytes < 0) {
            throw InvalidFeed::because('attachment size', '0 bytes or more', (string) $sizeInBytes);
        }

        if (null !== $durationInSeconds && $durationInSeconds < 0) {
            throw InvalidFeed::because('attachment duration', '0 seconds or more', (string) $durationInSeconds);
        }

        $this->mediaType = $type;
        $this->title = null === $title ? null : Text::line('attachment title', $title, self::MAX_TITLE_LENGTH);
    }
}
