<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application\Content;

use Phil\ContentBundle\Domain\Content\ContentRoot;

/**
 * Builds a new root of one kind of content, for `ContentManager::create()`.
 *
 * `Infrastructure\Doctrine\ConstructorContentRootFactory` calls the root's constructor without arguments; an
 * application whose roots need arguments aliases this interface to its own factory.
 */
interface ContentRootFactory
{
    public function create(string $resourceKey): ContentRoot;
}
