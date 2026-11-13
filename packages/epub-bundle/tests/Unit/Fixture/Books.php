<?php

declare(strict_types=1);

namespace Phil\EpubBundle\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use Phil\Epub\Book;
use Phil\Epub\Chapter;
use Phil\Epub\Identifier;
use Phil\Epub\Metadata;
use Phil\EpubBundle\Application\BookSource;
use RuntimeException;
use ZipArchive;

/**
 * Books for the tests, a source that builds them by identifier, and the files of a written EPUB read back.
 */
final class Books implements BookSource
{
    /** @var list<string> */
    public array $built = [];

    public static function book(string $title = 'The Lighthouse'): Book
    {
        return new Book(new Metadata($title, 'en', Identifier::uuid('7c9e6679-7425-40de-944b-e07fc1f90ae7'), new DateTimeImmutable('2026-11-07T12:00:00Z')))
            ->withChapter(new Chapter('<h1>One</h1><p>The keeper climbs.</p>'));
    }

    #[Override]
    public function build(string $id): Book
    {
        $this->built[] = $id;

        return self::book('Story '.$id);
    }

    /**
     * @return array<string, string> the entries of an EPUB file, by name
     */
    public static function entries(string $epub): array
    {
        $path = tempnam(sys_get_temp_dir(), 'epub-test');

        if (false === $path) {
            throw new RuntimeException('No temporary file.');
        }

        file_put_contents($path, $epub);
        $zip = new ZipArchive();
        $zip->open($path);

        $entries = [];

        for ($index = 0; $index < $zip->numFiles; ++$index) {
            $name = (string) $zip->getNameIndex($index);
            $entries[$name] = (string) $zip->getFromIndex($index);
        }

        $zip->close();
        unlink($path);

        return $entries;
    }
}
