<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Internal;

use Phil\PagedMedia\Gotenberg\Exception\InvalidStage;
use Phil\PagedMedia\Gotenberg\InputFile;

/**
 * The checks the stages share, each throwing `InvalidStage` with a message that names the option.
 *
 * @internal
 */
final class Check
{
    public static function above(string $option, float|int|null $value, float|int $floor): void
    {
        if (null !== $value && $value <= $floor) {
            throw InvalidStage::because(\sprintf('%s is above %s; %s is not.', $option, $floor, $value));
        }
    }

    public static function between(string $option, float|int|null $value, float|int $min, float|int $max): void
    {
        if (null !== $value && ($value < $min || $value > $max)) {
            throw InvalidStage::because(\sprintf('%s is from %s to %s; %s is not.', $option, $min, $max, $value));
        }
    }

    public static function notEmpty(string $option, ?string $value): void
    {
        if (null !== $value && '' === trim($value)) {
            throw InvalidStage::because(\sprintf('%s is not empty.', $option));
        }
    }

    public static function color(string $option, ?string $value): void
    {
        if (null !== $value && (7 !== \strlen($value) || '#' !== $value[0] || !ctype_xdigit(ltrim($value, '#')))) {
            throw InvalidStage::because(\sprintf('%s is written #rrggbb; "%s" is not.', $option, $value));
        }
    }

    /**
     * @param list<string> $extensions
     */
    public static function extension(string $option, InputFile $file, array $extensions): void
    {
        if (!\in_array($file->extension(), $extensions, true)) {
            throw InvalidStage::because(\sprintf('%s is a .%s file; "%s" is not.', $option, implode(', .', $extensions), $file->name));
        }
    }

    /**
     * The options without the ones left unset.
     *
     * @param array<string, scalar|array<array-key, mixed>|null> $options
     *
     * @return array<string, scalar|array<array-key, mixed>>
     */
    public static function set(array $options): array
    {
        return array_filter($options, static fn (mixed $value): bool => null !== $value);
    }
}
