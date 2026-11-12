<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Benchmark;

use DateTimeImmutable;
use Doctrine\ORM\EntityManager;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Database;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Recipe;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\MutableBlame;
use Phil\DoctrineBehaviours\Translations;
use Phil\LocaleDimensions\FallbackChain;
use Phil\LocaleDimensions\Locale;
use Symfony\Component\Clock\MockClock;

/**
 * The translations of a list of 100 records read along a chain of three locales, one query for the list; and the
 * translation of one record. Half the records have a French row, all have an English one.
 *
 * @Groups({"doctrine"})
 */
final readonly class TranslationBench
{
    private const int RECORDS = 100;

    private EntityManager $manager;

    private Translations $translations;

    private FallbackChain $chain;

    /** @var list<Recipe> */
    private array $recipes;

    public function __construct()
    {
        $this->manager = Database::entityManager(new MockClock(new DateTimeImmutable('2026-11-11 12:00 UTC')), new MutableBlame());
        $connection = $this->manager->getConnection();

        for ($number = 1; $number <= self::RECORDS; ++$number) {
            $connection->executeStatement('INSERT INTO recipe (id, servings) VALUES (?, 4)', ['r'.$number]);
            $connection->executeStatement("INSERT INTO recipe_translation (id, recipe_id, locale, title, version_number, version_frozen) VALUES (?, ?, 'en', ?, 1, 0)", ['r'.$number.'/en', 'r'.$number, 'Recipe '.$number]);

            if (0 === $number % 2) {
                $connection->executeStatement("INSERT INTO recipe_translation (id, recipe_id, locale, title, version_number, version_frozen) VALUES (?, ?, 'fr', ?, 1, 0)", ['r'.$number.'/fr', 'r'.$number, 'Recette '.$number]);
            }
        }

        $this->translations = new Translations($this->manager);
        $this->chain = FallbackChain::of([Locale::fromString('fr_CA'), Locale::fromString('fr'), Locale::fromString('en')]);
        /** @var list<Recipe> $recipes */
        $recipes = $this->manager->getRepository(Recipe::class)->findAll();
        $this->recipes = $recipes;
    }

    /**
     * @Revs(50)
     *
     * @Iterations(5)
     */
    public function benchPreloadAList(): void
    {
        $this->translations->preload($this->recipes, $this->chain);
    }

    /**
     * @Revs(200)
     *
     * @Iterations(5)
     */
    public function benchFindOne(): void
    {
        $this->translations->find($this->recipes[0], $this->chain);
    }
}
