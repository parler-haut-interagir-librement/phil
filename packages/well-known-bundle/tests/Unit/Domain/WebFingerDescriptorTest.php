<?php

declare(strict_types=1);

namespace Phil\WellKnownBundle\Tests\Unit\Domain;

use Phil\WellKnownBundle\Domain\Exception\InvalidValue;
use Phil\WellKnownBundle\Domain\ValueObject\WebFingerDescriptor;
use Phil\WellKnownBundle\Domain\ValueObject\WebFingerLink;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WebFingerDescriptor::class)]
#[CoversClass(WebFingerLink::class)]
#[CoversClass(InvalidValue::class)]
final class WebFingerDescriptorTest extends TestCase
{
    public function testALinkWritesWhatItHas(): void
    {
        self::assertSame('{"rel":"self"}', json_encode(new WebFingerLink('self')->toArray()));
        self::assertSame(
            '{"rel":"http:\/\/ostatus.org\/schema\/1.0\/subscribe","type":"text\/html","href":"https:\/\/e.x\/","template":"https:\/\/e.x\/follow?uri={uri}","titles":{"en":"Follow","und":"Suivre"},"properties":{"http:\/\/e.x\/p":null}}',
            json_encode(new WebFingerLink('http://ostatus.org/schema/1.0/subscribe', 'text/html', 'https://e.x/', 'https://e.x/follow?uri={uri}', ['en' => 'Follow', 'und' => 'Suivre'], ['http://e.x/p' => null])->toArray()),
        );
    }

    public function testADescriptorWritesWhatItHas(): void
    {
        self::assertSame(['subject' => 'acct:a@b'], new WebFingerDescriptor('acct:a@b')->toArray());
        self::assertEquals((object) ['p' => 'v'], new WebFingerDescriptor('acct:a@b', properties: ['p' => 'v'])->toArray()['properties'] ?? null);
        self::assertSame(
            '{"subject":"acct:a@b","aliases":["https:\/\/b\/@a"],"properties":{"http:\/\/b\/p":"v"},"links":[{"rel":"self"}]}',
            json_encode(new WebFingerDescriptor('acct:a@b', ['https://b/@a'], ['http://b/p' => 'v'], [new WebFingerLink('self')])->toArray()),
        );
    }

    public function testMerging(): void
    {
        $first = new WebFingerDescriptor('acct:a@b', ['https://b/@a', 'https://b/a', 'https://b/~a'], ['p' => 'first', 'q' => null], [new WebFingerLink('self'), new WebFingerLink('profile')]);
        $second = new WebFingerDescriptor('https://b/@a', ['https://b/a', 'https://b/users/a'], ['p' => 'second', 'r' => 'r'], [new WebFingerLink('avatar'), new WebFingerLink('self', href: 'https://b/x')]);

        $merged = $first->merge($second);

        self::assertSame('acct:a@b', $merged->subject);
        self::assertSame(['https://b/@a', 'https://b/a', 'https://b/~a', 'https://b/users/a'], $merged->aliases);
        self::assertSame(['p' => 'first', 'q' => null, 'r' => 'r'], $merged->properties);
        self::assertSame(['self', 'profile', 'avatar', 'self'], array_map(static fn (WebFingerLink $link): string => $link->rel, $merged->links));
        self::assertSame([0, 1, 2, 3], array_keys($merged->links));
    }

    public function testFilteringByRelation(): void
    {
        $descriptor = new WebFingerDescriptor('acct:a@b', ['x'], ['p' => 'v'], [new WebFingerLink('a'), new WebFingerLink('b'), new WebFingerLink('c'), new WebFingerLink('b', href: 'https://b')]);

        self::assertSame($descriptor, $descriptor->withRels([]));
        $filtered = $descriptor->withRels(['b', 'z']);
        self::assertSame([['rel' => 'b'], ['rel' => 'b', 'href' => 'https://b']], array_map(static fn (WebFingerLink $link): array => $link->toArray(), $filtered->links));
        self::assertSame([0, 1], array_keys($filtered->links));
        self::assertSame(['acct:a@b', ['x'], ['p' => 'v']], [$filtered->subject, $filtered->aliases, $filtered->properties]);
        self::assertSame([], $descriptor->withRels(['z'])->links);
    }

    public function testADescriptorHasASubject(): void
    {
        $this->expectExceptionMessageIsOrContains('A WebFinger descriptor has a subject.');

        new WebFingerDescriptor('');
    }

    public function testADescriptorPropertyHasAKey(): void
    {
        $this->expectExceptionMessageIsOrContains('The properties of the WebFinger descriptor of "acct:a@b" are keyed by a URI.');

        new WebFingerDescriptor('acct:a@b', properties: ['' => 'v']);
    }

    public function testALinkHasARelation(): void
    {
        $this->expectExceptionMessageIsOrContains('A WebFinger link has a relation.');

        new WebFingerLink('');
    }

    public function testALinkTitleHasAKey(): void
    {
        $this->expectExceptionMessageIsOrContains('The titles and properties of the WebFinger link "self" are keyed by a language tag or a URI.');

        new WebFingerLink('self', titles: ['' => 'Me']);
    }

    public function testALinkPropertyHasAKey(): void
    {
        $this->expectException(InvalidValue::class);

        new WebFingerLink('self', properties: ['' => 'v']);
    }
}
