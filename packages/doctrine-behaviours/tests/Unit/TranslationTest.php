<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit;

use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManager;
use Override;
use Phil\DoctrineBehaviours\AllowVersionRestore;
use Phil\DoctrineBehaviours\Contract\Translatable;
use Phil\DoctrineBehaviours\Exception\InvalidScope;
use Phil\DoctrineBehaviours\Exception\InvalidTranslation;
use Phil\DoctrineBehaviours\FixedBlame;
use Phil\DoctrineBehaviours\SlugListener;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\CountingMiddleware;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Database;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Enigma;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Gadget;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\GadgetTranslation;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Recipe;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\RecipeTranslation;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Riddle;
use Phil\DoctrineBehaviours\TranslationEntity;
use Phil\DoctrineBehaviours\Translations;
use Phil\DoctrineBehaviours\TranslationSet;
use Phil\DoctrineBehaviours\Versions;
use Phil\LocaleDimensions\FallbackChain;
use Phil\LocaleDimensions\Locale;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Component\Clock\MockClock;

#[CoversClass(Translations::class)]
#[CoversClass(TranslationSet::class)]
#[CoversClass(InvalidTranslation::class)]
#[CoversClass(SlugListener::class)]
#[CoversClass(InvalidScope::class)]
#[CoversTrait(TranslationEntity::class)]
final class TranslationTest extends TestCase
{
    private EntityManager $manager;

    private Translations $translations;

    private CountingMiddleware $queries;

    #[Override]
    protected function setUp(): void
    {
        $this->queries = new CountingMiddleware();
        $this->manager = Database::entityManager(new MockClock(new DateTimeImmutable('2026-11-11 08:00')), new FixedBlame(), middleware: $this->queries);
        $this->translations = new Translations($this->manager);
    }

    public function testATranslationIsCreatedEmptyAndStoredAtTheNextFlush(): void
    {
        $recipe = $this->recipe('crepes');
        $french = $this->translations->translate($recipe, $this->locale('fr'));

        self::assertInstanceOf(RecipeTranslation::class, $french);
        self::assertSame(['title' => '', 'instructions' => null], $this->translations->values($french));
        self::assertSame($french, $this->translations->translate($recipe, $this->locale('fr')), 'a pending translation is found again');
        self::assertTrue($this->manager->getUnitOfWork()->isScheduledForInsert($french));

        $french->title = 'Crêpes';
        $this->manager->flush();
        $this->manager->clear();

        $recipe = $this->manager->find(Recipe::class, 'crepes');
        self::assertInstanceOf(Recipe::class, $recipe);
        $stored = $this->translations->translate($recipe, $this->locale('fr'));
        self::assertSame('Crêpes', 'fr' === $stored->locale()->value && $stored instanceof RecipeTranslation ? $stored->title : null);
        self::assertFalse($this->manager->getUnitOfWork()->isScheduledForInsert($stored), 'the stored row, not a new one');
    }

    public function testWritingNeverCopiesTheFallback(): void
    {
        $recipe = $this->recipe('crepes');
        $this->translated($recipe, 'fr', 'Crêpes', 'Mélanger.');
        $this->manager->flush();

        $canadian = $this->translations->translate($recipe, $this->locale('fr_CA'));

        self::assertSame(['title' => '', 'instructions' => null], $this->translations->values($canadian));
        self::assertSame('fr_CA', $canadian->locale()->value);
    }

