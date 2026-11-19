<?php

declare(strict_types=1);

namespace Phil\ShortLinkBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\RedirectsBundle\Application\LocationBuilder;
use Phil\RedirectsBundle\Domain\Enum\QueryPolicy;
use Phil\RedirectsBundle\Domain\Enum\RedirectStatus;
use Phil\ShortLinkBundle\Application\Resolution;
use Phil\ShortLinkBundle\Application\ShortLinkResolver;
use Phil\ShortLinkBundle\Application\ShortLinkVisit;
use Phil\ShortLinkBundle\Domain\Enum\VisitorClass;
use Phil\ShortLinkBundle\Domain\Model\ShortLink;
use Phil\ShortLinkBundle\Domain\ValueObject\LinkTerms;
use Phil\ShortLinkBundle\Tests\Unit\Fixture\Links;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

#[CoversClass(ShortLinkResolver::class)]
#[CoversClass(Resolution::class)]
#[CoversClass(ShortLinkVisit::class)]
final class ShortLinkResolverTest extends TestCase
{
    private Links $fixture;

    protected function setUp(): void
    {
        $this->fixture = new Links();
    }

    public function testALinkRedirectsAndCountsItsVisit(): void
    {
        $link = $this->link('abc123', new LinkTerms(RedirectStatus::TemporaryRedirect, maxVisits: 2));
        $resolution = $this->fixture->resolver()->resolve('GO.example.com', '/abc123', 'utm=x');

        self::assertInstanceOf(Resolution::class, $resolution);
        self::assertTrue($resolution->isRedirect());
        self::assertSame([307, 'https://example.com/page'], [$resolution->status, $resolution->location]);
        self::assertEquals(new ShortLinkVisit((int) $link->id(), 'abc123', 'go.example.com', null), $resolution->visit);
        self::assertSame(1, $link->visits());
        self::assertEquals(new DateTimeImmutable('2026-11-12 12:00 UTC'), $link->lastVisitAt());
    }

    /**
     * Robots, prefetches and link previews are redirected and counted apart: they never use up the maximum, nor move
     * the last visit; once people have used it up, machines are gone too.
     */
    public function testOnlyPeopleCountTowardsTheMaximum(): void
    {
        $link = $this->link('poster', new LinkTerms(maxVisits: 1));
        $resolver = $this->fixture->resolver();

        foreach ([VisitorClass::Robot, VisitorClass::Prefetch, VisitorClass::LinkPreview] as $visitor) {
            $resolution = $resolver->resolve('go.example.com', '/poster', '', $visitor);
            self::assertSame(302, $resolution?->status);
            self::assertSame($visitor, $resolution->visit?->visitor);
        }

        self::assertSame([0, 3, null], [$link->visits(), $link->robotVisits(), $link->lastVisitAt()]);
        self::assertSame(VisitorClass::Human, $resolver->resolve('go.example.com', '/poster')?->visit?->visitor);
        self::assertSame([1, 3], [$link->visits(), $link->robotVisits()]);
        self::assertSame(410, $resolver->resolve('go.example.com', '/poster', '', VisitorClass::Robot)?->status);
        self::assertSame(3, $link->robotVisits());
    }

    public function testAKeptQueryIsAppendedByTheLocationBuilder(): void
    {
        $this->link('abc123', new LinkTerms(query: QueryPolicy::Keep), 'https://example.com/page?ref=1#top');

        self::assertSame('https://example.com/page?ref=1&utm=x#top', $this->fixture->resolver()->resolve('go.example.com', '/abc123/', 'utm=x')?->location);
    }

    public function testAHostThatIsNoShortLinkDomainIsNotAnswered(): void
    {
        $resolver = $this->fixture->resolver();

        self::assertNull($resolver->resolve('www.example.com', '/abc123'));
        self::assertFalse($resolver->handles('www.example.com'));
        self::assertTrue($resolver->handles('LIEN.example.fr'));
        self::assertSame([], $this->fixture->repository->lookups);
    }

