<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Stage;

use Override;
use SensitiveParameter;

/**
 * Opens encrypted PDFs and removes their encryption; in a list, it comes first.
 */
final readonly class Decrypt implements Stage
{
    public function __construct(
        #[SensitiveParameter]
        private ?string $password = null,
    ) {
    }

    #[Override]
    public function operation(): Operation
    {
        return Operation::Decrypt;
    }

    #[Override]
    public function options(): array
    {
        return null === $this->password ? [] : ['password' => $this->password];
    }

    #[Override]
    public function files(): array
    {
        return [];
    }
}
