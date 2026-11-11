<?php

declare(strict_types=1);

namespace Phil\StructuredData\Type;

use Phil\StructuredData\LanguageString;
use Phil\StructuredData\Node;
use Phil\StructuredData\Reference;

/**
 * An `ImageObject`: the address of the file, its caption, size in pixels, licence and who made it.
 */
final class ImageObject
{
    /**
     * @param array<string, mixed> $properties the other properties, which the named ones replace
     */
    public static function of(
        string $contentUrl,
        ?string $id = null,
        string|LanguageString|null $caption = null,
        ?int $width = null,
        ?int $height = null,
        ?string $license = null,
        Node|Reference|null $creator = null,
        string|LanguageString|null $creditText = null,
        ?string $url = null,
        array $properties = [],
    ): Node {
        return Node::of('ImageObject', Properties::merge($properties, [
            'contentUrl' => $contentUrl,
            'url' => $url,
            'caption' => $caption,
            'width' => $width,
            'height' => $height,
            'license' => $license,
            'creator' => $creator,
            'creditText' => $creditText,
        ]), $id);
    }
}
