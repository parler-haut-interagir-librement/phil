<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\ContentBundle\Domain\Content\ContentRoot;
use Phil\ContentBundle\Domain\Exception\InvalidContent;
use Phil\ContentBundle\Domain\Repository\ContentRootRepository;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Stringable;

/**
 * `ContentRootRepository` on the Doctrine ORM, for every root declared under `phil_content.roots`.
 *
 * A reference holds the root's identifier as a string; the identifier is rebuilt from it by the type of the
 * root's identifier property (`RootIdentifiers`), so a lookup binds the value its own DBAL type expects, on every
 * platform; a string that cannot be an identifier of its type finds no root.
 *
 * The other way, the identifier is written as its string: an integer in decimal, a `Stringable` through its
 * `__toString()`. A composite identifier has no reference.
 */
final readonly class DoctrineContentRootRepository implements ContentRootRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ContentRoots $roots,
    ) {
    }

    #[Override]
    public function find(ContentReference $reference): ?ContentRoot
    {
        $class = $this->roots->classOf($reference->resourceKey);
        $identifier = new RootIdentifiers($this->entityManager)->fromString($class, $reference->resourceId);

        return null === $identifier ? null : $this->entityManager->find($class, $identifier);
    }

    /**
     * @return list<ContentRoot>
     */
    #[Override]
    public function findAll(string $resourceKey): array
    {
        return $this->entityManager->getRepository($this->roots->classOf($resourceKey))->findAll();
    }

    #[Override]
    public function referenceOf(ContentRoot $root): ContentReference
    {
        $values = $this->entityManager->getClassMetadata($root::class)->getIdentifierValues($root);

        if (1 !== \count($values)) {
            throw InvalidContent::because(\sprintf('A root of "%s" needs a single identifier, set, to have a reference.', $root::class));
        }

        $value = array_first($values);

        $string = match (true) {
            \is_int($value) => (string) $value,
            \is_string($value) => $value,
            $value instanceof Stringable => (string) $value,
            default => throw InvalidContent::because(\sprintf('The identifier of "%s" has no string form; got %s.', $root::class, get_debug_type($value))),
        };

        return ContentReference::of($this->roots->resourceKeyOf($root), $string);
    }

    #[Override]
    public function add(ContentRoot $root): void
    {
        $this->entityManager->persist($root);
    }

    #[Override]
    public function remove(ContentRoot $root): void
    {
        $this->entityManager->remove($root);
    }
}
