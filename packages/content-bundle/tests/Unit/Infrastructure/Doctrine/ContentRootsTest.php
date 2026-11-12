<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine;

use Phil\ContentBundle\Infrastructure\Doctrine\ContentRoots;
use Phil\ContentBundle\Infrastructure\Doctrine\InvalidContentMapping;
use Phil\ContentBundle\Tests\Unit\Fixture\ExampleRoot;
use Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\Article;
use Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\ArticleDimension;
use Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\OrphanDimension;
use Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\Page;
use Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\PageDimension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ContentRoots::class)]
#[CoversClass(InvalidContentMapping::class)]
final class ContentRootsTest extends TestCase
{
    public function testEachRowClassLeadsToTheRootThatNamesIt(): void
    {
        $roots = ContentRoots::fromClassNames([Article::class, Page::class]);

        self::assertSame([Article::class, Page::class], $roots->roots);
        self::assertSame(Article::class, $roots->rootOf(ArticleDimension::class));
        self::assertSame(Page::class, $roots->rootOf(PageDimension::class));
    }

    public function testARowClassOfNoDeclaredRootIsRefused(): void
    {
        $this->expectException(InvalidContentMapping::class);
        $this->expectExceptionMessageIsOrContains(OrphanDimension::class);

        ContentRoots::fromClassNames([Article::class])->rootOf(OrphanDimension::class);
    }

    /**
     * A root that only implements the port has no row class to name: the storage cannot map it.
     */
    public function testAClassThatIsNotAStoredRootIsRefused(): void
    {
        $this->expectException(InvalidContentMapping::class);
        $this->expectExceptionMessageIsOrContains(ExampleRoot::class);

        ContentRoots::fromClassNames([ExampleRoot::class]);
    }

    public function testANonStringIsRefused(): void
    {
        $this->expectException(InvalidContentMapping::class);
        $this->expectExceptionMessageIsOrContains('"int"');

        ContentRoots::fromClassNames([42]);
    }

    /**
     * Declaring a root twice is declaring its row class twice: its association would have two targets.
     */
    public function testTwoRootsSharingARowClassAreRefused(): void
    {
        $this->expectException(InvalidContentMapping::class);
        $this->expectExceptionMessageIsOrContains(ArticleDimension::class);

        ContentRoots::fromClassNames([Article::class, Article::class]);
    }
}
