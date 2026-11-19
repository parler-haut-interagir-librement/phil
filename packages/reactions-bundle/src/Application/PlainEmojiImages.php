<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Application;

use Override;
use Phil\ReactionsBundle\Application\Contract\EmojiImages;

/**
 * Images declared by their address. A reference to a medium has no address without phil/media-bundle.
 */
final readonly class PlainEmojiImages implements EmojiImages
{
    #[Override]
    public function urls(string ...$images): array
    {
        $urls = [];

        foreach ($images as $image) {
            if (!str_starts_with($image, 'media:')) {
                $urls[$image] = $image;
            }
        }

        return $urls;
    }
}
