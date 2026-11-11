<?php

declare(strict_types=1);

namespace Phil\TrashBundle\Tests\Unit\Domain;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use Phil\Resource\ResourceReference;
use Phil\TrashBundle\Domain\Exception\InvalidTrashItem;
use Phil\TrashBundle\Domain\Exception\ParentMissing;
use Phil\TrashBundle\Domain\Exception\TrashException;
use Phil\TrashBundle\Domain\Exception\UnknownResource;
use Phil\TrashBundle\Domain\Model\TrashItem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(TrashItem::class)]
#[CoversClass(ResourceReference::class)]
#[CoversClass(InvalidTrashItem::class)]
#[CoversClass(ParentMissing::class)]
#[CoversClass(UnknownResource::class)]
final class TrashItemTest extends TestCase
{
    public function testAnItemKeepsWhatItWas(): void
    {
        $at = new DateTimeImmutable('2026-11-03 07:00', new DateTimeZone('America/Toronto'));
        $item = TrashItem::of(ResourceReference::of('page', '42'), '  About us ', ['title' => 'About us'], ResourceReference::of('page', '1'), $at, 'sub-1', $at->modify('+30 days'));

        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[0-9a-f]{4}-[0-9a-f]{12}$/', $item->id);
        self::assertSame(['page:42', 'About us', ['title' => 'About us'], 'page:1', 'sub-1'], [$item->resource()->toString(), $item->title(), $item->payload(), $item->parent()?->toString(), $item->trashedBy()]);
        self::assertSame(['2026-11-03T12:00:00+00:00', '2026-12-03T12:00:00+00:00'], [$item->trashedAt()->format('c'), $item->purgeAfter()->format('c')]);
        self::assertFalse($item->isExpiredAt(new DateTimeImmutable('2026-12-03 11:59:59 UTC')));
        self::assertTrue($item->isExpiredAt(new DateTimeImmutable('2026-12-03 12:00:00 UTC')));

        self::assertSame(str_repeat('a', 255), TrashItem::of(ResourceReference::of('page', '1'), 'x', [], null, $at, str_repeat('a', 255), $at)->trashedBy());
        $parent = TrashItem::of(ResourceReference::of('page', '1'), 'x', [], ResourceReference::of('doc', 'a:b'), $at, null, $at)->parent();
        self::assertNotNull($parent);
        self::assertSame(['doc:a:b', 'doc', 'a:b'], [$parent->toString(), $parent->key, $parent->id]);
        $orphan = TrashItem::of(ResourceReference::of('tag', 'x'), str_repeat('é', 255), [], null, $at, null, $at);
        self::assertSame([null, null, 255], [$orphan->parent(), $orphan->trashedBy(), mb_strlen($orphan->title())]);
        self::assertNotSame($item->id, $orphan->id);
    }

    /**
     * @return iterable<string, array{Closure(): mixed, string}>
     */
    public static function badItems(): iterable
    {
        $at = new DateTimeImmutable('2026-11-03 12:00 UTC');
        $page = static fn (): ResourceReference => ResourceReference::of('page', '42');

        yield 'no title' => [static fn (): TrashItem => TrashItem::of($page(), '  ', [], null, $at, null, $at), 'The title of a trashed resource is 1 to 255 characters.'];
        yield 'a long title' => [static fn (): TrashItem => TrashItem::of($page(), str_repeat('é', 256), [], null, $at, null, $at), 'The title of a trashed resource is 1 to 255 characters.'];
        yield 'nobody named' => [static fn (): TrashItem => TrashItem::of($page(), 'x', [], null, $at, '', $at), 'Who trashed a resource is 1 to 255 bytes, or nobody.'];
        yield 'a long name' => [static fn (): TrashItem => TrashItem::of($page(), 'x', [], null, $at, str_repeat('a', 256), $at), 'Who trashed a resource is 1 to 255 bytes, or nobody.'];
        yield 'purged before' => [static fn (): TrashItem => TrashItem::of($page(), 'x', [], null, $at, null, $at->modify('-1 second')), 'A trashed resource is purged after it was trashed.'];
    }

    #[DataProvider('badItems')]
    public function testABadItemIsRefused(Closure $make, string $message): void
    {
        $this->expectException(InvalidTrashItem::class);
        $this->expectExceptionMessageIsOrContains($message);

        $make();
    }

    public function testReferencesAndExceptions(): void
    {
        self::assertSame(['a.b-c_9', str_repeat('1', 255)], [ResourceReference::of('a.b-c_9', str_repeat('1', 255))->key, ResourceReference::of('a.b-c_9', str_repeat('1', 255))->id]);
        self::assertSame('p'.str_repeat('a', 63), ResourceReference::of('p'.str_repeat('a', 63), '1')->key);
        self::assertTrue(ResourceReference::of('page', '1')->equals(ResourceReference::of('page', '1')));
        self::assertFalse(ResourceReference::of('page', '1')->equals(ResourceReference::of('page', '2')));
        self::assertFalse(ResourceReference::of('page', '1')->equals(ResourceReference::of('post', '1')));

        $missing = ParentMissing::of(ResourceReference::of('page', '42'), ResourceReference::of('page', '1'));
        self::assertSame(['page:42 cannot be restored: its parent page:1 is gone; restore it under another parent.', 'page:42', 'page:1'], [$missing->getMessage(), $missing->resource->toString(), $missing->parent->toString()]);
        self::assertInstanceOf(TrashException::class, $missing);
        self::assertSame('No trash handler supports stdClass: register a TrashHandler for it.', UnknownResource::object(new stdClass())->getMessage());
        self::assertSame('No trash handler restores "page" resources: register a TrashHandler for them.', UnknownResource::kind('page')->getMessage());
        self::assertInstanceOf(TrashException::class, UnknownResource::kind('page'));
        self::assertInstanceOf(TrashException::class, InvalidTrashItem::because('x'));
    }
}
