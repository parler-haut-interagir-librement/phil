<?php

declare(strict_types=1);

namespace Phil\SamlSpBundle\Tests\Unit\Infrastructure;

use Phil\SamlSp\Binding\Binding;
use Phil\SamlSp\Binding\Outbound;
use Phil\SamlSpBundle\Infrastructure\Http\OutboundResponder;
use Phil\SamlSpBundle\Infrastructure\Routing\RoutedEndpoints;
use Phil\Url\Exception\InvalidUrl;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

#[CoversClass(OutboundResponder::class)]
#[CoversClass(RoutedEndpoints::class)]
final class HttpTest extends TestCase
{
    public function testARedirect(): void
    {
        $response = OutboundResponder::respond(new Outbound(Binding::Redirect, 'https://idp.example.org/sso?SAMLRequest=a'));

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('https://idp.example.org/sso?SAMLRequest=a', $response->getTargetUrl());
        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
        self::assertSame('no-referrer', $response->headers->get('Referrer-Policy'));
    }

    public function testAForm(): void
    {
        $response = OutboundResponder::respond(new Outbound(Binding::Post, 'https://idp.example.org/sso?a=1&b="2"', ['SAMLRequest' => 'PHg+', 'RelayState' => '<"r">']));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/html; charset=UTF-8', $response->headers->get('Content-Type'));
        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
        self::assertSame('no-referrer', $response->headers->get('Referrer-Policy'));
        self::assertSame(
            '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="robots" content="noindex"><title>Signing in</title></head><body>'
            .'<form method="post" action="https://idp.example.org/sso?a=1&amp;b=&quot;2&quot;" data-controller="phil--saml-sp-bundle--auto-submit">'
            .'<input type="hidden" name="SAMLRequest" value="PHg+"><input type="hidden" name="RelayState" value="&lt;&quot;r&quot;&gt;">'
            .'<p>Your browser is taking you to your organisation to sign in.</p><button type="submit">Continue</button></form></body></html>',
            $response->getContent(),
        );
        self::assertStringNotContainsString('<script', $response->getContent());

        $quote = OutboundResponder::respond(new Outbound(Binding::Post, 'https://idp.example.org/sso', ['RelayState' => "it's \xB1"]));
        self::assertStringContainsString("value=\"it&apos;s \u{FFFD}\"", (string) $quote->getContent());
    }

    public function testTheAddressesOfTheRoutes(): void
    {
        $routes = new RouteCollection();

        foreach (['metadata' => RoutedEndpoints::METADATA, 'acs' => RoutedEndpoints::ACS, 'slo' => RoutedEndpoints::SLO] as $path => $name) {
            $routes->add($name, new Route('/saml/{idp}/'.$path));
        }

        $endpoints = new RoutedEndpoints(new UrlGenerator($routes, new RequestContext('', 'GET', 'App.Example.com', 'https')));
        self::assertSame('https://app.example.com/saml/acme/metadata', $endpoints->metadata('acme')->toString());
        self::assertSame('https://app.example.com/saml/acme/acs', $endpoints->assertionConsumerService('acme')->toString());
        self::assertSame('https://app.example.com/saml/acme/slo', $endpoints->singleLogoutService('acme')->toString());
        self::assertSame('phil_saml_sp_login', RoutedEndpoints::LOGIN);

        $local = new RoutedEndpoints(new UrlGenerator($routes, new RequestContext('', 'GET', 'localhost', 'http', 8000)), true);
        self::assertSame('http://localhost:8000/saml/acme/acs', $local->assertionConsumerService('acme')->toString());

        $this->expectException(InvalidUrl::class);
        new RoutedEndpoints(new UrlGenerator($routes, new RequestContext('', 'GET', 'localhost', 'http', 8000)))->metadata('acme');
    }
}
