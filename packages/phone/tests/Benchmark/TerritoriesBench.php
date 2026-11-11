<?php

declare(strict_types=1);

namespace Phil\Phone\Tests\Benchmark;

use Generator;
use Phil\Phone\Metadata\Territories;

/**
 * Every `parse()` given a region and every country check looks the region up in the sorted list of about 250
 * countries, so the lookup is paid once per phone field rendered or validated.
 *
 * @BeforeMethods("setUp")
 *
 * @Groups({"phone"})
 */
final class TerritoriesBench
{
    /**
     * Warms the list and the plan once, as any process does after its first number.
     *
     * @param array{region?: string} $params
     */
    public function setUp(array $params): void
    {
        Territories::region($params['region'] ?? 'FR');
    }

    public function benchRegions(): void
    {
        Territories::regions();
    }

    /**
     * @ParamProviders("regions")
     *
     * @param array{region: string} $params
     */
    public function benchRegion(array $params): void
    {
        Territories::region($params['region']);
    }

    /**
     * @return Generator<string, array{region: string}>
     */
    public function regions(): Generator
    {
        yield 'AD (first)' => ['region' => 'AD'];
        yield 'fr (lower case, middle)' => ['region' => 'fr'];
        yield 'ZW (last)' => ['region' => 'ZW'];
    }
}
