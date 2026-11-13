<?php

declare(strict_types=1);

namespace Phil\StatusPageBundle\Application\Snapshot;

use DateTimeImmutable;

/**
 * The static copy of the page at one moment: `index.html`, `status.json` and `feed.atom`, in the order a publisher
 * writes them, so the HTML that links to the others comes last.
 */
final readonly class Snapshot
{
    public const string HTML = 'index.html';

    public const string JSON = 'status.json';

    public const string ATOM = 'feed.atom';

    /**
     * @param list<SnapshotFile> $files
     */
    public function __construct(
        public DateTimeImmutable $generatedAt,
        public array $files,
    ) {
    }

    public static function of(DateTimeImmutable $generatedAt, string $html, string $json, string $atom): self
    {
        return new self($generatedAt, [
            new SnapshotFile(self::JSON, $json, 'application/json'),
            new SnapshotFile(self::ATOM, $atom, 'application/atom+xml'),
            new SnapshotFile(self::HTML, $html, 'text/html; charset=utf-8'),
        ]);
    }
}
