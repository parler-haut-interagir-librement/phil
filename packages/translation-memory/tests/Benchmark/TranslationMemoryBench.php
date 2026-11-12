<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Tests\Benchmark;

use Generator;
use Phil\TranslationMemory\LanguageTag;
use Phil\TranslationMemory\Memory\InMemoryStore;
use Phil\TranslationMemory\Memory\MemoryUnit;
use Phil\TranslationMemory\Memory\Similarity;
use Phil\TranslationMemory\Memory\TranslationMemory;

/**
 * Each segment a translator opens is looked up in the memory: the units of a close length are sorted by trigrams and
 * the best fifty scored by their distance. Memories of 100, 1 000 and 10 000 units, and the distance of long segments
 * near and far from each other, are measured.
 *
 * @BeforeMethods("setUp")
 */
final class TranslationMemoryBench
{
    private TranslationMemory $memory;

    private readonly LanguageTag $english;

    private readonly LanguageTag $french;

    private string $near = '';

    private string $far = '';

    public function __construct()
    {
        $this->english = LanguageTag::of('en');
        $this->french = LanguageTag::of('fr');
        $this->memory = new TranslationMemory(new InMemoryStore());
    }

    /**
     * @param array{units: int, length: int} $params
     */
    public function setUp(array $params): void
    {
        $units = [];

        for ($i = 0; $i < $params['units']; ++$i) {
            $units[] = new MemoryUnit($this->english, $this->french, \sprintf('The goblin number %d hides behind the old door of the tower.', $i), \sprintf('Le gobelin numéro %d se cache derrière la vieille porte de la tour.', $i));
        }

        $this->memory = new TranslationMemory(new InMemoryStore($units));
        $this->near = str_repeat('The goblin hides behind the old door. ', intdiv($params['length'], 38) + 1);
        $this->far = str_repeat('A dragon sleeps under the high hill! ', intdiv($params['length'], 37) + 1);
    }

    /**
     * @ParamProviders("memories")
     *
     * @Revs(5)
     *
     * @Iterations(3)
     */
    public function benchLookup(): void
    {
        $this->memory->lookup('The goblin number 7 hides behind the new door of the tower.', $this->english, $this->french);
    }

    /**
     * @ParamProviders("lengths")
     *
     * @Revs(5)
     *
     * @Iterations(3)
     */
    public function benchScoreNear(): void
    {
        Similarity::score($this->near, $this->near.'x', 0.7);
    }

    /**
     * @ParamProviders("lengths")
     *
     * @Revs(5)
     *
     * @Iterations(3)
     */
    public function benchScoreFar(): void
    {
        Similarity::score($this->near, $this->far, 0.7);
    }

    /**
     * @return Generator<string, array{units: int, length: int}>
     */
    public function memories(): Generator
    {
        foreach ([100, 1_000, 10_000] as $units) {
            yield \sprintf('%d units', $units) => ['units' => $units, 'length' => 0];
        }
    }

    /**
     * @return Generator<string, array{units: int, length: int}>
     */
    public function lengths(): Generator
    {
        foreach ([100, 1_000] as $length) {
            yield \sprintf('%d characters', $length) => ['units' => 0, 'length' => $length];
        }
    }
}
