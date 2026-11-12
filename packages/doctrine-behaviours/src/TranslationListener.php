<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Phil\DoctrineBehaviours\Contract\Translation;
use Phil\DoctrineBehaviours\Exception\InvalidTranslation;

/**
 * Checks the mapping of each `Translation` entity as it is loaded, and gives its table the unique index on the join
 * column of `translatable` and the column `locale`: one row per record and locale, the only thing that holds when two
 * requests translate the same record at once. An index the entity declares on the same columns is kept as it is.
 * A subclass of a translation entity inherits both from the class that maps them.
 */
final readonly class TranslationListener
{
    /**
     * PostgreSQL cuts identifiers at 63 bytes.
     */
    private const int MAX_INDEX_NAME = 63;

    public function loadClassMetadata(LoadClassMetadataEventArgs $event): void
    {
        $metadata = $event->getClassMetadata();
        $class = $metadata->getName();

        if (!is_a($class, Translation::class, true) || $metadata->isMappedSuperclass || $metadata->isEmbeddedClass) {
            return;
        }

        if (!$metadata->hasField(Translation::LOCALE_FIELD)) {
            throw InvalidTranslation::noLocale($class);
        }

        if (!$metadata->hasAssociation(Translation::TRANSLATABLE_FIELD) || !$metadata->isAssociationWithSingleJoinColumn(Translation::TRANSLATABLE_FIELD)) {
            throw InvalidTranslation::noTranslatable($class);
        }

        if ($metadata->isInheritedField(Translation::LOCALE_FIELD) || $metadata->isInheritedAssociation(Translation::TRANSLATABLE_FIELD)) {
            return;
        }

        $columns = [$metadata->getSingleAssociationJoinColumnName(Translation::TRANSLATABLE_FIELD), $metadata->getColumnName(Translation::LOCALE_FIELD)];

        $wanted = $columns;
        sort($wanted);

        foreach ($metadata->table['uniqueConstraints'] ?? [] as $constraint) {
            $declared = \is_array($constraint) && \is_array($constraint['columns'] ?? null) ? $constraint['columns'] : [];
            sort($declared);

            if ($declared === $wanted) {
                return;
            }
        }

        $name = substr(preg_replace('/\W/', '_', $metadata->getTableName()).'_translatable_locale', -self::MAX_INDEX_NAME);
        $metadata->table['uniqueConstraints'][$name] = ['columns' => $columns];
    }
}
