<?php

declare(strict_types=1);

namespace Phil\DocumentImportBundle\Tests\Unit;

use Override;
use Phil\DocumentImport\Exception\InvalidDocument;
use Phil\DocumentImportBundle\Application\Contract\DocumentScreening;

/**
 * A screening that keeps the files it is shown, in order, and refuses them once told to.
 */
final class RecordedScreening implements DocumentScreening
{
    /** @var list<array{string, string}> */
    private array $screened = [];

    public bool $refuse = false;

    /**
     * @return list<array{string, string}>
     */
    public function screened(): array
    {
        return $this->screened;
    }

    /**
     * How many files were screened.
     */
    public function times(): int
    {
        return \count($this->screened);
    }

    #[Override]
    public function screen(string $contents, string $filename): void
    {
        if ($this->refuse) {
            throw InvalidDocument::because('Refused.');
        }

        $this->screened[] = [$contents, $filename];
    }
}
