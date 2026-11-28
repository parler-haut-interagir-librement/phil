<?php

declare(strict_types=1);

namespace Phil\BenefitDeliverables\Tests\Support;

use Override;
use Phil\BenefitDeliverables\Contract\ExportFileStore;

final class InMemoryFiles implements ExportFileStore
{
    /** @var array<string, string> */
    public array $files = [];

    /** @var list<string> */
    public array $deleted = [];

    #[Override]
    public function write(string $id, callable $writer): void
    {
        $stream = fopen('php://memory', 'w+');
        \assert(\is_resource($stream));

        $writer($stream);
        rewind($stream);
        $this->files[$id] = (string) stream_get_contents($stream);
        fclose($stream);
    }

    #[Override]
    public function open(string $id): mixed
    {
        if (!isset($this->files[$id])) {
            return null;
        }

        $stream = fopen('php://memory', 'w+');
        \assert(\is_resource($stream));
        fwrite($stream, $this->files[$id]);
        rewind($stream);

        return $stream;
    }

    #[Override]
    public function delete(string $id): void
    {
        $this->deleted[] = $id;
        unset($this->files[$id]);
    }
}
