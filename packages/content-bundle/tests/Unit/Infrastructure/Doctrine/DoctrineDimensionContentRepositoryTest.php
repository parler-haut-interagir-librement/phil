<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine;

use Phil\ContentBundle\Domain\Dimension\DimensionKey;
use Phil\ContentBundle\Domain\Dimension\Stage;
use Phil\ContentBundle\Infrastructure\Doctrine\DoctrineDimensionContentRepository;
use Phil\ContentBundle\Infrastructure\Doctrine\InvalidContentMapping;
use Phil\ContentBundle\Tests\Unit\Fixture\ExampleRoot;
use Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\EntityManagerFactory;
use Phil\LocaleDimensions\Version;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * What the adapter refuses before any query. Its lookups run against real tables in `Tests\Integration`.
 */
#[CoversClass(DoctrineDimensionContentRepository::class)]
final class DoctrineDimensionContentRepositoryTest extends TestCase
{
    public function testARootThatNamesNoRowClassIsRefused(): void
    {
        $repository = new DoctrineDimensionContentRepository(EntityManagerFactory::create());

        $this->expectException(InvalidContentMapping::class);
        $this->expectExceptionMessageIsOrContains(ExampleRoot::class);

        $repository->find(new ExampleRoot(), DimensionKey::unlocalized(Stage::Draft, Version::first()));
    }
}