    public function testTheFirstLocaleOfTheChainThatHasARowIsServedWhole(): void
    {
        $recipe = $this->recipe('crepes');
        $this->translated($recipe, 'fr', 'Crêpes', null);
        $this->translated($recipe, 'en', 'Pancakes', 'Mix.');
        $this->manager->flush();
        $this->manager->clear();

        $recipe = $this->manager->find(Recipe::class, 'crepes');
        self::assertInstanceOf(Recipe::class, $recipe);

        $served = $this->translations->find($recipe, $this->chain('fr_CA', 'fr', 'en'));

        self::assertInstanceOf(RecipeTranslation::class, $served);
        self::assertSame(['title' => 'Crêpes', 'instructions' => null], $this->translations->values($served), 'the French row, its empty field not filled from English');
        self::assertSame('en', $this->translations->find($recipe, $this->chain('de', 'en'))?->locale()->value);
        self::assertSame('en', $this->translations->find($recipe, $this->chain('en', 'fr'))?->locale()->value, 'in the order of the chain');
        self::assertNull($this->translations->find($recipe, $this->chain('de')), 'a chain that runs out serves nothing');
        self::assertNull($this->translations->get($recipe, $this->locale('fr_CA')));
        self::assertSame('fr', $this->translations->get($recipe, $this->locale('fr'))?->locale()->value);
    }

    public function testOnlyTheLocalesOfTheChainAreRead(): void
    {
        $recipe = $this->recipe('crepes');
        $this->translated($recipe, 'fr', 'Crêpes', null);
        $this->translated($recipe, 'en', 'Pancakes', null);
        $this->translated($recipe, 'de', 'Pfannkuchen', null);
        $this->manager->flush();
        $this->manager->clear();

        $recipe = $this->stored(Recipe::class, 'crepes');

        self::assertSame('en', $this->translations->find($recipe, $this->chain('en'))?->locale()->value);

        $read = $this->manager->getUnitOfWork()->getIdentityMap()[RecipeTranslation::class] ?? [];
        self::assertSame(['crepes/en'], array_map(static fn (object $translation): string => $translation instanceof RecipeTranslation ? $translation->id : '', array_values($read)), 'the other locales stay in the database');
    }

    public function testASubclassOfATranslationMaySetItsLocale(): void
    {
        self::assertTrue(new ReflectionMethod(TranslationEntity::class, 'writtenIn')->isProtected(), 'an abstract translation leaves its constructor to its subclasses');
    }

    public function testTheLocalesOfARecordAreListedInOrder(): void
    {
        $recipe = $this->recipe('crepes');
        $this->translated($recipe, 'fr', 'Crêpes', null);
        $this->translated($recipe, 'en', 'Pancakes', null);
        $this->manager->flush();
        $this->translations->translate($recipe, $this->locale('de'));

        self::assertSame(['de', 'en', 'fr'], array_map(static fn (Locale $locale): string => $locale->value, $this->translations->locales($recipe)), 'a pending translation counts');
        self::assertSame([], $this->translations->locales($this->recipe('waffles')));
    }

    public function testATranslationScheduledForRemovalIsNotServed(): void
    {
        $recipe = $this->recipe('crepes');
        $french = $this->translated($recipe, 'fr', 'Crêpes', null);
        $this->translated($recipe, 'en', 'Pancakes', null);
        $this->manager->flush();

        $this->manager->remove($french);

        self::assertSame('en', $this->translations->find($recipe, $this->chain('fr', 'en'))?->locale()->value);
        self::assertSame(['en'], array_map(static fn (Locale $locale): string => $locale->value, $this->translations->locales($recipe)));
    }

    public function testAPendingTranslationOfAnotherRecordOrLocaleDoesNotCount(): void
    {
        $recipe = $this->recipe('crepes');
        $other = $this->recipe('waffles');
        $this->translations->translate($other, $this->locale('fr'));
        $this->translations->translate($recipe, $this->locale('en'));

        self::assertNull($this->translations->find($recipe, $this->chain('fr')));
        self::assertNull($this->translations->get($recipe, $this->locale('de')));
    }

