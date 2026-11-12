<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Application\Transform;

use Phil\MediaBundle\Domain\Exception\InvalidMedia;

/**
 * Transformations from their configuration: `['type' => 'crop', 'width' => 300, 'height' => 200]`.
 */
final class Transformers
{
    public const array TYPES = ['thumbnail', 'crop', 'resize', 'widen', 'heighten', 'expand'];

    /**
     * @param list<array<string, mixed>> $definitions
     *
     * @return list<Transformer>
     */
    public static function fromDefinitions(array $definitions): array
    {
        return array_map(self::fromDefinition(...), $definitions);
    }

    /**
     * @param array<string, mixed> $definition
     */
    public static function fromDefinition(array $definition): Transformer
    {
        $width = self::integer($definition, 'width');
        $height = self::integer($definition, 'height');
        $x = self::integer($definition, 'x');
        $y = self::integer($definition, 'y');
        $upscale = true === ($definition['upscale'] ?? false);
        $type = $definition['type'] ?? null;

        return match ($type) {
            'thumbnail' => Thumbnail::of(self::required($width, $type, 'width'), self::required($height, $type, 'height'), \is_string($definition['mode'] ?? null) ? $definition['mode'] : 'crop'),
            'crop' => Crop::of(self::required($width, $type, 'width'), self::required($height, $type, 'height'), $x, $y),
            'resize' => Resize::of(self::required($width, $type, 'width'), self::required($height, $type, 'height'), $upscale),
            'widen' => Widen::of(self::required($width, $type, 'width'), $upscale),
            'heighten' => Heighten::of(self::required($height, $type, 'height'), $upscale),
            'expand' => Expand::of(self::required($width, $type, 'width'), self::required($height, $type, 'height'), $x, $y, \is_string($definition['background'] ?? null) ? $definition['background'] : null),
            default => throw InvalidMedia::because(\sprintf('A transformation is one of %s; "%s" is not.', implode(', ', self::TYPES), \is_string($type) ? $type : get_debug_type($type))),
        };
    }

    /**
     * @param array<string, mixed> $definition
     */
    private static function integer(array $definition, string $key): ?int
    {
        $value = $definition[$key] ?? null;

        return \is_int($value) ? $value : null;
    }

    private static function required(?int $value, string $type, string $key): int
    {
        return $value ?? throw InvalidMedia::because(\sprintf('A "%s" transformation needs a %s.', $type, $key));
    }
}
