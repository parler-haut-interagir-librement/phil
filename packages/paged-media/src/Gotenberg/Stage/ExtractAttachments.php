<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Stage;

use Override;
use Phil\PagedMedia\Gotenberg\Internal\Check;

/**
 * Returns the files embedded in the PDF, or the one of that name, each named after the PDF and itself
 * (`invoice_factur-x.xml`). It comes last; after other stages, the ZIP holds the PDF next to its files.
 */
final readonly class ExtractAttachments implements Stage
{
    public function __construct(public ?string $name = null)
    {
        Check::notEmpty('The name of an attachment', $name);
    }

    #[Override]
    public function operation(): Operation
    {
        return Operation::ExtractAttachments;
    }

    #[Override]
    public function options(): array
    {
        return null === $this->name ? [] : ['name' => $this->name];
    }

    #[Override]
    public function files(): array
    {
        return [];
    }
}
