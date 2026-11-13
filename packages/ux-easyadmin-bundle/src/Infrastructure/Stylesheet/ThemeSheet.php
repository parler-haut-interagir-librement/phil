<?php

declare(strict_types=1);

namespace Phil\UxEasyAdminBundle\Infrastructure\Stylesheet;

use Composer\InstalledVersions;
use LogicException;
use RuntimeException;

/**
 * The theme's single stylesheet, written from its source: each `@import url("tokens/<file>.css") layer(<layer>);` line
 * becomes `@layer <layer> { … }` holding that token file of phil/ux-css. Only a file name of lower-case letters and
 * hyphens is read, from the tokens directory and nowhere else.
 *
 * One file keeps the theme a single request, and keeps AssetMapper from resolving an import across two packages.
 */
final readonly class ThemeSheet
{
    private const string IMPORT = '/^@import url\("tokens\/([a-z-]+\.css)"\) layer\(([a-z.-]+)\);$/m';

    public static function build(string $source, string $tokensDirectory): string
    {
        return (string) preg_replace_callback(
            self::IMPORT,
            static function (array $import) use ($tokensDirectory): string {
                $contents = rtrim(self::read($tokensDirectory.'/'.$import[1]));

                return \sprintf("@layer %s {\n%s\n}", $import[2], preg_replace('/^(?=.)/m', '    ', $contents));
            },
            self::read($source),
        );
    }

    /**
     * The token sources of phil/ux-css, where Composer installed it.
     */
    public static function tokensDirectory(): string
    {
        $package = InstalledVersions::getInstallPath('phil/ux-css') ?? throw new LogicException('phil/ux-css is not installed: require it with Composer.');

        return $package.'/css/tokens';
    }

    private static function read(string $path): string
    {
        $contents = is_file($path) ? file_get_contents($path) : false;

        return false === $contents ? throw new RuntimeException(\sprintf('The stylesheet "%s" cannot be read.', $path)) : $contents;
    }
}
