<?php

declare(strict_types=1);

namespace Phil\Resource\Tests\Unit;

use Phil\Resource\InvalidResource;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ResourceReference::class)]
#[CoversClass(InvalidResource::class)]
final class ResourceReferenceTest extends TestCase
{
    public function testAReferenceIsAKindAndAnIdentifier(): void
    {
        $page = ResourceReference::of('page', '42');

        self::assertSame(['page', '42', 'page:42'], [$page->key, $page->id, $page->toString()]);
        self::assertTrue($page->equals(ResourceReference::of('page', '42')));
        self::assertFalse($page->equals(ResourceReference::of('page', '43')));
        self::assertFalse($page->equals(ResourceReference::of('post', '42')));
        self::assertSame(['a'.str_repeat('.', 63), str_repeat('x', 255)], [ResourceReference::of('a'.str_repeat('.', 63), 'x')->key, ResourceReference::of('a', str_repeat('x', 255))->id]);
        self::assertSame('shop.order_line-2', ResourceReference::of('shop.order_line-2', 'é')->key);
    }

    public function testAReferenceIsReadBackFromItsString(): void
    {
        self::assertEquals(ResourceReference::of('event', 'urn:uuid:42'), ResourceReference::fromString('event:urn:uuid:42'));
        self::assertSame('page:42', ResourceReference::fromString('page:42')->toString());
    }

    public function testAReferenceAgainstTheRulesIsRefused(): void
    {
        foreach ([
            [static fn (): ResourceReference => ResourceReference::of('', '1'), 'A resource kind is 1 to 64 lower-case letters, digits, "_", "." or "-", starting with a letter; "" is not.'],
            [static fn (): ResourceReference => ResourceReference::of('1page', '1'), 'A resource kind is 1 to 64 lower-case letters, digits, "_", "." or "-", starting with a letter; "1page" is not.'],
            [static fn (): ResourceReference => ResourceReference::of('Page', '1'), 'A resource kind is 1 to 64 lower-case letters, digits, "_", "." or "-", starting with a letter; "Page" is not.'],
            [static fn (): ResourceReference => ResourceReference::of("page\n", '1'), "A resource kind is 1 to 64 lower-case letters, digits, \"_\", \".\" or \"-\", starting with a letter; \"page\n\" is not."],
            [static fn (): ResourceReference => ResourceReference::of('a'.str_repeat('b', 64), '1'), 'A resource kind is 1 to 64 lower-case letters, digits, "_", "." or "-", starting with a letter; "a'.str_repeat('b', 64).'" is not.'],
            [static fn (): ResourceReference => ResourceReference::of('page', ''), 'A resource identifier is 1 to 255 bytes.'],
            [static fn (): ResourceReference => ResourceReference::of('page', str_repeat('x', 256)), 'A resource identifier is 1 to 255 bytes.'],
            [static fn (): ResourceReference => ResourceReference::fromString('page'), '"page" is not a resource reference of the form "kind:identifier".'],
            [static fn (): ResourceReference => ResourceReference::fromString('Page:1'), 'A resource kind is 1 to 64 lower-case letters, digits, "_", "." or "-", starting with a letter; "Page" is not.'],
        ] as [$call, $message]) {
            try {
                $call();
                self::fail($message);
            } catch (InvalidResource $invalidResource) {
                self::assertSame($message, $invalidResource->getMessage());
            }
        }
    }
}
