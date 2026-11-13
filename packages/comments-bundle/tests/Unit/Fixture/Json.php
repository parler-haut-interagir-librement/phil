<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Tests\Unit\Fixture;

/**
 * A value inside decoded JSON by its dotted path, `thread.comments.1.body`; `null` where there is none.
 */
final class Json
{
    public static function at(mixed $value, string $path): mixed
    {
        foreach (explode('.', $path) as $key) {
            $value = \is_array($value) ? $value[$key] ?? null : null;
        }

        return $value;
    }

    /**
     * A text inside decoded JSON; `null` when it is something else.
     */
    public static function text(mixed $value, string $path): ?string
    {
        $text = self::at($value, $path);

        return \is_string($text) ? $text : null;
    }

    /**
     * The values of one key in each item of a list inside decoded JSON.
     *
     * @return list<mixed>
     */
    public static function column(mixed $value, string $path, string $key): array
    {
        $list = self::at($value, $path);

        return \is_array($list) ? array_values(array_map(static fn (mixed $item): mixed => self::at($item, $key), $list)) : [];
    }
}
