<?php

declare(strict_types=1);

namespace Phil\PwaBundle\Domain\ValueObject;

use Phil\PwaBundle\Domain\Exception\InvalidManifest;
use Phil\PwaBundle\Domain\Manifest\IconPurpose;

/**
 * An icon of the installed site: its source — a path of the site or the logical path of an asset —, its sizes, its
 * media type, and what it is drawn for.
 */
final readonly class Icon
{
    /** @var non-empty-string */
    public string $src;

    /** @var non-empty-list<IconPurpose> */
    public array $purposes;

    /**
     * @param list<IconPurpose> $purposes any when empty
     */
    public function __construct(
        string $src,
        public string $sizes = 'any',
        public ?string $type = null,
        array $purposes = [],
    ) {
        $this->src = Rule::source($src, 'source of an icon');

        if (1 !== preg_match('/^(?:any|[1-9]\d{0,3}x[1-9]\d{0,3}(?: [1-9]\d{0,3}x[1-9]\d{0,3}){0,9})$/D', $sizes)) {
            throw InvalidManifest::because('The sizes of an icon are "any", or sizes such as "192x192 512x512".');
        }

        if (null !== $type && 1 !== preg_match('#^image/[a-z0-9][a-z0-9.+-]{0,63}$#D', $type)) {
            throw InvalidManifest::because('The type of an icon is the media type of an image, such as image/png.');
        }

        if (\count($purposes) !== \count(array_unique(array_map(static fn (IconPurpose $purpose): string => $purpose->value, $purposes)))) {
            throw InvalidManifest::because('Each purpose of an icon is given once.');
        }

        $this->purposes = [] === $purposes ? [IconPurpose::Any] : $purposes;
    }
}
