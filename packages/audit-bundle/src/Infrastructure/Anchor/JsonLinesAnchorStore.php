<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Infrastructure\Anchor;

use DateTimeImmutable;
use Override;
use Phil\AuditBundle\Domain\Chain\Anchor;
use Phil\AuditBundle\Domain\Contract\AnchorReader;
use Phil\AuditBundle\Domain\Contract\AnchorWriter;
use RuntimeException;

/**
 * Anchors appended to a file, one JSON line each. **It is append-only only if the file lives on storage the database's
 * administrator cannot rewrite** — a mount under object lock, a WORM volume —; on the application host it is for
 * development and tests, and makes a full rewrite of the chain no harder.
 */
final readonly class JsonLinesAnchorStore implements AnchorWriter, AnchorReader
{
    public function __construct(
        private string $file,
    ) {
    }

    #[Override]
    public function write(string $segment, DateTimeImmutable $periodEnd, string $firstHash, string $headHash): void
    {
        $line = json_encode(['segment' => $segment, 'period_end' => $periodEnd->format(\DATE_ATOM), 'first_hash' => $firstHash, 'head_hash' => $headHash], \JSON_THROW_ON_ERROR)."\n";

        if (false === @file_put_contents($this->file, $line, \FILE_APPEND | \LOCK_EX)) {
            throw new RuntimeException(\sprintf('The anchor of "%s" could not be written to %s.', $segment, $this->file));
        }
    }

    #[Override]
    public function anchors(string $segment): array
    {
        $content = @file_get_contents($this->file);
        $anchors = [];

        foreach (explode("\n", \is_string($content) ? $content : '') as $line) {
            $anchor = json_decode($line, true);

            if (\is_array($anchor) && $segment === ($anchor['segment'] ?? null) && \is_string($anchor['period_end'] ?? null) && \is_string($anchor['first_hash'] ?? null) && \is_string($anchor['head_hash'] ?? null)) {
                $anchors[] = new Anchor($segment, new DateTimeImmutable($anchor['period_end']), $anchor['first_hash'], $anchor['head_hash']);
            }
        }

        return $anchors;
    }
}
