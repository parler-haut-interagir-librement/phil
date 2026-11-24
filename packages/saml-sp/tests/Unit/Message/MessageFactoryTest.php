<?php

declare(strict_types=1);

namespace Phil\SamlSp\Tests\Unit\Message;

use Phil\SamlSp\Binding\Binding;
use Phil\SamlSp\Exception\InvalidMetadata;
use Phil\SamlSp\Message\MessageFactory;
use Phil\SamlSp\Message\OutgoingMessage;
use Phil\SamlSp\Metadata\IdentityProviderMetadata;
use Phil\SamlSp\Provider\IdentityProvider;
use Phil\SamlSp\Provider\ServiceProvider;
use Phil\SamlSp\Tests\Support\Keys;
use Phil\SamlSp\Tests\Support\Stage;
use Phil\SamlSp\Xml\XmlWriter;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MessageFactory::class)]
#[CoversClass(OutgoingMessage::class)]
final class MessageFactoryTest extends TestCase
{
    public function testAnAuthnRequest(): void
    {
        $message = new MessageFactory(Stage::clock())->authnRequest(Stage::sp(), Stage::entra());

        self::assertMatchesRegularExpression('/^_[0-9a-f]{40}$/', $message->id);
        self::assertSame(Binding::Redirect, $message->binding);
        self::assertSame('SAMLRequest', $message->parameter);
        self::assertTrue($message->signed);
        self::assertSame('https://login.microsoftonline.com/00000000-0000-4000-8000-000000000001/saml2', $message->destination->toString());
        self::assertSame(
            '<samlp:AuthnRequest xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" ID="'.$message->id.'" Version="2.0" IssueInstant="2026-11-19T12:01:00Z" Destination="https://login.microsoftonline.com/00000000-0000-4000-8000-000000000001/saml2" AssertionConsumerServiceURL="https://sp.example.org/saml/acme/acs" ProtocolBinding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST"><saml:Issuer>https://sp.example.org/saml/metadata</saml:Issuer><samlp:NameIDPolicy AllowCreate="true"/></samlp:AuthnRequest>',
            XmlWriter::xml($message->root),
        );
    }

    public function testAnAuthnRequestWithItsOptions(): void
    {
        $sp = new ServiceProvider(Stage::SP, Url::fromString(Stage::ACS), nameIdFormat: 'urn:oasis:names:tc:SAML:2.0:nameid-format:persistent', signRequests: false);
        $message = new MessageFactory(Stage::clock())->authnRequest($sp, Stage::okta(), forceAuthn: true, isPassive: true);
        $xml = XmlWriter::xml($message->root);

        self::assertSame(Binding::Post, $message->binding);
        self::assertFalse($message->signed);
        self::assertStringContainsString(' ForceAuthn="true" IsPassive="true">', $xml);
        self::assertStringContainsString('<samlp:NameIDPolicy Format="urn:oasis:names:tc:SAML:2.0:nameid-format:persistent" AllowCreate="true"/>', $xml);
    }

    public function testAnIdentityProviderMayAskForSignedRequests(): void
    {
        $sp = new ServiceProvider(Stage::SP, Url::fromString(Stage::ACS), signRequests: false);
        $idp = new IdentityProvider(new IdentityProviderMetadata(Stage::ENTRA, [Binding::Redirect->value => Url::fromString('https://idp.example.org/sso')], [], [Keys::certificate('entra')], true));

        self::assertTrue(new MessageFactory(Stage::clock())->authnRequest($sp, $idp)->signed);
    }

    public function testTheIdsAreRandom(): void
    {
        self::assertNotSame(MessageFactory::newId(), MessageFactory::newId());
    }

    public function testALogoutRequest(): void
    {
        $message = new MessageFactory(Stage::clock())->logoutRequest(Stage::sp(), Stage::entra(), 'ada@example.org', 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress', '_session');

        self::assertSame(Binding::Redirect, $message->binding);
        self::assertTrue($message->signed);
        self::assertSame('SAMLRequest', $message->parameter);
        self::assertSame(
            '<samlp:LogoutRequest xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" ID="'.$message->id.'" Version="2.0" IssueInstant="2026-11-19T12:01:00Z" Destination="https://login.microsoftonline.com/00000000-0000-4000-8000-000000000001/saml2"><saml:Issuer>https://sp.example.org/saml/metadata</saml:Issuer><saml:NameID Format="urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress">ada@example.org</saml:NameID><samlp:SessionIndex>_session</samlp:SessionIndex></samlp:LogoutRequest>',
            XmlWriter::xml($message->root),
        );
        self::assertStringNotContainsString('SessionIndex', XmlWriter::xml(new MessageFactory(Stage::clock())->logoutRequest(Stage::sp(), Stage::okta(), 'ada')->root));
        self::assertSame(Binding::Post, new MessageFactory(Stage::clock())->logoutRequest(Stage::sp(), Stage::okta(), 'ada')->binding);
    }

    public function testALogoutResponse(): void
    {
        $factory = new MessageFactory(Stage::clock());
        $message = $factory->logoutResponse(Stage::sp(), Stage::entra(), '_request');

        self::assertSame('SAMLResponse', $message->parameter);
        self::assertTrue($message->signed);
        self::assertSame(
            '<samlp:LogoutResponse xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" ID="'.$message->id.'" Version="2.0" IssueInstant="2026-11-19T12:01:00Z" Destination="https://login.microsoftonline.com/00000000-0000-4000-8000-000000000001/saml2" InResponseTo="_request"><saml:Issuer>https://sp.example.org/saml/metadata</saml:Issuer><samlp:Status><samlp:StatusCode Value="urn:oasis:names:tc:SAML:2.0:status:Success"/></samlp:Status></samlp:LogoutResponse>',
            XmlWriter::xml($message->root),
        );
        self::assertStringContainsString('urn:oasis:names:tc:SAML:2.0:status:Requester', XmlWriter::xml($factory->logoutResponse(Stage::sp(), Stage::entra(), '_request', false)->root));
    }

    public function testLogoutNeedsALogoutService(): void
    {
        $idp = new IdentityProvider(new IdentityProviderMetadata(Stage::ENTRA, [Binding::Redirect->value => Url::fromString('https://idp.example.org/sso')], [], [Keys::certificate('entra')]));

        foreach ([static fn (MessageFactory $factory): OutgoingMessage => $factory->logoutRequest(Stage::sp(), $idp, 'ada'), static fn (MessageFactory $factory): OutgoingMessage => $factory->logoutResponse(Stage::sp(), $idp, '_r')] as $make) {
            try {
                $make(new MessageFactory(Stage::clock()));
                self::fail('A logout message was written without a logout service.');
            } catch (InvalidMetadata $exception) {
                self::assertSame('The identity provider has no single logout service.', $exception->getMessage());
            }
        }
    }
}
