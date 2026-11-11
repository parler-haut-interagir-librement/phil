<?php

declare(strict_types=1);

namespace Phil\Address\Tests\Benchmark;

use Generator;
use Phil\Address\Subdivisions;

/**
 * Validation and formatting look a subdivision up at each level by a linear scan that lowercases up to six names per
 * candidate, so a state with hundreds of cities pays it on every address.
 */
final class SubdivisionsBench
{
    public function __construct()
    {
        // Read the data files once, as every request after the first finds them in memory.
        Subdivisions::of('CA');
        Subdivisions::of('BR', 'MG');
        Subdivisions::of('CN', 'GD');
    }

    /**
     * @ParamProviders("lookups")
     *
     * @param array{value: string, country: string, parents: list<string>} $params
     */
    public function benchFind(array $params): void
    {
        Subdivisions::find($params['value'], $params['country'], ...$params['parents']);
    }

    /**
     * @return Generator<string, array{value: string, country: string, parents: list<string>}>
     */
    public function lookups(): Generator
    {
        yield 'CA, first of 13' => ['value' => 'AB', 'country' => 'CA', 'parents' => []];
        yield 'CA, last of 13 by name' => ['value' => 'Yukon', 'country' => 'CA', 'parents' => []];
        yield 'CN-GD, last of 21 by local name' => ['value' => '珠海市', 'country' => 'CN', 'parents' => ['GD']];
        yield 'BR-MG, last of 853' => ['value' => 'Wenceslau Braz', 'country' => 'BR', 'parents' => ['MG']];
        yield 'BR-MG, unknown of 853' => ['value' => 'Atlantis', 'country' => 'BR', 'parents' => ['MG']];
    }
}
