<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Stage;

use Override;
use Phil\PagedMedia\Gotenberg\Internal\Check;

/**
 * Removes a file embedded in the PDF, by the name `/forms/pdf/attachments` lists.
 */
final readonly class Detach implements Stage
{
    public function __construct(public string $name)
    {
        Check::notEmpty('The name of the attachment to remove', $name);
    }

    #[Override]
    public function operation(): Operation
    {
        return Operation::Detach;
    }

    #[Override]
    public function options(): array
    {
        return ['name' => $this->name];
    }

    #[Override]
    public function files(): array
    {
        return [];
    }
}
