<?php

declare(strict_types=1);

namespace Phil\HttpCacheTagsBundle\Tests\Unit\Application;

use Phil\HttpCacheTagsBundle\Application\CacheTagPurger;
use Phil\HttpCacheTagsBundle\Application\CacheTagResolver;
use Phil\HttpCacheTagsBundle\Application\Invalidator;
use Phil\HttpCacheTagsBundle\Application\ResponseTags;
use Phil\HttpCacheTagsBundle\Domain\Exception\InvalidCacheTag;
use Phil\HttpCacheTagsBundle\Domain\ValueObject\CacheTag;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(CacheTag::class)]
#[CoversClass(InvalidCacheTag::class)]
#[CoversClass(ResponseTags::class)]
#[CoversClass(Invalidator::class)]
final class TagsTest extends TestCase
{
    public function testATagFitsEveryHeader(): void
    {
        self::assertSame('Page:42_a.b-c', CacheTag::of('Page:42_a.b-c')->value);
        self::assertSame(str_repeat('a', 128), CacheTag::of(str_repeat('a', 128))->value);
        self::assertSame(['a', 'b'], array_map(static fn (CacheTag $tag): string => $tag->value, CacheTag::list(['a', 'b', 'a'])));

        foreach (['', 'a b', 'a,b', str_repeat('a', 129), "a\n", 'é'] as $bad) {
            try {
                CacheTag::of($bad);
                self::fail($bad);
            } catch (InvalidCacheTag $invalidCacheTag) {
                self::assertSame(\sprintf('A cache tag is 1 to 128 letters, digits, "_", ".", ":" or "-"; "%s" is not.', $bad), $invalidCacheTag->getMessage());
            }
        }
    }

    /**
     * A repeated tag is kept once, where it first appeared; a tag that looks like a number is a tag like any other.
     */
    public function testARepeatedTagIsKeptOnceWhereItFirstAppeared(): void
    {
        self::assertSame(['b', '42', 'a'], array_map(static fn (CacheTag $tag): string => $tag->value, CacheTag::list(['b', '42', 'b', 'a', '42', 'a'])));
        self::assertSame([], CacheTag::list([]));
        self::assertSame('a', CacheTag::tryOf('a')?->value);
        self::assertNull(CacheTag::tryOf('not a tag'));

        $this->expectException(InvalidCacheTag::class);

        CacheTag::list(['a', 'a', 'not a tag']);
    }

    public function testTheResponseTagsAreKeptOnceUntilReset(): void
    {
        $tags = new ResponseTags();
        $tags->add('page:1', 'menu');
        $tags->add('page:1');

        self::assertSame(['page:1', 'menu'], $tags->all());
        $tags->reset();
        self::assertSame([], $tags->all());
    }

    public function testTagsArePurgedOnceAtTheEnd(): void
    {
        $purger = new class implements CacheTagPurger {
            /** @var list<list<string>> */
            public array $purged = [];

            public function purge(array $tags): void
            {
                $this->purged[] = array_map(static fn (CacheTag $tag): string => $tag->value, $tags);
            }
        };
        $resolver = new class implements CacheTagResolver {
            public function tagsFor(object $entity): iterable
            {
                return $entity instanceof stdClass ? ['page:1', 'page-list'] : [];
            }
        };
        $invalidator = new Invalidator($purger, [$resolver, $resolver]);

        $invalidator->invalidate('menu', 'page:1');
        $invalidator->invalidateEntity(new stdClass());
        $invalidator->invalidateEntity($this);
        self::assertSame(['menu', 'page:1', 'page-list'], $invalidator->pending());
        self::assertSame(3, $invalidator->flush());
        self::assertSame([['menu', 'page:1', 'page-list']], $purger->purged);
        self::assertSame([], $invalidator->pending());
        self::assertSame(0, $invalidator->flush());
        self::assertCount(1, $purger->purged);

        $alone = new Invalidator(null);
        $alone->invalidate('menu');
        self::assertSame(0, $alone->flush());
        self::assertSame([], $alone->pending());
        $alone->invalidateEntity(new stdClass());
        self::assertSame([], $alone->pending());
    }
}
