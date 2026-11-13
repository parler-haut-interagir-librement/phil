<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Read;

/**
 * What `/forms/pdf/info` reads of a PDF: its page count, its version, and whether it is encrypted, tagged for screen
 * readers, linearized for fast web view, or holds form fields.
 */
final readonly class PdfInfo
{
    public function __construct(
        public int $pages,
        public string $version,
        public bool $encrypted,
        public bool $tagged,
        public bool $linearized,
        public bool $formFields,
    ) {
    }

    /**
     * @internal
     */
    public static function fromJson(JsonValue $json): self
    {
        return new self($json->int('pages'), $json->string('version'), $json->bool('encrypted'), $json->bool('tagged'), $json->bool('linearized'), $json->bool('formFields'));
    }
}
