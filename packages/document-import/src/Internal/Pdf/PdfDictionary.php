<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Internal\Pdf;

/**
 * A dictionary of a PDF file, its entries by name.
 *
 * @internal
 */
final readonly class PdfDictionary
{
    /**
     * @param array<string, mixed> $entries
     */
    public function __construct(public array $entries = [])
    {
    }

    public function get(string $key): mixed
    {
        return $this->entries[$key] ?? null;
    }

    /**
     * The name an entry holds, `null` when it holds none.
     */
    public function name(string $key): ?string
    {
        $value = $this->entries[$key] ?? null;

        return $value instanceof PdfName ? $value->name : null;
    }
}
