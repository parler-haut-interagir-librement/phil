<?php

declare(strict_types=1);

namespace Phil\SearchLoupe\Tests\Unit\Fixtures;

use Loupe\Loupe\Configuration;
use Loupe\Loupe\Loupe;
use Loupe\Loupe\LoupeFactory;
use Loupe\Loupe\LoupeFactoryInterface;
use Override;
use RuntimeException;

/**
 * Loupe's factory, failing once it has created the files of a new directory — one named `loupe-` and twelve
 * hexadecimal digits — while it is told to.
 */
final class FailingLoupeFactory implements LoupeFactoryInterface
{
    public bool $failing = false;

    private readonly LoupeFactory $factory;

    public function __construct()
    {
        $this->factory = new LoupeFactory();
    }

    #[Override]
    public function create(string $dataDir, Configuration $configuration): Loupe
    {
        $loupe = $this->factory->create($dataDir, $configuration);

        if ($this->failing && 1 === preg_match('/\/loupe-[0-9a-f]{12}$/D', $dataDir)) {
            $loupe->countDocuments();

            throw new RuntimeException('The new directory failed.');
        }

        return $loupe;
    }

    #[Override]
    public function createInMemory(Configuration $configuration): Loupe
    {
        return $this->factory->createInMemory($configuration);
    }
}
