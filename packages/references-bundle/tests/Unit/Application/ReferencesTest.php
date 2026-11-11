<?php

declare(strict_types=1);

namespace Phil\ReferencesBundle\Tests\Unit\Application;

use Phil\ReferencesBundle\Application\Link;
use Phil\ReferencesBundle\Application\ReferenceCollector;
use Phil\ReferencesBundle\Application\References;
use Phil\ReferencesBundle\Application\ReferenceTarget;
use Phil\ReferencesBundle\Domain\Exception\StillReferenced;
use Phil\ReferencesBundle\Domain\ValueObject\Usage;
use Phil\ReferencesBundle\Tests\Unit\Fixtures\Document;
use Phil\ReferencesBundle\Tests\Unit\Fixtures\ImageTarget;
use Phil\ReferencesBundle\Tests\Unit\Fixtures\InMemoryUsages;
use Phil\ReferencesBundle\Tests\Unit\Fixtures\PageCollector;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(References::class)]
#[CoversClass(Link::class)]
final class ReferencesTest extends TestCase
{
    public function testASourceIsRefreshedAndForgotten(): void
    {
        $usages = new InMemoryUsages();
        $references = $this->references($usages);
        $page = new Document('page', '1', ['9', '8']);

        self::assertTrue($references->refresh($page));
        self::assertSame(['page:1 blocks.0.image image:9', 'page:1 blocks.1.image image:8'], $this->described($usages->bySource['page:1']));
        self::assertSame(['page:1 blocks.0.image image:9'], $this->described($references->usagesOf(ResourceReference::of('image', '9'))));

        $page->images = ['8'];
        $references->refresh($page);
        self::assertSame([], $references->usagesOf(ResourceReference::of('image', '9')));

        self::assertTrue($references->forget($page));
        self::assertSame([], $usages->bySource);
        self::assertFalse($references->refresh(new stdClass()));
        self::assertFalse($references->forget(new stdClass()));
        self::assertSame('page:1', $references->sourceOf($page)?->toString());
        self::assertNull($references->sourceOf(new stdClass()));
    }

    public function testAResourceStillUsedCannotBeDeleted(): void
    {
        $usages = new InMemoryUsages();
        $references = $this->references($usages);
        $references->refresh(new Document('page', '1', ['9']));
        $references->refresh(new Document('page', '2', ['9']));

        $image = ResourceReference::of('image', '9');

        try {
            $references->assertUnused($image);
            self::fail('A resource still used was deleted.');
        } catch (StillReferenced $stillReferenced) {
            self::assertCount(2, $stillReferenced->usages);
        }

        try {
            $references->assertUnused($image, [ResourceReference::of('page', '1')]);
            self::fail('A resource still used by a source that stays was deleted.');
        } catch (StillReferenced $stillReferenced) {
            self::assertSame(['page:2 blocks.0.image image:9'], $this->described($stillReferenced->usages));
        }

        $references->assertUnused($image, [ResourceReference::of('page', '1'), ResourceReference::of('page', '2')]);
        $references->assertUnused(ResourceReference::of('image', '7'));
        self::assertSame('image:9', $references->referenceOf(new Document('image', '9'))?->toString());
        self::assertNull($references->referenceOf(new Document('page', '9')));
    }

    public function testBrokenLinksAreThoseWhoseTargetIsGone(): void
    {
        $usages = new InMemoryUsages();
        $usages->replace(ResourceReference::of('page', '3'), [Usage::of(ResourceReference::of('page', '3'), 'link', ResourceReference::of('post', '1'))]);

        $references = $this->references($usages, ['9']);
        $references->refresh(new Document('page', '1', ['9', '8']));
        $references->refresh(new Document('page', '2', ['8', '7']));

        self::assertSame(['page:2 blocks.1.image image:7', 'page:1 blocks.1.image image:8', 'page:2 blocks.0.image image:8'], $this->described($references->broken()));
        self::assertSame([], $this->references(new InMemoryUsages())->broken());
    }

    public function testEveryLinkACollectorYieldsIsKept(): void
    {
        $usages = new InMemoryUsages();
        $collector = new class implements ReferenceCollector {
            public function supports(object $source): bool
            {
                return true;
            }

            public function sourceOf(object $source): ResourceReference
            {
                return ResourceReference::of('page', '1');
            }

            public function collect(object $source): iterable
            {
                yield from [new Link('hero', ResourceReference::of('image', '1'))];
                yield from [new Link('footer', ResourceReference::of('image', '2'))];
            }
        };
        new References($usages, [$collector], [])->refresh(new stdClass());

        self::assertSame(['page:1 hero image:1', 'page:1 footer image:2'], $this->described($usages->from('page:1')));
    }

    /**
     * @param list<string> $existing
     */
    private function references(InMemoryUsages $usages, array $existing = []): References
    {
        return new References($usages, [new PageCollector()], [new class implements ReferenceTarget {
            public function kind(): string
            {
                return 'video';
            }

            public function exists(string $id): bool
            {
                return true;
            }

            public function referenceOf(object $resource): ?ResourceReference
            {
                return null;
            }
        }, new ImageTarget($existing)]);
    }

    /**
     * @param list<Usage> $usages
     *
     * @return list<string>
     */
    private function described(array $usages): array
    {
        return array_map(static fn (Usage $usage): string => $usage->source->toString().' '.$usage->field.' '.$usage->target->toString(), $usages);
    }
}
