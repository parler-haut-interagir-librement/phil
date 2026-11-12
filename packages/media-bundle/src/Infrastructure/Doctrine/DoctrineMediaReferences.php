<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Override;
use Phil\MediaBundle\Application\DeleteBehaviour;
use Phil\MediaBundle\Application\MediaReference;
use Phil\MediaBundle\Application\MediaReferences;
use Phil\MediaBundle\Domain\ValueObject\MediaId;

/**
 * The columns of type `phil_media` of every mapped entity, found in Doctrine's metadata, and the records that hold a
 * medium in each. A column's deletion behaviour is the one configured for `Entity.field`, else the default; `set_null`
 * on a column that cannot be null is `restrict`.
 */
final readonly class DoctrineMediaReferences implements MediaReferences
{
    /**
     * @param array<string, DeleteBehaviour> $behaviours by `Entity.field`
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DeleteBehaviour $default = DeleteBehaviour::Restrict,
        private array $behaviours = [],
    ) {
    }

    #[Override]
    public function of(MediaId $id): array
    {
        $references = [];

        foreach ($this->columns() as [$entity, $field, $behaviour]) {
            $count = (int) $this->entityManager->createQuery(\sprintf('SELECT COUNT(e) FROM %s e WHERE e.%s = :id', $entity, $field))
                ->setParameter('id', $id, MediaType::NAME)
                ->getSingleScalarResult();

            if ($count > 0) {
                $references[] = new MediaReference($entity, $field, $count, $behaviour);
            }
        }

        return $references;
    }

    #[Override]
    public function detach(MediaId $id, MediaReference $reference): void
    {
        $this->entityManager->createQuery(\sprintf('UPDATE %s e SET e.%s = NULL WHERE e.%s = :id', $reference->entity, $reference->field, $reference->field))
            ->setParameter('id', $id, MediaType::NAME)
            ->execute();
    }

    /**
     * @return list<array{class-string, string, DeleteBehaviour}>
     */
    private function columns(): array
    {
        $columns = [];

        foreach ($this->entityManager->getMetadataFactory()->getAllMetadata() as $metadata) {
            if ($metadata->isMappedSuperclass || $metadata->isEmbeddedClass) {
                continue;
            }

            foreach ($metadata->fieldMappings as $field => $mapping) {
                if (MediaType::NAME === $mapping->type) {
                    $columns[] = [$metadata->getName(), $field, $this->behaviour($metadata, $field, true === $mapping->nullable)];
                }
            }
        }

        return $columns;
    }

    /**
     * @param ClassMetadata<object> $metadata
     */
    private function behaviour(ClassMetadata $metadata, string $field, bool $nullable): DeleteBehaviour
    {
        $behaviour = $this->behaviours[$metadata->getName().'.'.$field] ?? $this->default;

        return DeleteBehaviour::SetNull === $behaviour && !$nullable ? DeleteBehaviour::Restrict : $behaviour;
    }
}
