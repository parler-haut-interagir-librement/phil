<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Stage;

use Override;
use Phil\PagedMedia\Gotenberg\InputFile;
use Phil\PagedMedia\Gotenberg\Internal\Check;

/**
 * Embeds a file in the PDF: the source data, a spreadsheet, the XML of an invoice. The PDF lists it under its name
 * unless another is given, with the media type read from the name unless one is given.
 */
final readonly class Attach implements Stage
{
    public function __construct(
        public InputFile $file,
        public ?string $name = null,
        public ?string $mimeType = null,
        public ?Relationship $relationship = null,
    ) {
        Check::notEmpty('The name of an attachment', $name);
        Check::notEmpty('The media type of an attachment', $mimeType);
    }

    #[Override]
    public function operation(): Operation
    {
        return Operation::Attach;
    }

    #[Override]
    public function options(): array
    {
        return Check::set(['file' => $this->file->name, 'name' => $this->name, 'mimeType' => $this->mimeType, 'relationship' => $this->relationship?->value]);
    }

    #[Override]
    public function files(): array
    {
        return [$this->file];
    }
}
