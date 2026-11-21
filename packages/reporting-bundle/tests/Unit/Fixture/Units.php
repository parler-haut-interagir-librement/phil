<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Tests\Unit\Fixture;

use Override;
use Phil\ReportingBundle\Application\Contract\UnitDirectory;
use Phil\ReportingBundle\Domain\Training\UnitNode;

/**
 * A tree of units — the company, its two divisions, the teams of one —, which counts the paths it is asked for.
 *
 *     hq Company
 *     ├── ops Operations
 *     │   ├── t2 Support
 *     │   └── t1 Field
 *     └── sales Sales
 */
final class Units implements UnitDirectory
{
    /** @var array<string, int> */
    public array $asked = [];

    /** @var array<string, array{string, ?string}> */
    private const array TREE = [
        'hq' => ['Company', null],
        'sales' => ['Sales', 'hq'],
        'ops' => ['Operations', 'hq'],
        't1' => ['Field', 'ops'],
        't2' => ['Support', 'ops'],
        'twin-b' => ['Twin', 'hq'],
        'twin-a' => ['Twin', 'hq'],
    ];

    #[Override]
    public function path(string $unit): array
    {
        $this->asked[$unit] = ($this->asked[$unit] ?? 0) + 1;
        $path = [];

        for ($id = $unit; null !== $id && isset(self::TREE[$id]); $id = self::TREE[$id][1]) {
            array_unshift($path, new UnitNode($id, self::TREE[$id][0]));
        }

        return $path;
    }
}
