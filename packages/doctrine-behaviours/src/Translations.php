<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours;

use Doctrine\ORM\EntityManagerInterface;
use Phil\DoctrineBehaviours\Contract\Translatable;
use Phil\DoctrineBehaviours\Contract\Translation;
use Phil\DoctrineBehaviours\Exception\InvalidTranslation;
use Phil\LocaleDimensions\FallbackChain;
use Phil\LocaleDimensions\Locale;

/**
 * Reads and writes the translations of `Translatable` records.
 *
 * **Reading walks a fallback chain, row by row.** The translation served is the row of the first locale of the chain
 * that has one, whole: its fields are never mixed with those of another locale. A list of records is read in one query
 * per translation class and chunk of records, never one per record. Translations persisted and not flushed count, and
 * those scheduled for removal do not.
 *
 * **Writing never copies.** `translate()` gives the row of exactly that locale, or a new, empty one; the fallback the
 * reader would have been served is not copied into it, so that a translation nobody wrote never passes for one.
 */
final readonly class Translations
{
    /**
     * The records read by one query, so that the list of their identifiers stays within what every platform binds.
     */
    public const int CHUNK = 500;

    public function __construct(
        private EntityManagerInterface $manager,
    ) {
    }

    /**
     * The translation of the record in the first locale of the chain that has one, or null.
     */
    public function find(Translatable $record, FallbackChain $chain): ?Translation
    {
        return $this->preload([$record], $chain)->of($record);
    }

    /**
     * The translation of the record in exactly this locale, or null.
     */
    public function get(Translatable $record, Locale $locale): ?Translation
    {
        return $this->find($record, FallbackChain::of([$locale]));
    }

    /**
     * The translation of the record in this locale, to be written: the stored one, or a new, empty one, persisted for
     * the next flush.
     *
     * @throws InvalidTranslation when the record makes a translation that is not its own in this locale
     */
    public function translate(Translatable $record, Locale $locale): Translation
    {
        $translation = $this->get($record, $locale);

        if ($translation instanceof Translation) {
            return $translation;
        }

        $translation = $record->newTranslation($locale);

        if (!is_a($translation, $record::translationClass()) || $translation->translatable() !== $record || !$translation->locale()->equals($locale)) {
            throw InvalidTranslation::foreign($record::class, $locale->value);
        }

        $this->manager->persist($translation);

        return $translation;
    }

    /**
     * The locales the record is translated in, in the order of their values: what `hreflang` alternates list.
     *
     * @return list<Locale>
     */
    public function locales(Translatable $record): array
    {
        $locales = [];

        foreach ($this->rows([$record], null) as $translation) {
            $locales[$translation->locale()->value] = $translation->locale();
        }

        ksort($locales, \SORT_STRING);

        return array_values($locales);
    }

    /**
     * The translations of many records along one chain, read before a list is shown.
     *
     * @param iterable<Translatable> $records
     */
    public function preload(iterable $records, FallbackChain $chain): TranslationSet
    {
        $list = [];

        foreach ($records as $record) {
            $list[] = $record;
        }

        $found = [];

        foreach ($this->rows($list, $chain) as $translation) {
            $found[spl_object_id($translation->translatable())][$translation->locale()->value] = $translation;
        }

        $chosen = [];

        foreach ($found as $record => $byLocale) {
            $locale = $chain->resolve(array_map(static fn (Translation $translation): Locale => $translation->locale(), $byLocale));

            if ($locale instanceof Locale) {
                $chosen[$record] = $byLocale[$locale->value];
            }
        }

        return new TranslationSet($chosen);
    }

    /**
     * The translated fields of a translation, by name, in the order the record lists them.
     *
     * @return array<string, mixed>
     *
     * @throws InvalidTranslation when the record names a field its translation class does not map
     */
    public function values(Translation $translation): array
    {
        $record = $translation->translatable();
        $metadata = $this->manager->getClassMetadata($translation::class);
        $values = [];

        foreach ($record::translatedFields() as $field) {
            if (!$metadata->hasField($field)) {
                throw InvalidTranslation::unknownField($record::class, $translation::class, $field);
            }

            $values[$field] = $metadata->getFieldValue($translation, $field);
        }

        return $values;
    }

    /**
     * The rows of the records in the locales of the chain, every locale without one: the stored ones, then those
     * persisted and not flushed.
     *
     * @param list<Translatable> $records
     *
     * @return list<Translation>
     */
    private function rows(array $records, ?FallbackChain $chain): array
    {
        $work = $this->manager->getUnitOfWork();
        $locales = $chain instanceof FallbackChain ? array_map(static fn (Locale $locale): string => $locale->value, $chain->locales) : null;
        $wanted = [];
        $stored = [];

        foreach ($records as $record) {
            $wanted[spl_object_id($record)] = $record;

            if ($work->isInIdentityMap($record) && !$work->isScheduledForInsert($record)) {
                $stored[$record::translationClass()][] = $record;
            }
        }

        $rows = [];

        foreach ($stored as $class => $byClass) {
            $this->checkClass($byClass[0], $class);

            foreach (array_chunk($byClass, self::CHUNK) as $chunk) {
                $query = $this->manager->createQueryBuilder()
                    ->select('t')
                    ->from($class, 't')
                    ->where(\sprintf('t.%s IN (:records)', Translation::TRANSLATABLE_FIELD))
                    ->setParameter('records', $chunk);

                if (null !== $locales) {
                    $query->andWhere(\sprintf('t.%s IN (:locales)', Translation::LOCALE_FIELD))->setParameter('locales', $locales);
                }

                /** @var list<Translation> $result */
                $result = $query->getQuery()->getResult();

                foreach ($result as $translation) {
                    if (!$work->isScheduledForDelete($translation)) {
                        $rows[] = $translation;
                    }
                }
            }
        }

        foreach ($work->getScheduledEntityInsertions() as $pending) {
            if ($pending instanceof Translation && isset($wanted[spl_object_id($pending->translatable())]) && (null === $locales || \in_array($pending->locale()->value, $locales, true))) {
                $rows[] = $pending;
            }
        }

        return $rows;
    }

    /**
     * @throws InvalidTranslation when the class is not an entity implementing `Translation`
     */
    private function checkClass(Translatable $record, string $class): void
    {
        if (!is_a($class, Translation::class, true) || $this->manager->getMetadataFactory()->isTransient($class)) {
            throw InvalidTranslation::notATranslation($record::class, $class);
        }
    }
}