    public function testAListIsReadInOneQueryPerTranslationClass(): void
    {
        $recipes = [$this->recipe('crepes'), $this->recipe('waffles'), $this->recipe('scones')];
        $gadget = new Gadget('whisk');
        $this->manager->persist($gadget);
        $this->translated($recipes[0], 'fr', 'Crêpes', null);
        $this->translated($recipes[0], 'en', 'Pancakes', null);
        $this->translated($recipes[1], 'en', 'Waffles', null);
        $whisk = new GadgetTranslation($gadget, $this->locale('fr'));
        $whisk->name = 'Fouet';

        $this->manager->persist($whisk);
        $this->manager->flush();
        $this->manager->clear();

        $loaded = [...array_map(fn (Recipe $recipe): Recipe => $this->stored(Recipe::class, $recipe->id), $recipes), $this->stored(Gadget::class, 'whisk')];
        $this->queries->forget();

        $set = $this->translations->preload($loaded, $this->chain('fr', 'en'));

        self::assertCount(2, $this->queries->selects(), 'one query for the recipes, one for the gadgets');
        self::assertSame('fr', $set->of($loaded[0])?->locale()->value);
        self::assertSame('en', $set->of($loaded[1])?->locale()->value);
        self::assertNull($set->of($loaded[2]));
        self::assertSame('fr', $set->of($loaded[3])?->locale()->value);
        self::assertNull($set->of($this->recipe('muffins')), 'a record that was not read');
    }

    public function testALongListIsReadInChunks(): void
    {
        $recipes = [];

        for ($i = 0; $i <= Translations::CHUNK; ++$i) {
            $recipes[] = $recipe = $this->recipe('r'.$i);
            $this->translated($recipe, 'fr', 'Recette '.$i, null);
        }

        $this->manager->flush();
        $this->queries->forget();

        $set = $this->translations->preload($recipes, $this->chain('fr'));

        self::assertCount(2, $this->queries->selects());
        self::assertSame('Recette 500', $set->of($recipes[Translations::CHUNK]) instanceof RecipeTranslation ? $this->translations->values($set->of($recipes[Translations::CHUNK]))['title'] : null);
        self::assertSame('fr', $set->of($recipes[0])?->locale()->value);
    }

    public function testARecordNotStoredYetReadsNothingFromTheDatabase(): void
    {
        $recipe = new Recipe('crepes');
        $this->manager->persist($recipe);
        $this->queries->forget();

        self::assertNull($this->translations->find($recipe, $this->chain('fr')));
        self::assertSame([], $this->queries->selects());
    }

    public function testASetAnswersForTheRecordItWasReadFor(): void
    {
        $recipe = $this->recipe('crepes');
        $other = $this->recipe('waffles');
        $french = $this->translated($recipe, 'fr', 'Crêpes', null);

        self::assertNull(new TranslationSet([spl_object_id($other) => $french])->of($other));
        self::assertSame($french, new TranslationSet([spl_object_id($recipe) => $french])->of($recipe));
    }

    public function testOneRowPerRecordAndLocale(): void
    {
        $recipe = $this->recipe('crepes');
        $this->translated($recipe, 'fr', 'Crêpes', null);
        $this->manager->flush();

        $this->expectException(UniqueConstraintViolationException::class);
        $this->manager->getConnection()->executeStatement("INSERT INTO recipe_translation (id, recipe_id, locale, title, version_number, version_frozen) VALUES ('other', 'crepes', 'fr', 'Crêpes', 1, 0)");
    }

    public function testEachLocaleHasItsOwnSlugs(): void
    {
        $crepes = $this->recipe('crepes');
        $pancakes = $this->recipe('pancakes');
        $french = $this->translated($crepes, 'fr', 'Crêpes', null);
        $english = $this->translated($crepes, 'en', 'Crêpes', null);
        $again = $this->translated($pancakes, 'fr', 'Crêpes', null);
        $this->manager->flush();

        self::assertSame(['crepes', 'crepes', 'crepes-2'], [$french->slug(), $english->slug(), $again->slug()]);
    }

    public function testATranslationSlugIsScopedByItsLocale(): void
    {
        $french = $this->translated($this->recipe('crepes'), 'fr', 'Crêpes', null);
        $french->scope = [];

        $this->expectException(InvalidScope::class);
        $this->expectExceptionMessageIsOrContains('name "locale" in slugScope()');

        $this->manager->flush();
    }

