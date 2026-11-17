<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Fixture;

use Override;
use Phil\ContentBundle\Domain\Contract\SingleRootRepository;
use Phil\ContentBundle\Domain\Exception\InvalidContent;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;

/**
 * The root of each single kind, by resource key, refusing a second one as the primary key does.
 */
final class InMemorySingleRoots implements SingleRootRepository
{
    /** @var array<string, string> the identifier of the root of each kind */
    public array $roots = [];

    #[Override]
    public function claim(ContentReference $root): void
    {
        if (isset($this->roots[$root->resourceKey])) {
            throw InvalidContent::singleKindTaken($root->resourceKey);
        }

        $this->roots[$root->resourceKey] = $root->resourceId;
    }

    #[Override]
    public function release(ContentReference $root): void
    {
        if (($this->roots[$root->resourceKey] ?? null) === $root->resourceId) {
            unset($this->roots[$root->resourceKey]);
        }
    }
}
