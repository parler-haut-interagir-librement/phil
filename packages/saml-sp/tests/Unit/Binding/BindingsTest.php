<?php

declare(strict_types=1);

namespace Phil\SamlSp\Tests\Unit\Binding;

use InvalidArgumentException;
use Phil\SamlSp\Binding\Binding;
use Phil\SamlSp\Binding\HttpPost;
use Phil\SamlSp\Binding\HttpRedirect;
use Phil\SamlSp\Binding\MessageSender;
use Phil\SamlSp\Binding\Outbound;
use Phil\SamlSp\Exception\InvalidMessage;
use Phil\SamlSp\Exception\InvalidSignature;
use Phil\SamlSp\Message\MessageFactory;
use Phil\SamlSp\Message\ReceivedMessage;
use Phil\SamlSp\Provider\ServiceProvider;
use Phil\SamlSp\Signature\SignatureAlgorithm;
use Phil\SamlSp\Signature\SignaturePolicy;
use Phil\SamlSp\Signature\SignatureVerifier;
use Phil\SamlSp\Tests\Support\Keys;
use Phil\SamlSp\Tests\Support\Stage;
use Phil\SamlSp\Xml\SafeXml;
use Phil\SamlSp\Xml\XmlBudget;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

#[CoversClass(HttpRedirect::class)]
#[CoversClass(HttpPost::class)]
#[CoversClass(MessageSender::class)]
#[CoversClass(Outbound::class)]
#[CoversClass(ReceivedMessage::class)]
final class BindingsTest extends TestCase
{
    private const string XML = '<samlp:LogoutResponse xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" ID="_1"/>';

    public function testARedirectRoundTrips(): void
    {
        $url = HttpRedirect::url(Url::fromString('https://idp.example.org/slo'), 'SAMLResponse', self::XML, 'state/1', Keys::privateKey('entra'));
        $query = (string) parse_url($url, \PHP_URL_QUERY);

        self::assertStringStartsWith('https://idp.example.org/slo?SAMLResponse=', $url);
        self::assertStringContainsString('&RelayState=state%2F1&SigAlg='.urlencode(SignatureAlgorithm::RsaSha256->value).'&Signature=', $url);

        $message = HttpRedirect::receive($query, [Keys::certificate('okta'), Keys::certificate('entra')]);
        self::assertSame(self::XML, $message->xml);
        self::assertSame('state/1', $message->relayState);
        self::assertSame(Binding::Redirect, $message->binding);
        self::assertTrue($message->signatureVerified);
        self::assertSame('SAMLResponse', $message->parameter);
        self::assertFalse($message->isRequest());
    }

    public function testAnUnsignedRedirectIsNotVerified(): void
    {
        $url = HttpRedirect::url(Url::fromString('https://idp.example.org/sso?tenant=a'), 'SAMLRequest', self::XML);
        $query = (string) parse_url($url, \PHP_URL_QUERY);

        self::assertStringStartsWith('https://idp.example.org/sso?tenant=a&SAMLRequest=', $url);
        self::assertStringNotContainsString('RelayState', $url);
        self::assertStringNotContainsString('SigAlg', $url);

        $message = HttpRedirect::receive($query, [Keys::certificate('entra')]);
        self::assertSame(self::XML, $message->xml);
        self::assertNull($message->relayState);
        self::assertFalse($message->signatureVerified);
        self::assertSame('SAMLRequest', $message->parameter);
        self::assertTrue($message->isRequest());
    }

    public function testTheSignatureCoversTheRawQuery(): void
    {
        $url = HttpRedirect::url(Url::fromString('https://idp.example.org/slo'), 'SAMLRequest', self::XML, 'a b', Keys::privateKey('entra'), SignatureAlgorithm::RsaSha512);
        $query = (string) parse_url($url, \PHP_URL_QUERY);
        parse_str($query, $fields);
        $field = static fn (string $name): string => \is_string($fields[$name] ?? null) ? $fields[$name] : '';
        self::assertSame(SignatureAlgorithm::RsaSha512->value, $field('SigAlg'));

        $reordered = 'Signature='.urlencode($field('Signature')).'&SigAlg='.urlencode($field('SigAlg')).'&RelayState='.urlencode('a b').'&SAMLRequest='.urlencode($field('SAMLRequest')).'&other=1';
        self::assertTrue(HttpRedirect::receive($reordered, [Keys::certificate('entra')])->signatureVerified);

        $this->expectException(InvalidSignature::class);
        $this->expectExceptionMessageIsOrContains('The signature of the query does not verify with the certificates of the identity provider.');
        HttpRedirect::receive(str_replace('RelayState=a+b', 'RelayState=a+c', $query), [Keys::certificate('entra')]);
    }

