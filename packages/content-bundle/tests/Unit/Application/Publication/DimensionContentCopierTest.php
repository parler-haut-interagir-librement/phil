<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Application\Publication;

use Generator;
use Phil\ContentBundle\Application\Merger\TemplateMerger;
use Phil\ContentBundle\Application\Merger\WorkflowMerger;
use Phil\ContentBundle\Application\Pipeline\Merger;
use Phil\ContentBundle\Application\Publication\DimensionContentCopier;
use Phil\ContentBundle\Domain\Dimension\DimensionKey;
use Phil\ContentBundle\Domain\Dimension\Stage;
use Phil\ContentBundle\Tests\Unit\Fixture\ExampleDimensionContent;
use Phil\ContentBundle\Tests\Unit\Fixture\ExampleRoot;
use Phil\LocaleDimensions\Locale;
use Phil\LocaleDimensions\Version;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DimensionContentCopier::class)]
final class DimensionContentCopierTest extends TestCase
{
    /**
     * The mergers come from a tagged iterator, which may be a generator: read once, they serve every copy.
     */
    public function testEveryCopyRunsEveryMerger(): void
    {
        $copier = new DimensionContentCopier($this->mergers());
        $root = new ExampleRoot();
        $source = new ExampleDimensionContent($root, DimensionKey::localized(Locale::fromString('fr'), Stage::Draft, Version::first()));
        $source->setTemplateKey('news');
        $source->setTemplateData(['title' => 'Bonjour']);
        $source->setWorkflowPlace('published');

        $live = DimensionKey::localized(Locale::fromString('fr'), Stage::Live, Version::first());

        $first = $copier->copy($source, $live);
        $second = $copier->copy($source, $live->atVersion(Version::of(2)));

        foreach ([$first, $second] as $copy) {
            self::assertInstanceOf(ExampleDimensionContent::class, $copy);
            self::assertNotSame($source, $copy);
            self::assertSame($root, $copy->root());
            self::assertSame('news', $copy->getTemplateKey());
            self::assertSame(['title' => 'Bonjour'], $copy->getTemplateData());
            self::assertSame('published', $copy->getWorkflowPlace());
        }

        self::assertSame('fr/live/1', $first->dimension()->toString());
        self::assertSame('fr/live/2', $second->dimension()->toString());
    }

    /**
     * @return Generator<Merger>
     */
    private function mergers(): Generator
    {
        yield new TemplateMerger();
        yield new WorkflowMerger();
    }
}
