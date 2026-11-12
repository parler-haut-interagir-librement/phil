<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Application\Normalizer;

use DateTimeImmutable;
use DateTimeZone;
use Phil\ContentBundle\Application\Normalizer\WorkflowNormalizer;
use Phil\ContentBundle\Domain\Dimension\DimensionKey;
use Phil\ContentBundle\Domain\Dimension\Stage;
use Phil\ContentBundle\Tests\Unit\Fixture\BareDimensionContent;
use Phil\ContentBundle\Tests\Unit\Fixture\ExampleDimensionContent;
use Phil\ContentBundle\Tests\Unit\Fixture\ExampleRoot;
use Phil\LocaleDimensions\Version;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WorkflowNormalizer::class)]
final class WorkflowNormalizerTest extends TestCase
{
    /**
     * The date carries its offset: a reader never guesses the zone it was written in.
     */
    public function testThePlaceAndTheDateWithItsOffsetAreNormalized(): void
    {
        $row = $this->row();
        $row->setWorkflowPlace('published');
        $row->setWorkflowPublished(new DateTimeImmutable('2026-03-01 09:30:00', new DateTimeZone('America/Toronto')));

        self::assertSame(
            ['workflowPlace' => 'published', 'workflowPublished' => '2026-03-01T09:30:00-05:00'],
            new WorkflowNormalizer()->normalize($row),
        );
    }

    public function testAnUnpublishedRowHasNoDate(): void
    {
        self::assertSame(['workflowPlace' => null, 'workflowPublished' => null], new WorkflowNormalizer()->normalize($this->row()));
    }

    public function testARowWithoutTheCapabilityGivesNothing(): void
    {
        self::assertSame([], new WorkflowNormalizer()->normalize(
            new BareDimensionContent(new ExampleRoot(), DimensionKey::unlocalized(Stage::Live, Version::first())),
        ));
    }

    private function row(): ExampleDimensionContent
    {
        return new ExampleRoot()->createDimensionContent(DimensionKey::unlocalized(Stage::Live, Version::first()));
    }
}
