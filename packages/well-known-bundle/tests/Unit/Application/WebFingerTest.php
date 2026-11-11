<?php

declare(strict_types=1);

namespace Phil\WellKnownBundle\Tests\Unit\Application;

use Phil\WellKnownBundle\Application\Contract\WebFingerResolver;
use Phil\WellKnownBundle\Application\StaticWebFingerResolver;
use Phil\WellKnownBundle\Application\WebFinger;
use Phil\WellKnownBundle\Domain\ValueObject\WebFingerDescriptor;
use Phil\WellKnownBundle\Domain\ValueObject\WebFingerLink;
use Phil\WellKnownBundle\Domain\ValueObject\WebFingerQuery;
use Phil\WellKnownBundle\Domain\ValueObject\WebFingerResource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WebFinger::class)]
#[CoversClass(StaticWebFingerResolver::class)]
final class WebFingerTest extends TestCase
{
    public function testTheResolversAreMergedInTheirOrder(): void
    {
        $webFinger = new WebFinger([
            $this->resolver(null),
            new StaticWebFingerResolver(['acct:a@b' => ['subject' => null, 'aliases' => ['https://b/@a'], 'properties' => ['p' => 'v'], 'links' => [
                ['rel' => 'profile', 'type' => 'text/html', 'href' => 'https://b/@a', 'template' => null, 'titles' => ['en' => 'A'], 'properties' => ['q' => null]],
            ]]]),
            $this->resolver(new WebFingerDescriptor('ignored', ['https://b/users/a'], links: [new WebFingerLink('self', 'application/activity+json', 'https://b/users/a')])),
        ]);

        $descriptor = $webFinger->describe(WebFingerQuery::parse('resource=acct:a@B'));

        self::assertInstanceOf(WebFingerDescriptor::class, $descriptor);
        self::assertSame(
            '{"subject":"acct:a@b","aliases":["https://b/@a","https://b/users/a"],"properties":{"p":"v"},"links":[{"rel":"profile","type":"text/html","href":"https://b/@a","titles":{"en":"A"},"properties":{"q":null}},{"rel":"self","type":"application/activity+json","href":"https://b/users/a"}]}',
            json_encode($descriptor->toArray(), \JSON_UNESCAPED_SLASHES),
        );
        $self = $webFinger->describe(WebFingerQuery::parse('resource=acct:a@b&rel=self'));
        self::assertInstanceOf(WebFingerDescriptor::class, $self);
        self::assertSame(['self'], array_map(static fn (WebFingerLink $link): string => $link->rel, $self->links));
    }

    public function testAResourceNobodyKnows(): void
    {
        self::assertNull(new WebFinger([$this->resolver(null), new StaticWebFingerResolver([])])->describe(WebFingerQuery::parse('resource=acct:a@b')));
        self::assertNull(new WebFinger([])->describe(WebFingerQuery::parse('resource=acct:a@b')));
    }

    public function testAStaticResourceWithASubjectOfItsOwn(): void
    {
        $resolver = new StaticWebFingerResolver(['https://b/@a' => ['subject' => 'acct:a@b', 'aliases' => [], 'properties' => [], 'links' => []]]);

        self::assertSame(['subject' => 'acct:a@b'], $resolver->resolve(WebFingerResource::parse('https://b/@a'))?->toArray());
        self::assertNull($resolver->resolve(WebFingerResource::parse('acct:a@b')));
    }

    private function resolver(?WebFingerDescriptor $descriptor): WebFingerResolver
    {
        return new readonly class($descriptor) implements WebFingerResolver {
            public function __construct(
                private ?WebFingerDescriptor $descriptor,
            ) {
            }

            public function resolve(WebFingerResource $resource): ?WebFingerDescriptor
            {
                return $this->descriptor;
            }
        };
    }
}
