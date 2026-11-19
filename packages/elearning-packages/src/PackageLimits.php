<?php

declare(strict_types=1);

namespace Phil\ElearningPackages;

use Phil\ElearningPackages\Exception\InvalidLimits;

/**
 * The bounds of a reading, so that a package cannot exhaust the disk, the memory or the time of the server: the size
 * of the archive, its number of entries, the size of one file and of all of them once uncompressed, the compression
 * ratio of a file, the size of a manifest, the XML nodes and the nesting of its elements, counted before it is loaded,
 * and the depth and the number of the items it describes.
 */
final readonly class PackageLimits
{
    public const int ARCHIVE_BYTES = 512 * 1024 * 1024;

    public const int ENTRIES = 10_000;

    public const int ENTRY_BYTES = 256 * 1024 * 1024;

    public const int TOTAL_BYTES = 2 * 1024 * 1024 * 1024;

    public const int RATIO = 200;

    public const int MANIFEST_BYTES = 4 * 1024 * 1024;

    public const int DEPTH = 16;

    public const int ITEMS = 5_000;

    public const int MANIFEST_NODES = 500_000;

    public const int MANIFEST_DEPTH = 64;

    /**
     * @param int $archiveBytes  the size of the archive file
     * @param int $entries       how many entries the archive holds, directories included
     * @param int $entryBytes    the size of one file once uncompressed
     * @param int $totalBytes    the size of every file once uncompressed, announced and read
     * @param int $ratio         how many times a file larger than a mebibyte may be compressed
     * @param int $manifestBytes the size of `imsmanifest.xml` or `cmi5.xml`
     * @param int $depth         how deep the items or the blocks of a manifest nest
     * @param int $items         how many items, resources, blocks or units a manifest describes
     * @param int $manifestNodes how many XML nodes a manifest holds — elements, attributes, text and comments —, counted
     *                           before it is loaded
     * @param int $manifestDepth how deep the XML elements of a manifest nest, the root at 1, checked before it is loaded
     *
     * @throws InvalidLimits when a bound is not positive
     */
    public function __construct(
        public int $archiveBytes = self::ARCHIVE_BYTES,
        public int $entries = self::ENTRIES,
        public int $entryBytes = self::ENTRY_BYTES,
        public int $totalBytes = self::TOTAL_BYTES,
        public int $ratio = self::RATIO,
        public int $manifestBytes = self::MANIFEST_BYTES,
        public int $depth = self::DEPTH,
        public int $items = self::ITEMS,
        public int $manifestNodes = self::MANIFEST_NODES,
        public int $manifestDepth = self::MANIFEST_DEPTH,
    ) {
        if (min($archiveBytes, $entries, $entryBytes, $totalBytes, $ratio, $manifestBytes, $depth, $items, $manifestNodes, $manifestDepth) < 1) {
            throw InvalidLimits::because('Every limit of a package is a positive number.');
        }
    }
}
