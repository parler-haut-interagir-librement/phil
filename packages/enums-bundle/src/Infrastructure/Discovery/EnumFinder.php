<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Infrastructure\Discovery;

use PhpToken;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use UnitEnum;

/**
 * Finds the enums declared in PHP files: what the commands and the translation extractor work on.
 *
 * It reads the tokens of each file — the namespace, then each `enum` declaration — and keeps the enums the
 * autoloader then finds, so a file outside the autoloader's map contributes nothing. A path that is neither a PHP
 * file nor a directory is skipped.
 */
final class EnumFinder
{
    private function __construct()
    {
    }

    /**
     * @param string|iterable<string> $paths files or directories
     *
     * @return list<class-string<UnitEnum>> sorted by name, each once
     */
    public static function find(string|iterable $paths): array
    {
        $enums = [];

        foreach (\is_string($paths) ? [$paths] : $paths as $path) {
            foreach (self::files($path) as $file) {
                $enums = [...$enums, ...self::declared((string) file_get_contents($file))];
            }
        }

        $enums = array_unique(array_filter($enums, enum_exists(...)));
        sort($enums);

        return $enums;
    }

    /**
     * @return list<string>
     */
    private static function files(string $path): array
    {
        if (is_file($path)) {
            return str_ends_with($path, '.php') ? [$path] : [];
        }

        if (!is_dir($path)) {
            return [];
        }

        $files = [];

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            if ('php' === $file->getExtension()) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * The fully qualified names of the enums a source declares.
     *
     * @return list<string>
     */
    private static function declared(string $source): array
    {
        $tokens = array_values(array_filter(PhpToken::tokenize($source), static fn (PhpToken $token): bool => !$token->isIgnorable()));
        $namespace = '';
        $enums = [];

        foreach ($tokens as $index => $token) {
            $next = $tokens[$index + 1] ?? null;

            if ($token->is(\T_NAMESPACE) && null !== $next && $next->is([\T_NAME_QUALIFIED, \T_STRING])) {
                $namespace = $next->text.'\\';
            }

            if ($token->is(\T_ENUM) && null !== $next && $next->is(\T_STRING)) {
                $enums[] = $namespace.$next->text;
            }
        }

        return $enums;
    }
}
