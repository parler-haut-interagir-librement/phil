<?php

declare(strict_types=1);

namespace Phil\ShortLinkBundle\Tests\Unit\Application;

use Phil\ShortLinkBundle\Application\Exception\InvalidCodec;
use Phil\ShortLinkBundle\Application\ShortLinkDomain;
use Phil\ShortLinkBundle\Application\ShortLinkDomains;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ShortLinkDomains::class)]
#[CoversClass(ShortLinkDomain::class)]
final class ShortLinkDomainsTest extends TestCase
{
    public function testAHostIsReadAsABrowserReadsIt(): void
    {
        $domains = new ShortLinkDomains([new ShortLinkDomain('Go.Example.COM'), new ShortLinkDomain('lien.bücher.example', 'fr', 'http')]);

        self::assertSame('go.example.com', $domains->forHost('GO.example.com')?->host);
        self::assertSame('lien.xn--bcher-kva.example', $domains->forHost('LIEN.xn--bcher-kva.example')?->host);
        self::assertSame('http://lien.xn--bcher-kva.example/abc', $domains->forLocale('fr')->urlOf('abc'));
        self::assertNull($domains->forHost('example.com'));
        self::assertTrue($domains->isShortLinkHost('GO.EXAMPLE.COM'));
        self::assertFalse($domains->isShortLinkHost('www.example.com'));
    }

    public function testALinkIsWrittenOnTheDomainOfItsLocaleOrTheSharedOne(): void
    {
        $domains = new ShortLinkDomains([
            new ShortLinkDomain('en.example.com', 'en'),
            new ShortLinkDomain('go.example.com'),
            new ShortLinkDomain('lien.example.fr', 'fr'),
            new ShortLinkDomain('other.example.com'),
            new ShortLinkDomain('fr2.example.fr', 'fr'),
        ]);

        self::assertSame('https://lien.example.fr/abc', $domains->forLocale('fr')->urlOf('abc'));
        self::assertSame('en.example.com', $domains->forLocale('en')->host);
        self::assertSame('go.example.com', $domains->forLocale('de')->host);
        self::assertSame('go.example.com', $domains->forLocale(null)->host);
        self::assertSame(['en', 'fr'], $domains->locales());
        self::assertSame(['en.example.com', 'go.example.com', 'lien.example.fr', 'other.example.com', 'fr2.example.fr'], array_map(static fn (ShortLinkDomain $domain): string => $domain->host, $domains->all()));
    }

    public function testWithoutASharedDomainTheFirstOneWrites(): void
    {
        $domains = new ShortLinkDomains([new ShortLinkDomain('en.example.com', 'en'), new ShortLinkDomain('fr.example.com', 'fr')]);

        self::assertSame('en.example.com', $domains->forLocale(null)->host);
        self::assertSame('en.example.com', $domains->forLocale('de')->host);
    }

    public function testAHostGivenTwiceKeepsItsFirstLocale(): void
    {
        $domains = new ShortLinkDomains([new ShortLinkDomain('go.example.com', 'fr'), new ShortLinkDomain('GO.example.com', 'en')]);

        self::assertSame('fr', $domains->forHost('go.example.com')?->locale);
        self::assertCount(1, $domains->all());
    }

    /**
     * @return iterable<string, array{list<ShortLinkDomain>, string}>
     */
    public static function invalidDomains(): iterable
    {
        yield 'none' => [[], 'Short links need at least one domain.'];
        yield 'an address' => [[new ShortLinkDomain('127.0.0.1')], 'is not a host name'];
        yield 'localhost' => [[new ShortLinkDomain('localhost')], 'is not a host name'];
        yield 'a port' => [[new ShortLinkDomain('go.example.com:8080')], 'is a host name alone'];
        yield 'a path' => [[new ShortLinkDomain('go.example.com/x')], 'is a host name alone'];
    }

    /**
     * @param list<ShortLinkDomain> $domains
     */
    #[DataProvider('invalidDomains')]
    public function testADomainThatIsNoHostNameIsRefused(array $domains, string $message): void
    {
        $this->expectException(InvalidCodec::class);
        $this->expectExceptionMessageIsOrContains($message);

        new ShortLinkDomains($domains);
    }

    public function testLocalhostIsAcceptedWhenAllowed(): void
    {
        self::assertSame('go.localhost', new ShortLinkDomains([new ShortLinkDomain('go.localhost', scheme: 'http')], true)->forLocale(null)->host);
    }
}
