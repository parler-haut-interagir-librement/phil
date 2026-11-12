<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Infrastructure\Doctrine;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ToOneOwningSideMapping;
use Phil\ContentBundle\Domain\Content\AbstractDimensionContent;
use Phil\ContentBundle\Infrastructure\Doctrine\Mapping\CapabilityMapping;

/**
 * Completes the mapping of content classes when Doctrine loads them (`loadClassMetadata`).
 *
 * - **Every capability a class implements** gets its columns, from the `CapabilityMapping` of that capability.
 * - **Every concrete row class** gets its `root` association, towards the declared root that names it, with
 *   a `root_id` join column that cannot be null and is deleted with its root; and one unique constraint,
 *   `(root_id, stage, version, locale_key)`. A class that maps `root` itself keeps its association, and the
 *   constraint names its join column instead of `root_id`. The order of the columns is chosen for the three lookups of the
 *   repository: a row by its whole key, the rows of one stage and version, and the highest version of one
 *   stage all read a prefix of that one index.
 *
 * Mapping added here is mapping the application does not write: a row class declares its capabilities by
 * implementing their interfaces, and nothing else.
 */
final readonly class DimensionContentMappingListener
{
    public const string ROOT_FIELD = 'root';

    public const string ROOT_COLUMN = 'root_id';

    /**
     * The columns of the unique dimension after the root's join column.
     *
     * @var list<string>
     */
    public const array DIMENSION_COLUMNS = ['stage', 'version', 'locale_key'];

    /**
     * @var list<string>
     */
    public const array UNIQUE_COLUMNS = [self::ROOT_COLUMN, ...self::DIMENSION_COLUMNS];

    /**
     * @var array<CapabilityMapping>
     */
    private array $capabilities;

    /**
     * @param iterable<CapabilityMapping> $capabilities read once, here: a generator cannot be walked for a second class
     */
    public function __construct(
        private ContentRoots $roots,
        iterable $capabilities,
    ) {
        $this->capabilities = [...$capabilities];
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $event): void
    {
        $metadata = $event->getClassMetadata();

        foreach ($this->capabilities as $capability) {
            if (is_a($metadata->getName(), $capability->capability(), true)) {
                $capability->map($metadata);
            }
        }

        $class = $metadata->getName();

        if ($metadata->isMappedSuperclass || !is_subclass_of($class, AbstractDimensionContent::class)) {
            return;
        }

        $this->mapRoot($metadata, $this->roots->rootOf($class));
        $this->addUniqueDimension($metadata);
    }

    /**
     * @param ClassMetadata<object> $metadata
     * @param class-string          $root
     */
    private function mapRoot(ClassMetadata $metadata, string $root): void
    {
        if ($metadata->hasAssociation(self::ROOT_FIELD)) {
            return;
        }

        $metadata->mapManyToOne([
            'fieldName' => self::ROOT_FIELD,
            'targetEntity' => $root,
            'joinColumns' => [[
                'name' => self::ROOT_COLUMN,
                'referencedColumnName' => 'id',
                'nullable' => false,
                'onDelete' => 'CASCADE',
            ]],
        ]);
    }

    /**
     * The join column of the `root` association: `root_id`, or the column of an association the class maps
     * itself.
     *
     * @param ClassMetadata<object> $metadata
     *
     * @throws InvalidContentMapping when the class maps `root` without a single join column
     */
    private function rootColumn(ClassMetadata $metadata): string
    {
        $association = $metadata->getAssociationMapping(self::ROOT_FIELD);
        $joinColumns = $association instanceof ToOneOwningSideMapping ? $association->joinColumns : [];

        if (1 !== \count($joinColumns)) {
            throw InvalidContentMapping::because(\sprintf('The row class "%s" maps its "%s" association without a single join column.', $metadata->getName(), self::ROOT_FIELD));
        }

        return $joinColumns[0]->name;
    }

    /**
     * Unnamed: Doctrine derives a name from the table and the columns, which keeps it unique across a
     * PostgreSQL schema, where index names are not scoped to their table.
     *
     * @param ClassMetadata<object> $metadata
     */
    private function addUniqueDimension(ClassMetadata $metadata): void
    {
        $columns = [$this->rootColumn($metadata), ...self::DIMENSION_COLUMNS];
        $constraints = $metadata->table['uniqueConstraints'] ?? [];

        foreach ($constraints as $constraint) {
            if (\is_array($constraint) && ($constraint['columns'] ?? null) === $columns) {
                return;
            }
        }

        $constraints[] = ['columns' => $columns];
        $metadata->table['uniqueConstraints'] = $constraints;
    }
}
