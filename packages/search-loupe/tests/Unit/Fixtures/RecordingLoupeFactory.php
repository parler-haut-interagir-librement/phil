<?php

declare(strict_types=1);

namespace Phil\SearchLoupe\Tests\Unit\Fixtures;

use Loupe\Loupe\Configuration;
use Loupe\Loupe\Loupe;
use Loupe\Loupe\LoupeFactory;
use Loupe\Loupe\LoupeFactoryInterface;
use Override;

/**
 * Loupe's factory, recording each Loupe it opens and whether the index's lock was held while it did.
 */
final class RecordingLoupeFactory implements LoupeFactoryInterface
{
    /**
     * @var list<bool>
     */
    private array $opened = [];

    private readonly LoupeFactory $factory;

    public function __construct()
    {
        $this->factory = new LoupeFactory();
    }

    #[Override]
    public function create(string $dataDir, Configuration $configuration): Loupe
    {
        $this->opened[] = $this->locked(\dirname($dataDir).'/phil_search.lock');

        return $this->factory->create($dataDir, $configuration);
    }

    #[Override]
    public function createInMemory(Configuration $configuration): Loupe
    {
        $this->opened[] = false;

        return $this->factory->createInMemory($configuration);
    }

    /**
     * @return list<bool> for each Loupe opened, whether another process held the index's lock
     */
    public function opened(): array
    {
        return $this->opened;
    }

    private function locked(string $file): bool
    {
        if (!is_file($file)) {
            return false;
        }

        $handle = fopen($file, 'r');

        if (false === $handle) {
            return false;
        }

        $free = flock($handle, \LOCK_EX | \LOCK_NB);
        fclose($handle);

        return !$free;
    }
}
