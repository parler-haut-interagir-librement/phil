<?php

declare(strict_types=1);

namespace Phil\LegalNoticesBundle\Tests\Unit;

use DateTimeImmutable;
use Phil\LegalNotices\LegalNotices;
use Phil\LegalNotices\PlainTextBody;
use Phil\LegalNotices\SiteIdentity;
use Phil\LegalNoticesBundle\Infrastructure\StructuredData\LegalPagesStructuredData;
use Phil\LegalNoticesBundle\Tests\Unit\Fixture\Kit;
use Phil\StructuredData\Node;
use Phil\StructuredDataBundle\Application\PageContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

#[CoversClass(LegalPagesStructuredData::class)]
final class LegalPagesStructuredDataTest extends TestCase
{
    public function testThePageOfTheVersionInForce(): void
    {
        $node = $this->node(['_route' => 'phil_legal_notices_legal_notice']);

        self::assertSame(['WebPage'], $node->types);
        self::assertSame('https://example.com/legal#webpage', $node->id);
        self::assertEquals([
            '@type' => 'WebPage',
            '@id' => 'https://example.com/legal#webpage',
            'url' => 'https://example.com/legal',
            'name' => 'Mentions légales',
            'inLanguage' => 'fr',
            'datePublished' => '2026-11-01T00:00:00+00:00',
            'dateModified' => '2026-11-01T00:00:00+00:00',
            'version' => 1,
            'publisher' => [
                '@type' => 'Organization',
                'name' => 'Example',
                'email' => 'hello@example.com',
                'address' => "1 Main Street\nMontréal",
            ],
        ], $node->toArray());
    }

    public function testALocalizedRouteIsKnownByItsCanonicalName(): void
    {
        $node = $this->node(['_route' => 'phil_legal_notices_privacy_policy.fr', '_canonical_route' => 'phil_legal_notices_privacy_policy'], '2026-12-15');

        self::assertSame(2, $node->toArray()['version'] ?? null);
    }

    public function testThePageOfAVersionShowsThatVersion(): void
    {
        $node = $this->node(['_route' => 'phil_legal_notices_legal_notice_version', 'number' => '2']);

        self::assertSame(2, $node->toArray()['version'] ?? null);
        self::assertSame('2027-01-01T00:00:00+00:00', $node->toArray()['dateModified'] ?? null);
    }

    public function testNoOtherPageIsDescribed(): void
    {
        self::assertSame([], $this->nodes(['_route' => 'phil_legal_notices_legal_notice_history']));
        self::assertSame([], $this->nodes(['_route' => 'home']));
        self::assertSame([], $this->nodes([]));
        self::assertSame([], $this->nodes(['_route' => 'phil_legal_notices_legal_notice_version', 'number' => '99']));
        self::assertSame([], $this->nodes(['_route' => 'phil_legal_notices_privacy_policy'], '2026-11-15'));
        self::assertSame([], [...$this->provider('2026-11-15')->nodes(new PageContext(url: 'https://example.com/legal'))]);
        self::assertSame([], [...$this->provider('2026-11-15')->nodes(new PageContext(request: $this->request(['_route' => 'phil_legal_notices_legal_notice'])))]);
    }

    public function testWithoutATranslatorOrAPublisher(): void
    {
        $notices = new LegalNotices(Kit::source(), new PlainTextBody(), new SiteIdentity([]));
        $provider = new LegalPagesStructuredData($notices, new MockClock(new DateTimeImmutable('2026-11-15')));
        $nodes = [...$provider->nodes(new PageContext(url: 'https://example.com/legal', request: $this->request(['_route' => 'phil_legal_notices_legal_notice'])))];

        self::assertCount(1, $nodes);
        self::assertArrayNotHasKey('name', $nodes[0]->toArray());
        self::assertArrayNotHasKey('publisher', $nodes[0]->toArray());
        self::assertArrayNotHasKey('inLanguage', $nodes[0]->toArray());
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function node(array $attributes, string $now = '2026-11-15'): Node
    {
        $nodes = $this->nodes($attributes, $now);
        self::assertCount(1, $nodes);

        return $nodes[0];
    }

    /**
     * @param array<string, mixed> $attributes
     *
     * @return list<Node>
     */
    private function nodes(array $attributes, string $now = '2026-11-15'): array
    {
        return array_values([...$this->provider($now)->nodes(new PageContext('https://example.com/', 'https://example.com/legal', 'fr', $this->request($attributes)))]);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function request(array $attributes): Request
    {
        $request = new Request();
        $request->attributes->add($attributes);

        return $request;
    }

    private function provider(string $now): LegalPagesStructuredData
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['title.legal_notice' => 'Mentions légales'], 'fr', 'phil_legal_notices');

        $notices = new LegalNotices(Kit::source(), new PlainTextBody(), new SiteIdentity(['publisher_name' => 'Example', 'contact_email' => 'hello@example.com', 'publisher_address' => "1 Main Street\nMontréal"]));

        return new LegalPagesStructuredData($notices, new MockClock(new DateTimeImmutable($now)), $translator);
    }
}
