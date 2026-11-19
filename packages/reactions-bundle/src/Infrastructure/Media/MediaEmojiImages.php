<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Infrastructure\Media;

use Override;
use Phil\MediaBundle\Domain\Exception\InvalidMedia;
use Phil\MediaBundle\Domain\Repository\MediaRepository;
use Phil\MediaBundle\Domain\ValueObject\MediaId;
use Phil\MediaBundle\Infrastructure\Twig\MediaExtension;
use Phil\ReactionsBundle\Application\Contract\EmojiImages;

/**
 * Images declared as `media:` and the identifier of a medium of phil/media-bundle, all read in one query, or by their
 * address.
 */
final readonly class MediaEmojiImages implements EmojiImages
{
    private const string PREFIX = 'media:';

    public function __construct(
        private MediaRepository $media,
        private MediaExtension $urls,
    ) {
    }

    #[Override]
    public function urls(string ...$images): array
    {
        $urls = [];
        $wanted = [];

        foreach ($images as $image) {
            if (!str_starts_with($image, self::PREFIX)) {
                $urls[$image] = $image;

                continue;
            }

            try {
                $wanted[MediaId::fromString(substr($image, \strlen(self::PREFIX)))->value] = $image;
            } catch (InvalidMedia) {
                continue;
            }
        }

        if ([] === $wanted) {
            return $urls;
        }

        foreach ($this->media->getMany(...array_keys($wanted)) as $id => $medium) {
            $urls[$wanted[$id]] = $this->urls->url($medium);
        }

        return $urls;
    }
}
