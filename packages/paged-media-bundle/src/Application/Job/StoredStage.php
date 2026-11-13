<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Application\Job;

use Override;
use Phil\PagedMedia\Gotenberg\InputFile;
use Phil\PagedMedia\Gotenberg\Stage\Operation;
use Phil\PagedMedia\Gotenberg\Stage\Stage;

/**
 * A stage read back from a kept job: its operation, its options as Gotenberg names them and the files they name, as
 * the stage gave them when the job was kept. It was checked when it was first built; it sends the same request.
 */
final readonly class StoredStage implements Stage
{
    /**
     * @param array<string, scalar|array<array-key, mixed>> $options
     * @param list<InputFile>                               $files
     */
    public function __construct(
        private Operation $operation,
        private array $options = [],
        private array $files = [],
    ) {
    }

    #[Override]
    public function operation(): Operation
    {
        return $this->operation;
    }

    #[Override]
    public function options(): array
    {
        return $this->options;
    }

    #[Override]
    public function files(): array
    {
        return $this->files;
    }
}
