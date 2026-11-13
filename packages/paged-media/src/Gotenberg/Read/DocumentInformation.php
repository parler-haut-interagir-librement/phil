<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Read;

/**
 * The document information of a PDF, as `/forms/pdf/metadata` reads it and `set-metadata` writes it. A date stays as
 * the PDF stores it: a PDF date, `D:20260101120000Z`, or an XMP date when only the XMP metadata holds it.
 */
final readonly class DocumentInformation
{
    public function __construct(
        public ?string $title = null,
        public ?string $author = null,
        public ?string $subject = null,
        public ?string $keywords = null,
        public ?string $creator = null,
        public ?string $producer = null,
        public ?string $creationDate = null,
        public ?string $modDate = null,
    ) {
    }

    /**
     * @internal
     */
    public static function fromJson(JsonValue $json): self
    {
        return new self(
            $json->optionalString('title'),
            $json->optionalString('author'),
            $json->optionalString('subject'),
            $json->optionalString('keywords'),
            $json->optionalString('creator'),
            $json->optionalString('producer'),
            $json->optionalString('creationDate'),
            $json->optionalString('modDate'),
        );
    }
}
