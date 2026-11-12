<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Application\Pipeline;

use Phil\ContentBundle\Application\DataMapper\ExcerptDataMapper;
use Phil\ContentBundle\Application\DataMapper\SeoDataMapper;
use Phil\ContentBundle\Application\Pipeline\ContentDataMapper;
use Phil\ContentBundle\Domain\Dimension\DimensionKey;
use Phil\ContentBundle\Domain\Dimension\Stage;
use Phil\ContentBundle\Tests\Unit\Fixture\ExampleRoot;
use Phil\LocaleDimensions\Locale;
use Phil\LocaleDimensions\Version;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ContentDataMapper::class)]
final class ContentDataMapperTest extends TestCase
{
    /**
     * The mappers may come as a generator, as a tagged iterator does: every write still sees all of them.
     */
    public function testEveryMapperWritesItsOwnValuesOnEveryCall(): void
    {
        $mapper = new ContentDataMapper((static function (): iterable {
            yield new SeoDataMapper();
            yield new ExcerptDataMapper();
        })());
        $root = new ExampleRoot();

        foreach (['fr', 'en'] as $locale) {
            $unlocalized = $root->createDimensionContent(DimensionKey::unlocalized(Stage::Draft, Version::first()));
            $localized = $root->createDimensionContent(DimensionKey::localized(Locale::fromString($locale), Stage::Draft, Version::first()));

            $mapper->map($unlocalized, $localized, ['seoTitle' => $locale, 'excerptImage' => '/cover.png']);

            self::assertSame($locale, $localized->getSeoTitle());
            self::assertSame('/cover.png', $unlocalized->getExcerptImage());
        }
    }
}
