<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit;

use DateTimeImmutable;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Override;
use Phil\DoctrineBehaviours\Exception\InvalidTranslation;
use Phil\DoctrineBehaviours\FixedBlame;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Database;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\GadgetTranslation;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Recipe;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\RecipeTranslation;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Track;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\LooseTranslation;
use Phil\DoctrineBehaviours\TranslationListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(TranslationListener::class)]
#[CoversClass(InvalidTranslation::class)]
final class TranslationListenerTest extends TestCase
{
    private EntityManager $manager;

    #[Override]
    protected function setUp(): void
    {
        $this->manager = Database::entityManager(new MockClock(new DateTimeImmutable('2026-11-11 08:00')), new FixedBlame());
    }

    public function testATranslationTableGetsTheUniqueIndexOnItsRecordAndLocale(): void
    {
        $constraints = $this->manager->getClassMetadata(RecipeTranslation::class)->table['uniqueConstraints'] ?? [];

        self::assertSame(['columns' => ['recipe_id', 'locale']], $constraints['recipe_translation_translatable_locale'] ?? null);
        self::assertCount(2, $constraints, 'beside the index of the slug the entity declares');
    }

    public function testAnIndexTheEntityDeclaresOnTheSameColumnsIsKept(): void
    {
        self::assertSame(['gadget_locale' => ['columns' => ['locale', 'gadget_id']]], $this->manager->getClassMetadata(GadgetTranslation::class)->table['uniqueConstraints'] ?? []);
    }

    public function testAClassThatIsNoTranslationIsLeftAlone(): void
    {
        self::assertArrayNotHasKey('uniqueConstraints', $this->manager->getClassMetadata(Track::class)->table);
    }

    public function testAMappedSuperclassIsLeftAlone(): void
    {
        $metadata = $this->metadata();
        $metadata->isMappedSuperclass = true;

        $this->load($metadata);

        self::assertArrayNotHasKey('uniqueConstraints', $metadata->table);
    }

    public function testATranslationWithoutLocaleIsRefused(): void
    {
        $metadata = $this->metadata(locale: false);

        $this->expectException(InvalidTranslation::class);
        $this->expectExceptionMessageIsOrContains('The translation '.LooseTranslation::class.' maps no field "locale": use TranslationEntity.');

        $this->load($metadata);
    }

    public function testATranslationWithoutItsRecordIsRefused(): void
    {
        $metadata = $this->metadata(relation: false);

        $this->expectException(InvalidTranslation::class);
        $this->expectExceptionMessageIsOrContains('The translation '.LooseTranslation::class.' maps no to-one relation "translatable" held by one join column.');

        $this->load($metadata);
    }

    public function testATranslationOfARecordHeldByTwoColumnsIsRefused(): void
    {
        $metadata = $this->metadata(relation: false);
        $metadata->mapManyToOne(['fieldName' => 'translatable', 'targetEntity' => Recipe::class, 'joinColumns' => [['name' => 'a', 'referencedColumnName' => 'id'], ['name' => 'b', 'referencedColumnName' => 'servings']]]);

        $this->expectException(InvalidTranslation::class);

        $this->load($metadata);
    }

    public function testATranslationOfARecordHeldByAToManyRelationIsRefused(): void
    {
        $metadata = $this->metadata(relation: false);
        $metadata->mapManyToMany(['fieldName' => 'translatable', 'targetEntity' => Recipe::class]);

        $this->expectException(InvalidTranslation::class);

        $this->load($metadata);
    }

    public function testAnInheritedMappingIsLeftToTheClassThatMapsIt(): void
    {
        foreach (['locale', 'translatable'] as $inherited) {
            $metadata = $this->metadata();

            if ('locale' === $inherited) {
                $metadata->fieldMappings['locale']->inherited = Recipe::class;
            } else {
                $metadata->associationMappings['translatable']->inherited = Recipe::class;
            }

            $this->load($metadata);

            self::assertArrayNotHasKey('uniqueConstraints', $metadata->table, $inherited);
        }
    }

    public function testAnIndexDeclaredInTheOrderOfTheListenerIsKept(): void
    {
        $metadata = $this->metadata();
        $metadata->table['uniqueConstraints'] = ['mine' => ['columns' => ['recipe_id', 'locale']]];

        $this->load($metadata);

        self::assertSame(['mine' => ['columns' => ['recipe_id', 'locale']]], $metadata->table['uniqueConstraints'] ?? null, 'the join column sorts after the locale: both lists are sorted before they are compared');
    }

    public function testAnIndexOnOtherColumnsDoesNotCount(): void
    {
        $metadata = $this->metadata();
        $metadata->table['uniqueConstraints'] = ['other' => ['columns' => ['locale', 'text']], 'loose' => []];

        $this->load($metadata);

        self::assertSame(['other' => ['columns' => ['locale', 'text']], 'loose' => [], 'loose_translatable_locale' => ['columns' => ['recipe_id', 'locale']]], $metadata->table['uniqueConstraints'] ?? null);
    }

    public function testTheNameOfTheIndexFitsPostgreSql(): void
    {
        $metadata = $this->metadata(table: 'a.'.str_repeat('t', 80));

        $this->load($metadata);

        $names = array_keys($metadata->table['uniqueConstraints'] ?? []);
        self::assertSame([str_repeat('t', 43).'_translatable_locale'], $names);
        self::assertSame(63, \strlen($names[0]));
    }

    /**
     * @return ClassMetadata<LooseTranslation>
     */
    private function metadata(bool $locale = true, bool $relation = true, string $table = 'loose'): ClassMetadata
    {
        $metadata = new ClassMetadata(LooseTranslation::class);
        $metadata->initializeReflection($this->manager->getMetadataFactory()->getReflectionService());
        $metadata->setPrimaryTable(['name' => $table]);
        $metadata->mapField(['fieldName' => 'text', 'type' => 'string', 'id' => true]);

        if ($locale) {
            $metadata->mapField(['fieldName' => 'locale', 'type' => 'string']);
        }

        if ($relation) {
            $metadata->mapManyToOne(['fieldName' => 'translatable', 'targetEntity' => Recipe::class, 'joinColumns' => [['name' => 'recipe_id', 'referencedColumnName' => 'id']]]);
        }

        return $metadata;
    }

    /**
     * @param ClassMetadata<LooseTranslation> $metadata
     */
    private function load(ClassMetadata $metadata): void
    {
        new TranslationListener()->loadClassMetadata(new LoadClassMetadataEventArgs($metadata, $this->manager));
    }
}
