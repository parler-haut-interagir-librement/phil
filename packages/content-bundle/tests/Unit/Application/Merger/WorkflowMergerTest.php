<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Application\Merger;

use DateTimeImmutable;
use Phil\ContentBundle\Application\Merger\WorkflowMerger;
use Phil\ContentBundle\Domain\Dimension\DimensionKey;
use Phil\ContentBundle\Domain\Dimension\Stage;
use Phil\ContentBundle\Tests\Unit\Fixture\BareDimensionContent;
use Phil\ContentBundle\Tests\Unit\Fixture\ExampleDimensionContent;
use Phil\ContentBundle\Tests\Unit\Fixture\ExampleRoot;
use Phil\LocaleDimensions\Version;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WorkflowMerger::class)]
final class WorkflowMergerTest extends TestCase
{
    public function testTheSourcePlaceAndDateReplaceTheTargetOnes(): void
    {
        $published = new DateTimeImmutable('2026-01-15 10:00:00');
        $target = $this->row('draft', null);

        new WorkflowMerger()->merge($target, $this->row('published', $published));

        self::assertSame('published', $target->getWorkflowPlace());
        self::assertSame($published, $target->getWorkflowPublished());
    }

    public function testWhatTheSourceDoesNotSetIsKept(): void
    {
        $published = new DateTimeImmutable('2026-01-15 10:00:00');
        $target = $this->row('published', $published);

        new WorkflowMerger()->merge($target, $this->row(null, null));

        self::assertSame('published', $target->getWorkflowPlace());
        self::assertSame($published, $target->getWorkflowPublished());
    }

    public function testARowWithoutTheCapabilityIsLeftAlone(): void
    {
        $target = $this->row('draft', null);

        new WorkflowMerger()->merge($target, new BareDimensionContent(new ExampleRoot(), DimensionKey::unlocalized(Stage::Draft, Version::first())));

        self::assertSame('draft', $target->getWorkflowPlace());
    }

    /**
     * The marking store of `symfony/workflow` passes a context as the second argument; the setter accepts it.
     */
    public function testThePlaceSetterAcceptsTheMarkingStoreContext(): void
    {
        $row = $this->row(null, null);
        $row->setWorkflowPlace('review', ['transition' => 'submit']);

        self::assertSame('review', $row->getWorkflowPlace());
    }

    private function row(?string $place, ?DateTimeImmutable $published): ExampleDimensionContent
    {
        $row = new ExampleRoot()->createDimensionContent(DimensionKey::unlocalized(Stage::Draft, Version::first()));
        $row->setWorkflowPlace($place);
        $row->setWorkflowPublished($published);

        return $row;
    }
}
