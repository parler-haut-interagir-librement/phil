<?php

declare(strict_types=1);

namespace Phil\SamlSp\Tests\Unit\Message;

use Phil\SamlSp\Binding\Binding;
use Phil\SamlSp\Binding\HttpRedirect;
use Phil\SamlSp\Exception\IdentityProviderRefused;
use Phil\SamlSp\Exception\InvalidMessage;
use Phil\SamlSp\Exception\InvalidSignature;
use Phil\SamlSp\Message\LogoutProcessor;
use Phil\SamlSp\Message\LogoutRequest;
use Phil\SamlSp\Message\ReceivedMessage;
use Phil\SamlSp\Provider\ServiceProvider;
use Phil\SamlSp\Tests\Support\Dom;
use Phil\SamlSp\Tests\Support\Keys;
use Phil\SamlSp\Tests\Support\Responses;
use Phil\SamlSp\Tests\Support\Stage;
use Phil\SamlSp\Tests\Support\TestSigner;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;
use Throwable;

#[CoversClass(LogoutProcessor::class)]
#[CoversClass(LogoutRequest::class)]
final class LogoutProcessorTest extends TestCase
{
    public function testALogoutRequestOnTheRedirectBinding(): void
    {
        $url = HttpRedirect::url(Url::fromString(Stage::SLO), 'SAMLRequest', self::request(), null, Keys::privateKey('entra'));
        $message = HttpRedirect::receive((string) parse_url($url, \PHP_URL_QUERY), [Keys::certificate('entra')]);
        $request = new LogoutProcessor(Stage::clock())->request($message, Stage::entra(), Stage::sp());

        self::assertSame('_logout', $request->id);
        self::assertSame(Stage::ENTRA, $request->issuer);
        self::assertSame('ada@example.org', $request->nameId);
        self::assertSame('urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress', $request->nameIdFormat);
        self::assertSame(['_s1', '_s2'], $request->sessionIndexes);
    }

    public function testALogoutRequestSignedInside(): void
    {
        $document = Dom::load(self::request());
        TestSigner::sign(Responses::root($document), 'entra');

        $request = new LogoutProcessor(Stage::clock())->request(new ReceivedMessage((string) $document->saveXML(), Binding::Post), Stage::entra(), Stage::sp());
        self::assertSame('ada@example.org', $request->nameId);
    }

    public function testAnUnsignedLogoutRequestIsRefused(): void
    {
        $this->expectException(InvalidSignature::class);
        $this->expectExceptionMessageIsOrContains('The LogoutRequest is not signed.');

        new LogoutProcessor(Stage::clock())->request(new ReceivedMessage(self::request(), Binding::Post), Stage::entra(), Stage::sp());
    }

