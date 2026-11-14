<?php

declare(strict_types=1);

namespace Phil\OpenBadgesBundle\Infrastructure\DependencyInjection;

use JsonException;
use LogicException;
use Phil\OpenBadges\Contract\ContextLoader;
use Phil\OpenBadges\JsonLd\BundledContexts;

/**
 * The JSON-LD contexts the application provides, by URL, read from the files the configuration names when the
 * Data Integrity suite is first used: a context document is a JSON object that holds an `@context`. Any URL neither the
 * application nor the library gives is refused, so nothing is ever fetched while a proof is made or checked.
 *
 * @internal
 */
final class Contexts
{
    /** The largest context file read, in bytes. */
    public const int MAX_SIZE = 1_048_576;

    private function __construct()
    {
    }

    /**
     * @param array<string, string> $files the file of each context, by its URL
     */
    public static function loader(array $files): ContextLoader
    {
        $documents = [];

        foreach ($files as $url => $file) {
            $documents[$url] = self::read($url, $file);
        }

        return new BundledContexts($documents);
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function read(string $url, string $file): array
    {
        $size = is_file($file) && is_readable($file) ? filesize($file) : false;

        if (false === $size || $size > self::MAX_SIZE) {
            throw new LogicException(\sprintf('phil_open_badges.contexts: the context "%s" is read from "%s", which is not a readable file of at most %d bytes.', $url, $file, self::MAX_SIZE));
        }

        try {
            $document = json_decode((string) file_get_contents($file), true, 64, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $document = null;
        }

        if (!\is_array($document) || !\is_array($document['@context'] ?? null)) {
            throw new LogicException(\sprintf('phil_open_badges.contexts: the file "%s" of the context "%s" is not a JSON object with an "@context".', $file, $url));
        }

        return $document;
    }
}
