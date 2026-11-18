<?php

declare(strict_types=1);

namespace Phil\OpenBadgesBundle\Tests\Unit\Fixture;

use DateTimeImmutable;
use Phil\OpenBadges\Achievement;
use Phil\OpenBadges\CredentialIssuer;
use Phil\OpenBadges\DataIntegrity\DidKeyMethods;
use Phil\OpenBadges\Issuer;
use Phil\OpenBadges\Key\Ed25519Signer;
use Phil\OpenBadges\Recipient;
use Phil\OpenBadges\Status\StatusListEntry;
use Phil\OpenBadges\Verification\CredentialVerifier;
use Phil\OpenBadgesBundle\Infrastructure\Routing\StatusListUrls;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * The parts the unit tests share: the issuer, its fixed key, its verifier, and the router of its status lists.
 */
final class Kit
{
    public const string ISSUER = 'https://example.com/open-badges/issuer';

    public const string METHOD = self::ISSUER.'#key-1';

    private function __construct()
    {
    }

    /**
     * The issuer's fixed key, so that a credential is the same bytes on every run: test key, public, never trust it.
     */
    public static function signer(): Ed25519Signer
    {
        return Ed25519Signer::fromSeed(str_repeat("\1", 32));
    }

    public static function issuer(): Issuer
    {
        return new Issuer(self::ISSUER, 'Example');
    }

    public static function verifier(): CredentialVerifier
    {
        return new CredentialVerifier(new DidKeyMethods([self::METHOD => self::signer()->verificationKey()]), self::METHOD);
    }

    public static function urls(string $host = 'example.com', ?bool $allowLocalhost = null): StatusListUrls
    {
        $routes = new RouteCollection();
        $routes->add(StatusListUrls::ROUTE, new Route('/open-badges/status/{list}', requirements: ['list' => StatusListUrls::LIST]));

        $generator = new UrlGenerator($routes, new RequestContext(host: $host, scheme: 'https'));

        return null === $allowLocalhost ? new StatusListUrls($generator) : new StatusListUrls($generator, $allowLocalhost);
    }

    public static function token(?StatusListEntry $status = null, string $email = 'ada@example.org'): string
    {
        return new CredentialIssuer(self::issuer(), self::signer(), self::METHOD)
            ->issue(new Achievement('https://example.com/badges/gold', 'Gold', 'Fifty comments.', 'Comment on fifty items.'), Recipient::email($email, 'saltsalt'), 'urn:uuid:3f6f2c1e-8a4b-4c1d-9e2f-5a6b7c8d9e0f', new DateTimeImmutable('2026-01-01 UTC'), status: $status);
    }
}
