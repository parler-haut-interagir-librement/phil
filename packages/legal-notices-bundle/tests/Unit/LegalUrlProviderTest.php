<?php

declare(strict_types=1);

namespace Phil\LegalNoticesBundle\Tests\Unit;

use DateTimeImmutable;
use Phil\LegalNoticesBundle\Infrastructure\Sitemap\LegalUrlProvider;
use Phil\LegalNoticesBundle\Tests\Unit\Fixture\Kit;
use Phil\Sitemap\Sitemap;
use Phil\Sitemap\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

#[CoversClass(LegalUrlProvider::class)]
final class LegalUrlProviderTest extends TestCase
{
    public function testThePagesOfTheDocumentsInForce(): void
    {
        $sitemap = new Sitemap();
        $this->provider('2026-11-15')->provide($sitemap, null);

        self::assertEquals(['legal' => [new Url('https://example.com/legal', new DateTimeImmutable('2026-11-01T00:00:00+00:00'))]], $sitemap->files());

        $both = new Sitemap();
        $this->provider('2026-12-15')->provide($both, 'legal');
        self::assertSame(['https://example.com/legal', 'https://example.com/privacy'], array_map(static fn (Url $url): string => $url->loc, $both->files()['legal'] ?? []));

        $other = new Sitemap();
        $this->provider('2026-12-15')->provide($other, 'pages');
        self::assertSame([], $other->files());
    }

    private function provider(string $now): LegalUrlProvider
    {
        $routes = new RouteCollection();
        $routes->add('phil_legal_notices_legal_notice', new Route('/legal'));
        $routes->add('phil_legal_notices_privacy_policy', new Route('/privacy'));

        return new LegalUrlProvider(Kit::notices(), new UrlGenerator($routes, new RequestContext(host: 'example.com', scheme: 'https')), new MockClock(new DateTimeImmutable($now)), 'legal');
    }
}
