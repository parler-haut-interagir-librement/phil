<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Benchmark;

use Phil\PrivacyBundle\Domain\Consent\ConsentCategory;
use Phil\PrivacyBundle\Domain\Consent\ConsentPolicy;
use Phil\PrivacyBundle\Domain\Cookie\CookieInventory;

/**
 * What a page with the banner or a blocked frame runs: the inventory by category, the names a response sets checked
 * against forty declared lines, and a frame's address read.
 *
 * @Groups({"privacy", "cookies"})
 */
final readonly class CookieInventoryBench
{
    private CookieInventory $inventory;

    private ConsentPolicy $policy;

    public function __construct()
    {
        $this->policy = new ConsentPolicy('1', [new ConsentCategory('session', true), new ConsentCategory('analytics'), new ConsentCategory('videos'), new ConsentCategory('marketing')]);
        $data = [];

        foreach (['session', 'analytics', 'videos', 'marketing'] as $category) {
            for ($index = 0; $index < 10; ++$index) {
                $data[$category][$category.'_'.$index.(0 === $index % 3 ? '.*' : '')] = ['purpose' => 'Why', 'duration' => 'P1D'];
            }
        }

        $this->inventory = CookieInventory::fromArray($data, $this->policy);
    }

    /**
     * @Revs(1000)
     *
     * @Iterations(5)
     */
    public function benchGroups(): int
    {
        return \count($this->inventory->groups($this->policy));
    }

    /**
     * @Revs(1000)
     *
     * @Iterations(5)
     */
    public function benchUndeclared(): int
    {
        return \count($this->inventory->undeclared(['session_1', 'analytics_3.abc', 'tracker', 'marketing_9']));
    }
}
