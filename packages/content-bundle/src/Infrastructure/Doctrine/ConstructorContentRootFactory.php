<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Infrastructure\Doctrine;

use Override;
use Phil\ContentBundle\Application\Content\ContentRootFactory;
use Phil\ContentBundle\Domain\Content\ContentRoot;
use ReflectionClass;
use ReflectionMethod;

/**
 * Builds a root through its constructor, called without arguments: the root generates its own identifier,
 * or leaves it to the database.
 *
 * A root whose constructor requires arguments is refused; the application then aliases
 * `ContentRootFactory` to its own factory.
 */
final readonly class ConstructorContentRootFactory implements ContentRootFactory
{
    public function __construct(
        private ContentRoots $roots,
    ) {
    }

    #[Override]
    public function create(string $resourceKey): ContentRoot
    {
        $class = $this->roots->classOf($resourceKey);
        $constructor = new ReflectionClass($class)->getConstructor();

        if ($constructor instanceof ReflectionMethod && (0 < $constructor->getNumberOfRequiredParameters() || !$constructor->isPublic())) {
            throw InvalidContentMapping::because(\sprintf('"%s" cannot be built without arguments; alias %s to a factory of your own.', $class, ContentRootFactory::class));
        }

        return new $class();
    }
}
