<?php

declare(strict_types=1);

namespace Phil\VideoBundle\Application;

use Phil\Url\Url;
use Phil\VideoBundle\Application\Exception\InvalidVideo;

/**
 * One file of a hosted video: its address and its media type, `video/mp4` or `video/webm`.
 */
final readonly class VideoSource
{
    /**
     * @throws InvalidVideo when the type is no video type
     */
    public function __construct(
        public Url $url,
        public string $type = 'video/mp4',
    ) {
        if (1 !== preg_match('#^video/[a-z0-9.+-]{1,64}$#D', $type)) {
            throw new InvalidVideo(\sprintf('"%s" is no video type.', $type));
        }
    }
}
