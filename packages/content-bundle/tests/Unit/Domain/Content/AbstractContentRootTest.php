<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Domain\Content;

use Phil\ContentBundle\Domain\Content\AbstractContentRoot;
use Phil\ContentBundle\Domain\Dimension\DimensionKey;
use Phil\ContentBundle\Domain\Dimension\Stage;
use Phil\ContentBundle\Tests\Unit\Fixture\StaticPage;
use Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\Article;
use Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\ArticleDimension;
use Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\Page;
use Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\PageDimension;
use Phil\LocaleDimensions\Locale;
use Phil\LocaleDimensions\Version;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AbstractContentRoot::class)]
final class AbstractContentRootTest extends TestCase
{
    /**
     * The aggregator merges into a row the root builds: it must be of the root's own row class, attached to
     * this root, with the dimension asked for.
     */
    public function testANewRowIsOfTheClassTheRootNames(): void
    {
        $root = new Article();
        $key = DimensionKey::localized(Locale::fromString('fr'), Stage::Draft, Version::of(2));

        $row = $root->createDimensionContent($key);

        self::assertInstanceOf(ArticleDimension::class, $row);
        self::assertSame($root, $row->root());
        self::assertTrue($row->dimension()->equals($key));
    }

    public function testEachRootBuildsItsOwnRowClass(): void
    {
        self::assertInstanceOf(
            PageDimension::class,
            new Page()->createDimensionContent(DimensionKey::unlocalized(Stage::Draft, Version::first())),
        );
    }

    /**
     * The default resource key is the short class name in snake case.
     */
    public function testTheResourceKeyIsTheShortClassNameInSnakeCase(): void
    {
        self::assertSame('article', Article::resourceKey());
        self::assertSame('static_page', StaticPage::resourceKey());
    }
}
