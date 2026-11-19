<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Internal;

use Dom\DocumentType;
use Dom\XMLDocument;
use DOMException;
use Phil\DocumentImport\Exception\InvalidDocument;
use Phil\DocumentImport\Exception\LimitExceeded;
use Phil\DocumentImport\Limits;
use ZipArchive;

/**
 * The entries of a ZIP archive, read at once within the limits — in memory where the PHP version allows it —, and their XML parsed without network access nor
 * document type.
 *
 * @internal
 */
final readonly class Archive
{
    /**
     * @param array<string, string> $entries
     */
    private function __construct(
        private array $entries,
    ) {
    }

    /**
     * @throws InvalidDocument
     * @throws LimitExceeded
     */
    public static function open(string $contents, Limits $limits): self
    {
        return new self(Zip::read($contents, static fn (ZipArchive $zip): array => self::entries($zip, $limits), Zip::inMemory()));
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->entries);
    }

    public function get(string $name): ?string
    {
        return $this->entries[$name] ?? null;
    }

    /**
     * The archive of the entries given, a flat document read as the parts of a package.
     *
     * @param array<string, string> $entries
     */
    public static function of(array $entries): self
    {
        return new self($entries);
    }

    /**
     * The parsed XML of an entry, `null` when the archive does not hold it; the namespaces of `$namespaces` read as
     * the ones they map to.
     *
     * @param array<string, string> $namespaces
     *
     * @throws InvalidDocument
     */
    public function xml(string $name, array $namespaces = []): ?XMLDocument
    {
        $xml = $this->entries[$name] ?? null;

        if (null === $xml) {
            return null;
        }

        if ([] !== $namespaces) {
            $xml = strtr($xml, $namespaces);
        }

        if ('' === $xml) {
            throw InvalidDocument::because(\sprintf('The entry "%s" is not well-formed XML.', $name));
        }

        if (1 === preg_match('/^\s*(?:<\?[^?]*\?>\s*|<!--.*?-->\s*)*<!DOCTYPE/s', substr($xml, 0, 4096))) {
            throw InvalidDocument::because(\sprintf('The entry "%s" declares a document type, which no office document does.', $name));
        }

        $malformed = InvalidDocument::because(\sprintf('The entry "%s" is not well-formed XML.', $name));
        set_error_handler(static fn (): never => throw $malformed);

        try {
            $document = XMLDocument::createFromString($xml, \LIBXML_NONET | \LIBXML_NOERROR | \LIBXML_NOWARNING);
        } catch (DOMException) {
            throw $malformed;
        } finally {
            restore_error_handler();
        }

        if ($document->doctype instanceof DocumentType) {
            throw InvalidDocument::because(\sprintf('The entry "%s" declares a document type, which no office document does.', $name));
        }

        return $document;
    }

    /**
     * The path a relationship or a link points to, from the folder of the entry that holds it: `/` starts from the
     * root of the archive, `..` climbs a folder.
     */
    public static function resolve(string $folder, string $target): string
    {
        $segments = [];

        foreach (explode('/', str_starts_with($target, '/') ? $target : $folder.'/'.$target) as $segment) {
            match ($segment) {
                '', '.' => null,
                '..' => array_pop($segments),
                default => $segments[] = $segment,
            };
        }

        return implode('/', $segments);
    }

    /**
     * The entries of an open archive, their sizes as declared checked before they are read; an entry whose bytes
     * do not match its declared size or checksum cannot be read.
     *
     * @return array<string, string>
     *
     * @throws InvalidDocument
     * @throws LimitExceeded
     */
    private static function entries(ZipArchive $zip, Limits $limits): array
    {
        $entries = [];
        $total = 0;

        for ($index = 0; $index < $zip->numFiles; ++$index) {
            $stat = $zip->statIndex($index);

            if (false === $stat) {
                throw InvalidDocument::because(\sprintf('The entry %d of the archive cannot be read.', $index));
            }

            if ($stat['size'] > $limits->entryBytes) {
                throw LimitExceeded::because(\sprintf('The entry "%s" is larger than %d bytes once uncompressed.', $stat['name'], $limits->entryBytes));
            }

            $total += $stat['size'];

            if ($total > $limits->totalBytes) {
                throw LimitExceeded::because(\sprintf('The archive is larger than %d bytes once uncompressed.', $limits->totalBytes));
            }

            $entries[$stat['name']] = self::read($zip, $index, $stat['name']);
        }

        return $entries;
    }

    /**
     * The bytes of an entry; the warning of the ZIP library on a corrupted entry becomes the exception of the library.
     *
     * @throws InvalidDocument
     */
    private static function read(ZipArchive $zip, int $index, string $name): string
    {
        set_error_handler(static fn (): never => throw InvalidDocument::because(\sprintf('The entry "%s" of the archive cannot be read.', $name)));

        try {
            return (string) $zip->getFromIndex($index);
        } finally {
            restore_error_handler();
        }
    }
}
