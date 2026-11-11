<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Infrastructure\Memory;

use Override;
use Phil\ReportingBundle\Application\Contract\ExportStore;
use Phil\ReportingBundle\Application\Exception\JobUnavailable;
use Phil\ReportingBundle\Application\Report\ReportStream;
use Phil\ReportingBundle\Application\Report\StoredExport;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The exported files in memory, for the tests and the `sync://` transport: forgotten when the container is reset.
 */
final class InMemoryExportStore implements ExportStore, ResetInterface
{
    /** @var array<string, string> */
    private array $contents = [];

    #[Override]
    public function keep(ReportStream $content, string $filename, string $mediaType, int $rows): StoredExport
    {
        $id = sodium_bin2hex(random_bytes(16));
        $this->contents[$id] = $content->contents();

        return new StoredExport($id, $filename, $mediaType, \strlen($this->contents[$id]), $rows);
    }

    #[Override]
    public function open(string $id): ReportStream
    {
        $content = $this->contents[$id] ?? throw JobUnavailable::of($id, 'no export is kept under it');
        $stream = ReportStream::temporary();
        $stream->write($content);
        $stream->rewind();

        return $stream;
    }

    #[Override]
    public function remove(string $id): void
    {
        unset($this->contents[$id]);
    }

    /**
     * The contents kept, by identifier.
     *
     * @return array<string, string>
     */
    public function contents(): array
    {
        return $this->contents;
    }

    #[Override]
    public function reset(): void
    {
        $this->contents = [];
    }
}
