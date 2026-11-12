<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Fixture;

use LogicException;
use Phil\ContentBundle\Domain\Content\ContentRoot;
use Phil\ContentBundle\Domain\Repository\ContentRootRepository;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;

/**
 * A root exists for every reference but the ones listed as deleted: what the route table's tests need to
 * tell a slug held by a root from a slug held by nobody.
 */
final class ExistingRoots implements ContentRootRepository
{
    /**
     * @var list<string>
     */
    public array $deleted = [];

    public function find(ContentReference $reference): ?ContentRoot
    {
        return \in_array($reference->toString(), $this->deleted, true) ? null : new ExampleRoot();
    }

    public function findAll(string $resourceKey): array
    {
        throw new LogicException('Not needed by the route table.');
    }

    public function referenceOf(ContentRoot $root): ContentReference
    {
        throw new LogicException('Not needed by the route table.');
    }

    public function add(ContentRoot $root): void
    {
        throw new LogicException('Not needed by the route table.');
    }

    public function remove(ContentRoot $root): void
    {
        throw new LogicException('Not needed by the route table.');
    }
}