    public function testTheRootSendsHomeWhenConfigured(): void
    {
        $home = $this->fixture->resolver('https://example.com/')->resolve('go.example.com', '/');

        self::assertSame([302, 'https://example.com/', null], [$home?->status, $home?->location, $home?->visit]);
        $root = $this->fixture->resolver()->resolve('go.example.com', '/');
        self::assertInstanceOf(Resolution::class, $root);
        self::assertSame(404, $root->status);
        self::assertFalse($root->isRedirect());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function pathsThatAreNoCode(): iterable
    {
        yield 'no slash' => ['abc123'];
        yield 'two segments' => ['/abc/123'];
        yield 'out of the alphabet' => ['/abc%20123'];
        yield 'a dot' => ['/robots.txt'];
        yield 'too long' => ['/'.str_repeat('a', 65)];
    }

    #[DataProvider('pathsThatAreNoCode')]
    public function testAPathThatIsNoCodeIsNotFoundWithoutReadingTheLinks(string $path): void
    {
        self::assertSame(404, $this->fixture->resolver()->resolve('go.example.com', $path)?->status);
        self::assertSame([], $this->fixture->repository->lookups);
    }

    public function testTheLongestCodeIsRead(): void
    {
        $this->link(str_repeat('a', 64));

        self::assertTrue($this->fixture->resolver()->resolve('go.example.com', '/'.str_repeat('a', 64))?->isRedirect());
    }

    public function testACodeOfOneCharacterIsRead(): void
    {
        $this->link('a');

        self::assertTrue($this->fixture->resolver()->resolve('go.example.com', '/a')?->isRedirect());
    }

    public function testATargetOnTheLocalMachineIsServedOnlyWhereLocalhostIsAllowed(): void
    {
        $link = $this->link('local', target: 'http://localhost/page');
        $strict = $this->fixture->resolver();
        $allowed = new ShortLinkResolver($this->fixture->repository, $this->fixture->domains, $this->fixture->codec, new LocationBuilder(allowLocalhost: true), $this->fixture->clock, null, true);

        $builderOnly = new ShortLinkResolver($this->fixture->repository, $this->fixture->domains, $this->fixture->codec, new LocationBuilder(allowLocalhost: true), $this->fixture->clock);

        self::assertSame(404, $builderOnly->resolve('go.example.com', '/local')?->status, 'the resolver refuses it too');
        self::assertSame(404, $strict->resolve('go.example.com', '/local')?->status);
        self::assertSame(0, $link->visits());
        self::assertSame('http://localhost/page', $allowed->resolve('go.example.com', '/local')?->location);
        self::assertSame(1, $link->visits());
    }

    public function testAnUnknownCodeIsNotFound(): void
    {
        self::assertSame(404, $this->fixture->resolver()->resolve('go.example.com', '/zzz999')?->status);
        self::assertSame(['zzz999'], $this->fixture->repository->lookups);
    }

    public function testALinkIsServedOnlyOnTheDomainsOfItsLocale(): void
    {
        $this->link('french', new LinkTerms(locale: 'fr'));
        $this->link('shared');
        $resolver = $this->fixture->resolver();

        $french = $resolver->resolve('lien.example.fr', '/french');
        $everywhere = $resolver->resolve('go.example.com', '/french');
        $shared = $resolver->resolve('lien.example.fr', '/shared');

        self::assertTrue($french?->isRedirect());
        self::assertTrue($everywhere?->isRedirect(), 'a domain without a locale serves every link');
        self::assertTrue($shared?->isRedirect());
        self::assertInstanceOf(Resolution::class, $french);
        self::assertSame('fr', $french->visit?->locale);
    }

    public function testALinkOfAnotherLocaleIsNotFound(): void
    {
        $this->link('english', new LinkTerms(locale: 'en'));

        self::assertSame(404, $this->fixture->resolver()->resolve('lien.example.fr', '/english')?->status);
        self::assertSame(0, $this->fixture->repository->findByCode('english')?->visits());
    }

    public function testALinkNotYetInForceIsNotFound(): void
    {
        $link = $this->link('later', new LinkTerms(validFrom: new DateTimeImmutable('2026-11-12 13:00 UTC')));

        self::assertSame(404, $this->fixture->resolver()->resolve('go.example.com', '/later')?->status);
        self::assertSame(0, $link->visits());
    }

    /**
     * @return iterable<string, array{LinkTerms, int}>
     */
    public static function linksNoLongerInForce(): iterable
    {
        yield 'disabled' => [new LinkTerms(enabled: false), 0];
        yield 'expired' => [new LinkTerms(validUntil: new DateTimeImmutable('2026-11-12 12:00 UTC')), 0];
        yield 'out of visits' => [new LinkTerms(maxVisits: 3), 3];
    }

    #[DataProvider('linksNoLongerInForce')]
    public function testALinkNoLongerInForceIsGone(LinkTerms $terms, int $visits): void
    {
        $link = $this->link('gone', $terms);
        new ReflectionProperty(ShortLink::class, 'visits')->setValue($link, $visits);
        $resolution = $this->fixture->resolver()->resolve('go.example.com', '/gone');

        self::assertSame([410, null, null, false], [$resolution?->status, $resolution?->location, $resolution?->visit, $resolution?->isRedirect()]);
        self::assertSame($visits, $link->visits());
        self::assertSame(0, $this->fixture->repository->visitAttempts, 'no visit is asked for a link known to be out of force');
    }

    public function testTheLastVisitLostToARacingRequestIsGone(): void
    {
        $link = $this->link('last', new LinkTerms(maxVisits: 1));
        $this->fixture->repository->loseNextRace = true;

        self::assertSame(410, $this->fixture->resolver()->resolve('go.example.com', '/last')?->status);
        self::assertSame(0, $link->visits());
    }

    public function testALinkWhoseLocationCannotBeBuiltIsNotFoundAndCountsNothing(): void
    {
        $link = $this->link('broken', target: 'https://example.com/page');
        new ReflectionProperty(ShortLink::class, 'target')->setValue($link, '/not-absolute ');
        $other = $this->link('unsaved');
        unset($this->fixture->repository->links[(int) $other->id()]);
        new ReflectionProperty(ShortLink::class, 'id')->setValue($other, null);
        $this->fixture->repository->links[99] = $other;

        self::assertSame(404, $this->fixture->resolver()->resolve('go.example.com', '/broken')?->status);
        self::assertSame(0, $link->visits());
        self::assertSame(404, $this->fixture->resolver()->resolve('go.example.com', '/unsaved')?->status, 'a link without an identifier counts nothing');
    }

    public function testAResolutionOfAStatus(): void
    {
        self::assertSame([404, null, null], [Resolution::notFound()->status, Resolution::notFound()->location, Resolution::notFound()->visit]);
        self::assertSame([410, null], [Resolution::gone()->status, Resolution::gone()->location]);
        self::assertSame([308, '/x'], [Resolution::redirect('/x', RedirectStatus::PermanentRedirect)->status, Resolution::redirect('/x', RedirectStatus::PermanentRedirect)->location]);
    }

    private function link(string $code, LinkTerms $terms = new LinkTerms(), string $target = 'https://example.com/page'): ShortLink
    {
        $link = new ShortLink($code, $target, new DateTimeImmutable('2026-11-01 UTC'), $terms);
        $this->fixture->repository->save($link);

        return $link;
    }
}