    public function testEachLocaleHasItsOwnVersions(): void
    {
        $recipe = $this->recipe('crepes');
        $french = $this->translated($recipe, 'fr', 'Crêpes', null);
        $english = $this->translated($recipe, 'en', 'Pancakes', null);
        $this->manager->flush();
        $versions = new Versions($this->manager, new MockClock(new DateTimeImmutable('2026-11-11 09:00')), new AllowVersionRestore());

        $versions->freeze($french);

        $french->title = 'Crêpes fines';
        $english->title = 'Thin pancakes';
        $this->manager->flush();

        self::assertSame([2, 1], [$french->versionNumber(), $english->versionNumber()]);
        self::assertSame('Crêpes', $versions->get($french, 1)?->state()['title'] ?? null);
    }

    public function testATranslationMadeForAnotherRecordClassOrLocaleIsRefused(): void
    {
        $gadget = new Gadget('whisk');
        $other = new Gadget('spoon');
        $this->manager->persist($gadget);
        $this->manager->persist($other);

        $recipe = $this->recipe('crepes');
        $refused = 0;

        foreach ([new GadgetTranslation($other, $this->locale('fr')), new GadgetTranslation($gadget, $this->locale('en')), new RecipeTranslation($recipe, $this->locale('fr'))] as $wrong) {
            $gadget->next = $wrong;

            try {
                $this->translations->translate($gadget, $this->locale('fr'));
            } catch (InvalidTranslation $invalid) {
                self::assertStringContainsString('Gadget::newTranslation() made a translation of another record, class or locale than its own in "fr"', $invalid->getMessage());
                ++$refused;
            }
        }

        self::assertSame(3, $refused);
        $gadget->next = null;
        self::assertInstanceOf(GadgetTranslation::class, $this->translations->translate($gadget, $this->locale('fr')));
    }

    public function testAFieldTheTranslationDoesNotMapIsRefused(): void
    {
        $gadget = new Gadget('whisk');
        $this->manager->persist($gadget);
        $translation = $this->translations->translate($gadget, $this->locale('fr'));

        $this->expectException(InvalidTranslation::class);
        $this->expectExceptionMessageIsOrContains('names "colour" as a translated field, which '.GadgetTranslation::class.' does not map.');

        $this->translations->values($translation);
    }

    public function testATranslationClassThatIsNoTranslationEntityIsRefused(): void
    {
        foreach ([new Riddle('sphinx'), new Enigma('oracle')] as $record) {
            $this->manager->persist($record);
            $this->manager->flush();

            try {
                $this->translations->find($record, $this->chain('fr'));
                self::fail('A translation class that is not a translation entity is refused.');
            } catch (InvalidTranslation $invalid) {
                self::assertSame(\sprintf('%s names %s as its translation, which is not an entity implementing Translation.', $record::class, $record::translationClass()), $invalid->getMessage());
            }
        }
    }

    private function recipe(string $id): Recipe
    {
        $recipe = new Recipe($id);
        $this->manager->persist($recipe);

        return $recipe;
    }

    private function translated(Recipe $recipe, string $locale, string $title, ?string $instructions): RecipeTranslation
    {
        $translation = $recipe->newTranslation($this->locale($locale));
        $translation->title = $title;
        $translation->instructions = $instructions;

        $this->manager->persist($translation);

        return $translation;
    }

    /**
     * @template T of Translatable
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function stored(string $class, string $id): Translatable
    {
        $record = $this->manager->find($class, $id);
        self::assertInstanceOf($class, $record);

        return $record;
    }

    private function locale(string $value): Locale
    {
        return Locale::fromString($value);
    }

    private function chain(string ...$locales): FallbackChain
    {
        return FallbackChain::of(array_map(Locale::fromString(...), array_values($locales)));
    }
}