    public function testALogoutRequestSignedByAnotherKeyIsRefused(): void
    {
        $document = Dom::load(self::request());
        TestSigner::sign(Responses::root($document), 'attacker');

        $this->expectException(InvalidSignature::class);
        new LogoutProcessor(Stage::clock())->request(new ReceivedMessage((string) $document->saveXML(), Binding::Post), Stage::entra(), Stage::sp());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refusedRequests(): iterable
    {
        yield 'another root' => [str_replace('LogoutRequest', 'LogoutResponse', self::request()), 'The message is not a SAML 2.0 LogoutRequest.'];
        yield 'another version' => [str_replace('Version="2.0"', 'Version="1.0"', self::request()), 'The message is not a SAML 2.0 LogoutRequest.'];
        yield 'no ID' => [str_replace('ID="_logout"', 'ID=""', self::request()), 'The message is not a SAML 2.0 LogoutRequest.'];
        yield 'no ID attribute' => [str_replace(' ID="_logout"', '', self::request()), 'The message is not a SAML 2.0 LogoutRequest.'];
        yield 'another issuer' => [str_replace(Stage::ENTRA, Stage::OKTA, self::request()), 'The LogoutRequest is not issued by the identity provider.'];
        yield 'no issuer' => [str_replace('<saml:Issuer>'.Stage::ENTRA.'</saml:Issuer>', '', self::request()), 'The LogoutRequest is not issued by the identity provider.'];
        yield 'another destination' => [str_replace(Stage::SLO, 'https://evil.example.org/slo', self::request()), 'The destination of the LogoutRequest is not the logout service.'];
        yield 'a destination that is no address' => [str_replace(Stage::SLO, 'nowhere', self::request()), 'The destination of the LogoutRequest is not the logout service.'];
        yield 'expired' => [str_replace('NotOnOrAfter="2026-11-19T12:05:00Z"', 'NotOnOrAfter="2026-11-19T11:58:00Z"', self::request()), 'The logout request has expired.'];
        yield 'issued too long ago' => [str_replace('IssueInstant="2026-11-19T12:00:00Z"', 'IssueInstant="2026-11-19T11:00:00Z"', self::request()), 'The LogoutRequest was issued outside the time the service provider accepts.'];
        yield 'no name' => [str_replace('<saml:NameID Format="urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress">ada@example.org</saml:NameID>', '', self::request()), 'A logout request has one name identifier, not encrypted.'];
    }

    #[DataProvider('refusedRequests')]
    public function testARefusedLogoutRequest(string $xml, string $message): void
    {
        $this->expectException(InvalidMessage::class);
        $this->expectExceptionMessageIsOrContains($message);

        new LogoutProcessor(Stage::clock())->request(new ReceivedMessage($xml, Binding::Redirect, null, true), Stage::entra(), Stage::sp());
    }

    public function testALogoutServiceOnLocalhostIsTheDestination(): void
    {
        $sp = new ServiceProvider(Stage::SP, Url::fromString(Stage::ACS), Url::fromString('https://localhost/slo', true), Keys::pair('sp'));
        $xml = str_replace(Stage::SLO, 'https://localhost/slo', self::request());

        self::assertSame('ada@example.org', new LogoutProcessor(Stage::clock())->request(new ReceivedMessage($xml, Binding::Redirect, null, true), Stage::entra(), $sp)->nameId);
    }

    public function testADestinationNeedsALogoutService(): void
    {
        $sp = new ServiceProvider(Stage::SP, Url::fromString(Stage::ACS), signing: Keys::pair('sp'));

        $this->expectExceptionMessageIsOrContains('The destination of the LogoutRequest is not the logout service.');
        new LogoutProcessor(Stage::clock())->request(new ReceivedMessage(self::request(), Binding::Redirect, null, true), Stage::entra(), $sp);
    }

    public function testTheOptionalPartsOfALogoutRequest(): void
    {
        $xml = str_replace([' NotOnOrAfter="2026-11-19T12:05:00Z"', ' Destination="'.Stage::SLO.'"', '<samlp:SessionIndex>_s1</samlp:SessionIndex><samlp:SessionIndex>_s2</samlp:SessionIndex>'], '', self::request());
        $request = new LogoutProcessor(Stage::clock())->request(new ReceivedMessage($xml, Binding::Redirect, null, true), Stage::entra(), new ServiceProvider(Stage::SP, Url::fromString(Stage::ACS), signRequests: false));

        self::assertSame([], $request->sessionIndexes);
    }

    #[DoesNotPerformAssertions]
    public function testALogoutResponse(): void
    {
        new LogoutProcessor(Stage::clock())->response(new ReceivedMessage(self::response(), Binding::Redirect, null, true), Stage::entra(), Stage::sp(), '_mine');
    }

    /**
     * @return iterable<string, array{string, string|null, class-string<Throwable>, string}>
     */
    public static function refusedResponses(): iterable
    {
        yield 'another request' => [self::response(), '_other', InvalidMessage::class, 'The logout response does not answer the logout request of this browser.'];
        yield 'no request' => [self::response(), null, InvalidMessage::class, 'The logout response does not answer the logout request of this browser.'];
        yield 'no InResponseTo' => [str_replace(' InResponseTo="_mine"', '', self::response()), '_mine', InvalidMessage::class, 'The logout response does not answer the logout request of this browser.'];
        yield 'partial' => [str_replace('<samlp:StatusCode Value="urn:oasis:names:tc:SAML:2.0:status:Success"/>', '<samlp:StatusCode Value="urn:oasis:names:tc:SAML:2.0:status:Responder"><samlp:StatusCode Value="urn:oasis:names:tc:SAML:2.0:status:PartialLogout"/></samlp:StatusCode>', self::response()), '_mine', IdentityProviderRefused::class, 'The identity provider answered the status "urn:oasis:names:tc:SAML:2.0:status:Responder" (urn:oasis:names:tc:SAML:2.0:status:PartialLogout).'];
        yield 'no status' => [str_replace('<samlp:Status><samlp:StatusCode Value="urn:oasis:names:tc:SAML:2.0:status:Success"/></samlp:Status>', '', self::response()), '_mine', IdentityProviderRefused::class, 'The identity provider answered the status "".'];
        yield 'no status code' => [str_replace('<samlp:StatusCode Value="urn:oasis:names:tc:SAML:2.0:status:Success"/>', '', self::response()), '_mine', IdentityProviderRefused::class, 'The identity provider answered the status "".'];
    }

    /**
     * @param class-string<Throwable> $exception
     */
    #[DataProvider('refusedResponses')]
    public function testARefusedLogoutResponse(string $xml, ?string $requestId, string $exception, string $message): void
    {
        $this->expectException($exception);
        $this->expectExceptionMessageIsOrContains($message);

        new LogoutProcessor(Stage::clock())->response(new ReceivedMessage($xml, Binding::Redirect, null, true), Stage::entra(), Stage::sp(), $requestId);
    }

    private static function request(): string
    {
        return '<samlp:LogoutRequest xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" ID="_logout" Version="2.0" IssueInstant="2026-11-19T12:00:00Z" Destination="'.Stage::SLO.'" NotOnOrAfter="2026-11-19T12:05:00Z"><saml:Issuer>'.Stage::ENTRA.'</saml:Issuer><saml:NameID Format="urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress">ada@example.org</saml:NameID><samlp:SessionIndex>_s1</samlp:SessionIndex><samlp:SessionIndex>_s2</samlp:SessionIndex></samlp:LogoutRequest>';
    }

    private static function response(): string
    {
        return '<samlp:LogoutResponse xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" ID="_answer" Version="2.0" IssueInstant="2026-11-19T12:00:00Z" Destination="'.Stage::SLO.'" InResponseTo="_mine"><saml:Issuer>'.Stage::ENTRA.'</saml:Issuer><samlp:Status><samlp:StatusCode Value="urn:oasis:names:tc:SAML:2.0:status:Success"/></samlp:Status></samlp:LogoutResponse>';
    }
}
