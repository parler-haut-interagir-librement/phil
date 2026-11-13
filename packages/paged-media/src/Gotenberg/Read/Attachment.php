<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Read;

/**
 * A file embedded in a PDF, with its media type and its relationship to the document when the PDF states them.
 */
final readonly class Attachment
{
    public function __construct(
        public string $name,
        public ?string $mimeType = null,
        public ?string $relationship = null,
    ) {
    }

    /**
     * @internal
     */
    public static function fromJson(JsonValue $json): self
    {
        return new self($json->string('name'), $json->optionalString('mimeType'), $json->optionalString('relationship'));
    }
}