    public function testARedirectSignedWithAnotherKeyIsRefused(): void
    {
        $url = HttpRedirect::url(Url::fromString('https://idp.example.org/slo'), 'SAMLRequest', self::XML, null, Keys::privateKey('attacker'));

        $this->expectExceptionMessageIsOrContains('The signature of the query does not verify with the certificates of the identity provider.');
        HttpRedirect::receive((string) parse_url($url, \PHP_URL_QUERY), [Keys::certificate('entra')]);
    }

    public function testARedirectVerifiedWithAShortKeyIsRefused(): void
    {
        $url = HttpRedirect::url(Url::fromString('https://idp.example.org/slo'), 'SAMLRequest', self::XML, null, Keys::privateKey('weak'));
        $query = (string) parse_url($url, \PHP_URL_QUERY);

        self::assertTrue(HttpRedirect::receive($query, [Keys::certificate('weak')], new SignaturePolicy(minRsaBits: 1024))->signatureVerified);

        $this->expectExceptionMessageIsOrContains('A trusted certificate holds an RSA key shorter than 2048 bits.');
        HttpRedirect::receive($query, [Keys::certificate('weak')]);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function badQueries(): iterable
    {
        $message = urlencode(base64_encode((string) gzdeflate(self::XML)));

        yield 'nothing' => ['a=1', 'A redirected message carries SAMLRequest or SAMLResponse.'];
        yield 'both' => ['SAMLRequest='.$message.'&SAMLResponse='.$message, 'A redirected message carries SAMLRequest or SAMLResponse.'];
        yield 'twice' => ['SAMLRequest='.$message.'&SAMLRequest='.$message, 'A redirected message carries SAMLRequest once.'];
        yield 'encoded name twice' => ['SAMLRequest='.$message.'&SAML%52equest='.$message, 'A redirected message carries SAMLRequest once.'];
        yield 'not base64' => ['SAMLRequest=***', 'A redirected message is deflated base64 of 262144 bytes at most.'];
        yield 'not deflated' => ['SAMLRequest='.urlencode(base64_encode(self::XML)), 'A redirected message is deflated base64 of 262144 bytes at most.'];
        yield 'no equals sign' => ['SAMLRequest', 'A redirected message is deflated base64 of 262144 bytes at most.'];
        yield 'empty' => ['SAMLRequest=', 'A redirected message is deflated base64 of 262144 bytes at most.'];
        yield 'long relay state' => ['SAMLRequest='.$message.'&RelayState='.str_repeat('a', 1025), 'A relay state has 1024 bytes at most.'];
    }

    #[DataProvider('badQueries')]
    public function testABadQueryIsRefused(string $query, string $message): void
    {
        $this->expectException(InvalidMessage::class);
        $this->expectExceptionMessageIsOrContains($message);

        HttpRedirect::receive($query, [Keys::certificate('entra')]);
    }

    public function testARelayStateOfTheLimitIsRead(): void
    {
        $query = 'SAMLRequest='.urlencode(base64_encode((string) gzdeflate(self::XML))).'&RelayState='.str_repeat('a', 1024);

        self::assertSame(1024, \strlen((string) HttpRedirect::receive($query, [])->relayState));
    }

    public function testAMessageIsNotVerifiedUntilItsSignatureIs(): void
    {
        self::assertFalse(new ReceivedMessage('<a/>', Binding::Post)->signatureVerified);
    }

    public function testTheMessageIsDeflatedAtTheBestLevel(): void
    {
        $randomizer = new Randomizer(new Mt19937(7));
        $xml = '';

        for ($i = 0; $i < 27_500; ++$i) {
            $xml .= \chr(97 + $randomizer->getInt(0, 2));
        }

        self::assertNotSame(gzdeflate($xml, 8), gzdeflate($xml, 9), 'The text tells the levels 8 and 9 apart.');

        $url = HttpRedirect::url(Url::fromString('https://idp.example.org/sso'), 'SAMLRequest', $xml);

        parse_str((string) parse_url($url, \PHP_URL_QUERY), $fields);
        $encoded = $fields['SAMLRequest'] ?? null;

        self::assertIsString($encoded);
        self::assertSame(gzdeflate($xml, 9), base64_decode($encoded, true));
    }

    public function testAValueMayHoldAnEqualsSign(): void
    {
        $query = 'SAMLRequest='.urlencode(base64_encode((string) gzdeflate(self::XML))).'&RelayState=a=b';

        self::assertSame('a=b', HttpRedirect::receive($query, [])->relayState);
    }

    public function testACompressedMessageOfExactlyTheBudgetIsRead(): void
    {
        $payload = hash('sha256', 'a', true).hash('sha256', 'b', true);
        $deflated = (string) gzdeflate($payload);
        $query = 'SAMLRequest='.urlencode(base64_encode($deflated));

        self::assertGreaterThanOrEqual(\strlen($payload), \strlen($deflated));
        self::assertSame($payload, HttpRedirect::receive($query, [], budget: new XmlBudget(maxBytes: \strlen($deflated)))->xml);
    }

    public function testADeflateBombIsRefused(): void
    {
        $bomb = (string) gzdeflate('<a>'.str_repeat('a', 300_000).'</a>', 9);
        $query = 'SAMLRequest='.urlencode(base64_encode($bomb));

        self::assertLessThan(2000, \strlen($bomb));
        self::assertSame(300_007, \strlen(HttpRedirect::receive($query, [], budget: new XmlBudget(maxBytes: 300_007))->xml));

        $this->expectExceptionMessageIsOrContains('A redirected message is deflated base64 of 262144 bytes at most.');
        HttpRedirect::receive($query, []);
    }

    public function testACompressedMessageLargerThanTheBudgetIsRefused(): void
    {
        $query = 'SAMLRequest='.urlencode(base64_encode(random_bytes(200)));

        $this->expectExceptionMessageIsOrContains('A redirected message is deflated base64 of 100 bytes at most.');
        HttpRedirect::receive($query, [], budget: new XmlBudget(maxBytes: 100));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function badSignatures(): iterable
    {
        yield 'no algorithm' => ['&Signature=AAAA', 'The signature algorithm "" is refused.'];
        yield 'SHA-1' => ['&SigAlg='.urlencode(SignatureAlgorithm::RsaSha1->value).'&Signature=AAAA', 'is refused.'];
        yield 'not base64' => ['&SigAlg='.urlencode(SignatureAlgorithm::RsaSha256->value).'&Signature=***', 'The signature of the query is not base64.'];
    }

    #[DataProvider('badSignatures')]
    public function testABadQuerySignatureIsRefused(string $suffix, string $message): void
    {
        $this->expectException(InvalidSignature::class);
        $this->expectExceptionMessageIsOrContains($message);

        HttpRedirect::receive('SAMLRequest='.urlencode(base64_encode((string) gzdeflate(self::XML))).$suffix, [Keys::certificate('entra')]);
    }

    public function testAPostRoundTrips(): void
    {
        $form = HttpPost::form(Url::fromString('https://idp.example.org/sso'), 'SAMLRequest', self::XML, 'state');

        self::assertSame(Binding::Post, $form->binding);
        self::assertFalse($form->isRedirect());
        self::assertSame('https://idp.example.org/sso', $form->url);
        self::assertSame(['SAMLRequest' => base64_encode(self::XML), 'RelayState' => 'state'], $form->fields);
        self::assertSame(['SAMLRequest' => base64_encode(self::XML)], HttpPost::form(Url::fromString('https://idp.example.org/sso'), 'SAMLRequest', self::XML)->fields);

        $message = HttpPost::receive(chunk_split($form->fields['SAMLRequest'], 10, "\r\n"), 'state');
        self::assertSame(self::XML, $message->xml);
        self::assertSame('state', $message->relayState);
        self::assertSame(Binding::Post, $message->binding);
        self::assertFalse($message->signatureVerified);
        self::assertSame('SAMLResponse', $message->parameter);
        self::assertSame('SAMLRequest', HttpPost::receive(base64_encode(self::XML), null, parameter: 'SAMLRequest')->parameter);
    }

    public function testAPostIsBounded(): void
    {
        self::assertSame(str_repeat('a', 100), HttpPost::decode(base64_encode(str_repeat('a', 100)), new XmlBudget(maxBytes: 100)));

        foreach ([base64_encode(str_repeat('a', 101)), str_repeat('A', 4232), '', '!'] as $encoded) {
            try {
                HttpPost::decode($encoded, new XmlBudget(maxBytes: 100));
                self::fail('A field was read beyond the budget.');
            } catch (InvalidMessage $exception) {
                self::assertSame('A posted message is base64 of 100 bytes at most.', $exception->getMessage());
            }
        }

        self::assertSame(3, \strlen(HttpPost::decode(str_repeat(' ', 4225).'YWJj', new XmlBudget(maxBytes: 100))));

        $this->expectExceptionMessageIsOrContains('A posted message is base64 of 100 bytes at most.');
        HttpPost::decode(str_repeat(' ', 4226).'YWJj', new XmlBudget(maxBytes: 100));
    }

    public function testAPostWithALongRelayStateIsRefused(): void
    {
        self::assertSame(str_repeat('a', 1024), HttpPost::receive(base64_encode(self::XML), str_repeat('a', 1024))->relayState);

        $this->expectExceptionMessageIsOrContains('A relay state has 1024 bytes at most.');
        HttpPost::receive(base64_encode(self::XML), str_repeat('a', 1025));
    }

    public function testTheSenderPutsAMessageOnItsBinding(): void
    {
        $factory = new MessageFactory(Stage::clock());
        $redirect = MessageSender::send($factory->authnRequest(Stage::sp(), Stage::entra()), Keys::pair('sp'), 'relay');

        self::assertTrue($redirect->isRedirect());
        self::assertSame([], $redirect->fields);
        self::assertTrue(HttpRedirect::receive((string) parse_url($redirect->url, \PHP_URL_QUERY), [Keys::certificate('sp')])->signatureVerified);

        $post = MessageSender::send($factory->authnRequest(Stage::sp(), Stage::okta()), Keys::pair('sp'), null, SignatureAlgorithm::RsaSha384);
        self::assertSame('https://example.okta.com/app/example_sp_1/exk00000000000000001/sso/saml', $post->url);
        $document = SafeXml::load(HttpPost::decode($post->fields['SAMLRequest']), XmlBudget::message());
        new SignatureVerifier()->verify($document->documentElement ?? self::fail(), [Keys::certificate('sp')]);
        self::assertStringContainsString(SignatureAlgorithm::RsaSha384->value, (string) $document->saveXML());
    }

    public function testAnUnsignedMessageIsSentWithoutSignature(): void
    {
        $sp = new ServiceProvider(Stage::SP, Url::fromString(Stage::ACS), signRequests: false);
        $factory = new MessageFactory(Stage::clock());

        self::assertStringNotContainsString('Signature', MessageSender::send($factory->authnRequest($sp, Stage::entra()), null)->url);
        self::assertStringNotContainsString('Signature', MessageSender::send($factory->authnRequest($sp, Stage::entra()), Keys::pair('sp'))->url);
        self::assertStringNotContainsString('Signature', (string) base64_decode(MessageSender::send($factory->authnRequest($sp, Stage::okta()), Keys::pair('sp'))->fields['SAMLRequest'], true));
    }

    public function testASignedMessageNeedsTheSigningKeys(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('The message is signed: the service provider needs its signing key pair.');

        MessageSender::send(new MessageFactory(Stage::clock())->authnRequest(Stage::sp(), Stage::entra()), null);
    }
}
