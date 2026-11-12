<?php

declare(strict_types=1);

namespace Phil\SocialCards;

use Phil\SocialCards\Exception\InvalidShareMetadata;

/**
 * The image a link is shown with: its absolute address, the text that replaces it for those who cannot see it, its size
 * in pixels and its media type, each optional but the address.
 */
final readonly class ShareImage
{
    /** The longest replacement text X accepts; a longer one is cut. */
    public const int MAX_ALT = 420;

    public const int MAX_SIDE = 10_000;

    public string $url;

    public ?string $alt;

    /** @var int<1, 10000>|null */
    public ?int $width;

    /** @var int<1, 10000>|null */
    public ?int $height;

    public function __construct(
        string $url,
        ?string $alt = null,
        ?int $width = null,
        ?int $height = null,
        public ?string $type = null,
    ) {
        $this->url = Text::absoluteUrl($url, 'image address');
        $this->alt = Text::line($alt, self::MAX_ALT);
        $this->width = $this->side('width', $width);
        $this->height = $this->side('height', $height);

        if (null !== $type && 1 !== preg_match('{^image/[a-z0-9][a-z0-9.+-]{0,63}$}D', $type)) {
            throw InvalidShareMetadata::because(\sprintf('"%s" is not the media type of an image.', Text::excerpt($type)));
        }
    }

    /**
     * @return int<1, 10000>|null
     */
    private function side(string $side, ?int $pixels): ?int
    {
        if (null !== $pixels && ($pixels < 1 || $pixels > self::MAX_SIDE)) {
            throw InvalidShareMetadata::because(\sprintf('The %s of an image is 1 to %d pixels; %d is not.', $side, self::MAX_SIDE, $pixels));
        }

        return $pixels;
    }
}
