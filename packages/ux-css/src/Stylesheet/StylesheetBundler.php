<?php

declare(strict_types=1);

namespace Phil\UxCss\Stylesheet;

use Phil\UxCss\Exception\UnreadableSheet;

/**
 * One stylesheet from an entry that imports others into layers: each `@import url("…") layer(…);` becomes the
 * `@layer … { … }` block holding the imported file, so the result is served as a single file — through an importmap,
 * a CDN or a pipeline that does not follow `@import`.
 *
 * Only the imports of the entry are inlined, each read relative to it; an import that names no layer, or one inside
 * an imported file, is left as written. An import must stay within the directory of the entry: an absolute path, or
 * one whose `..` climbs above that directory, is refused, so that bundling a sheet never copies another file of the
 * server into what is served.
 */
final readonly class StylesheetBundler
{
    /**
     * @throws UnreadableSheet
     */
    public static function bundle(string $entry): string
    {
        $css = self::read($entry);
        $directory = \dirname($entry);

        return (string) preg_replace_callback(
            '/^@import url\("([^"]+)"\) layer\(([\w.-]+)\);$/m',
            static function (array $import) use ($directory): string {
                if (!self::within($import[1])) {
                    throw UnreadableSheet::outside($import[1]);
                }

                $contents = rtrim(self::read($directory.'/'.$import[1]));
                $indented = (string) preg_replace('/^(?=.)/m', '    ', $contents);

                return \sprintf("@layer %s {\n%s\n}", $import[2], $indented);
            },
            $css,
        );
    }

    /**
     * Whether a relative path stays within its directory, `.` and `..` read as written.
     */
    private static function within(string $path): bool
    {
        if (str_starts_with($path, '/') || str_contains($path, ':')) {
            return false;
        }

        $depth = 0;

        foreach (explode('/', $path) as $segment) {
            $depth += match ($segment) {
                '..' => -1,
                '', '.' => 0,
                default => 1,
            };

            if ($depth < 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * @throws UnreadableSheet
     */
    private static function read(string $path): string
    {
        $contents = is_file($path) && is_readable($path) ? file_get_contents($path) : false;

        if (false === $contents) {
            throw UnreadableSheet::at($path);
        }

        return $contents;
    }
}
