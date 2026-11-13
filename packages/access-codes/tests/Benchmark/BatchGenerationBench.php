<?php

declare(strict_types=1);

namespace Phil\AccessCodes\Tests\Benchmark;

use Phil\AccessCodes\AccessCodes;
use Phil\AccessCodes\CodeFormat;
use Phil\AccessCodes\CodeHasher;
use Phil\AccessCodes\CreatedBatch;
use Phil\AccessCodes\InMemoryCodeStore;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

/**
 * What creating a batch of 100,000 codes costs: drawing them from the secure generator with their check symbols, the
 * keyed hash of each, and handing the hashes to a store in memory, so that the store's own writes stay out of the
 * measure. Then the parse and hashes of one typed code, the work of every redemption before the store.
 */
final readonly class BatchGenerationBench
{
    private const string KEY = 'kkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkk';

    private CodeFormat $format;

    private CodeHasher $hasher;

    public function __construct()
    {
        $this->format = new CodeFormat();
        $this->hasher = new CodeHasher(self::KEY, str_repeat('o', 32));
    }

    /**
     * @Revs(1)
     *
     * @Iterations(3)
     */
    public function benchCreateABatchOf100000Codes(): CreatedBatch
    {
        $codes = new AccessCodes(
            new InMemoryCodeStore(),
            $this->hasher,
            new RateLimiterFactory(['id' => 'bench', 'policy' => 'no_limit'], new InMemoryStorage()),
            new MockClock('2026-11-13 10:00:00'),
            $this->format,
        );

        return $codes->create('Bench', 100_000);
    }

    /**
     * @return list<string>
     *
     * @Revs(1000)
     *
     * @Iterations(5)
     */
    public function benchReadAndHashATypedCode(): array
    {
        return $this->hasher->candidates($this->format->parse('7kq2-m9xd-h4tb-h'));
    }
}
