<?php

declare(strict_types=1);

namespace Phil\SamlSp\Tests\Unit\Message;

use DOMDocument;
use DOMElement;
use Phil\SamlSp\Attribute\UserField;
use Phil\SamlSp\Exception\DecryptionFailed;
use Phil\SamlSp\Exception\IdentityProviderRefused;
use Phil\SamlSp\Exception\InvalidMessage;
use Phil\SamlSp\Exception\InvalidSignature;
use Phil\SamlSp\Exception\InvalidXml;
use Phil\SamlSp\Exception\ReplayedAssertion;
use Phil\SamlSp\Message\ResponseProcessor;
use Phil\SamlSp\Message\TimeWindow;
use Phil\SamlSp\Provider\SecurityPolicy;
use Phil\SamlSp\Provider\ServiceProvider;
use Phil\SamlSp\Signature\SignaturePolicy;
use Phil\SamlSp\Store\InMemoryReplayGuard;
use Phil\SamlSp\Tests\Support\Dom;
use Phil\SamlSp\Tests\Support\Keys;
use Phil\SamlSp\Tests\Support\RecordingGuard;
use Phil\SamlSp\Tests\Support\Responses;
use Phil\SamlSp\Tests\Support\Stage;
use Phil\SamlSp\Tests\Support\TestEncrypter;
use Phil\SamlSp\Tests\Support\TestSigner;
use Phil\SamlSp\Xml\XmlBudget;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;

#[CoversClass(ResponseProcessor::class)]
#[CoversClass(TimeWindow::class)]
final class ResponseProcessorTest extends TestCase
{
    public function testTheResponseOfMicrosoftEntraIdSignsThePersonIn(): void
    {
        foreach (['entra-signed-assertion.xml', 'entra-signed-response-and-assertion.xml', 'entra-encrypted.xml'] as $fixture) {
            $identity = Stage::processor()->process(Stage::fixture($fixture), Stage::entra(), Stage::sp(), Stage::REQUEST);

            self::assertSame(Stage::ENTRA, $identity->issuer, $fixture);
            self::assertSame('ada.lovelace@example.org', $identity->nameId);
            self::assertSame('urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress', $identity->nameIdFormat);
            self::assertSame('_9a6c6f9b-2f1e-4a43-9d36-7fd3e5f4b200', $identity->sessionIndex);
            self::assertSame('_9a6c6f9b-2f1e-4a43-9d36-7fd3e5f4b200', $identity->assertionId);
            self::assertSame('2026-11-19T11:59:58+00:00', $identity->authnInstant->format('c'));
            self::assertNull($identity->sessionNotOnOrAfter);
            self::assertSame('urn:oasis:names:tc:SAML:2.0:ac:classes:Password', $identity->authnContext);
            self::assertSame('5f0c1a52-3c1e-4d8e-9a8f-0c3b0b9d7e11', $identity->subject());
            self::assertSame('ada.lovelace@example.org', $identity->value(UserField::Email));
            self::assertSame('Ada', $identity->value(UserField::GivenName));
            self::assertSame('Lovelace', $identity->value(UserField::FamilyName));
            self::assertSame('Ada Lovelace', $identity->value(UserField::DisplayName));
            self::assertSame(['0f5b7f6e-1111-4c2d-9e3f-000000000010', '0f5b7f6e-1111-4c2d-9e3f-000000000020'], $identity->values(UserField::Groups));
            self::assertSame(['Teacher'], $identity->values(UserField::Roles));
            self::assertCount(9, $identity->attributes);
        }
    }

    public function testTheResponseOfOktaSignsThePersonIn(): void
    {
        foreach (['okta-signed.xml', 'okta-encrypted.xml'] as $fixture) {
            $identity = Stage::processor()->process(Stage::fixture($fixture), Stage::okta(), Stage::sp(), Stage::REQUEST);

            self::assertSame(Stage::OKTA, $identity->issuer);
            self::assertSame('00u1a2b3c4d5e6f7g8h9', $identity->subject());
            self::assertSame('id1763553597551.1874093281', $identity->sessionIndex);
            self::assertSame(['Everyone', 'Teachers'], $identity->values(UserField::Groups));
            self::assertSame([], $identity->values(UserField::Roles));
            self::assertNull($identity->value(UserField::DisplayName));
        }
    }

    public function testACommentInTheNameIdentifierHidesNothing(): void
    {
        self::assertSame('ada.lovelace@example.org', Stage::processor()->process(Stage::fixture('attack-comment.xml'), Stage::entra(), Stage::sp(), Stage::REQUEST)->nameId);
    }

    /**
     * @return iterable<string, array{string, class-string<Throwable>, string}>
     */
    public static function attacks(): iterable
    {
        yield 'wrapping into an extension' => ['attack-xsw-extensions.xml', InvalidMessage::class, 'A response holds exactly one assertion, as its own child.'];
        yield 'wrapping by a repeated ID' => ['attack-xsw-duplicate-id.xml', InvalidSignature::class, 'Two elements of the document carry the same ID.'];
        yield 'a moved signature' => ['attack-signature-moved.xml', InvalidSignature::class, 'The reference of the signature is not the element it signs.'];
        yield 'a modified assertion' => ['attack-modified.xml', InvalidSignature::class, 'The digest of the signed element does not match its reference.'];
        yield 'another key' => ['attack-attacker-key.xml', InvalidSignature::class, 'The signature does not verify with the certificates of the identity provider.'];
        yield 'nothing signed' => ['attack-unsigned.xml', InvalidSignature::class, 'The assertion is not signed.'];
        yield 'SHA-1' => ['attack-sha1.xml', InvalidSignature::class, 'The signature algorithm "http://www.w3.org/2000/09/xmldsig#rsa-sha1" is refused.'];
        yield 'error status' => ['entra-error-status.xml', IdentityProviderRefused::class, 'The identity provider answered the status "urn:oasis:names:tc:SAML:2.0:status:Responder".'];
    }

    /**
     * @param class-string<Throwable> $exception
     */
    #[DataProvider('attacks')]
    public function testAnAttackIsRefused(string $fixture, string $exception, string $message): void
    {
        $this->expectException($exception);
        $this->expectExceptionMessageIsOrContains($message);

        Stage::processor()->process(Stage::fixture($fixture), Stage::entra(), Stage::sp(), Stage::REQUEST);
    }

    public function testSha1IsAcceptedWhenThePolicyAllowsIt(): void
    {
        $policy = new SecurityPolicy(signatures: new SignaturePolicy(allowSha1: true));

        self::assertSame('ada.lovelace@example.org', Stage::processor(policy: $policy)->process(Stage::fixture('attack-sha1.xml'), Stage::entra(), Stage::sp(), Stage::REQUEST)->nameId);
    }

    public function testAnAssertionIsAcceptedOnce(): void
    {
        $clock = Stage::clock();
        $processor = Stage::processor($clock, new InMemoryReplayGuard($clock));
        $processor->process(Stage::fixture('entra-signed-assertion.xml'), Stage::entra(), Stage::sp(), Stage::REQUEST);

        $this->expectException(ReplayedAssertion::class);
        $this->expectExceptionMessageIsOrContains('The assertion was already accepted once.');

        $processor->process(Stage::fixture('entra-signed-assertion.xml'), Stage::entra(), Stage::sp(), Stage::REQUEST);
    }

    public function testTheReplayIsRememberedUntilTheLatestExpiryAndTheSkew(): void
    {
        $guard = new RecordingGuard();
        Stage::processor(guard: $guard)->process(Stage::fixture('entra-signed-assertion.xml'), Stage::entra(), Stage::sp(), Stage::REQUEST);

        self::assertSame([[Stage::ENTRA, '_9a6c6f9b-2f1e-4a43-9d36-7fd3e5f4b200', '2026-11-19T13:12:00.123000+00:00']], $guard->uses);

        $guard = new RecordingGuard();
        $document = Responses::entra(['notOnOrAfter' => '2026-11-19T12:03:00Z', 'confirmationNotOnOrAfter' => '2026-11-19T12:04:00Z']);
        Stage::processor(guard: $guard)->process($this->signed($document), Stage::entra(), Stage::sp(), Stage::REQUEST);

        self::assertSame('2026-11-19T12:06:00.000000+00:00', $guard->uses[0][2]);
    }

    public function testAResponseSignedAroundAnUnsignedAssertionNeedsTheIdentityProviderToSayIt(): void
    {
        $document = Responses::entra();
        TestSigner::sign(Responses::root($document), 'entra');

        self::assertSame('ada.lovelace@example.org', Stage::processor()->process(base64_encode((string) $document->saveXML()), Stage::entra(wantAssertionsSigned: false), Stage::sp(), Stage::REQUEST)->nameId);

        $this->expectExceptionMessageIsOrContains('The assertion is not signed.');
        Stage::processor()->process(base64_encode((string) $document->saveXML()), Stage::entra(), Stage::sp(), Stage::REQUEST);
    }

    public function testAnUnsignedResponseAroundAnUnsignedAssertionIsRefusedWhateverTheConfiguration(): void
    {
        $this->expectExceptionMessageIsOrContains('The assertion is not signed.');

        Stage::processor()->process(Stage::fixture('attack-unsigned.xml'), Stage::entra(wantAssertionsSigned: false), Stage::sp(), Stage::REQUEST);
    }

    public function testATamperedResponseSignatureIsRefusedEvenWithASignedAssertion(): void
    {
        $document = Dom::load(Keys::fixture('entra-signed-response-and-assertion.xml'));
        Responses::root($document)->setAttribute('IssueInstant', '2026-11-19T12:00:01Z');

        $this->expectExceptionMessageIsOrContains('The digest of the signed element does not match its reference.');
        Stage::processor()->process(base64_encode((string) $document->saveXML()), Stage::entra(), Stage::sp(), Stage::REQUEST);
    }

    public function testAnUnsolicitedResponseIsAcceptedOnlyWhenAllowed(): void
    {
        $document = Responses::entra(['inResponseTo' => null]);

        self::assertSame('ada.lovelace@example.org', Stage::processor()->process($this->signed($document), Stage::entra(allowUnsolicited: true), Stage::sp(), null)->nameId);

        $this->expectExceptionMessageIsOrContains('The response answers no request of this browser, and the identity provider may not send one unsolicited.');
        Stage::processor()->process($this->signed(Responses::entra(['inResponseTo' => null])), Stage::entra(), Stage::sp(), null);
    }

    public function testAResponseToAnotherRequestIsRefusedEvenWhenUnsolicitedOnesAreAllowed(): void
    {
        $this->expectExceptionMessageIsOrContains('The response does not answer the request of this browser.');

        Stage::processor()->process(Stage::fixture('entra-signed-assertion.xml'), Stage::entra(allowUnsolicited: true), Stage::sp(), null);
    }

    public function testAnAssertionWithoutTheRequestIsRefused(): void
    {
        $this->expectExceptionMessageIsOrContains('The response does not answer the request of this browser.');

        Stage::processor()->process($this->signed(Responses::entra(['inResponseTo' => null])), Stage::entra(), Stage::sp(), Stage::REQUEST);
    }

    public function testTheConfirmationMustAnswerTheRequestToo(): void
    {
        $document = Responses::entra();
        Responses::first($document, '//saml:SubjectConfirmationData')->setAttribute('InResponseTo', '_other');

        $this->expectExceptionMessageIsOrContains('The subject confirmation does not answer the request of this browser.');
        Stage::processor()->process($this->signed($document), Stage::entra(), Stage::sp(), Stage::REQUEST);
    }

    /**
     * @return iterable<string, array{array<string, string|null>, string}>
     */
    public static function refusedOptions(): iterable
    {
        yield 'another request' => [['inResponseTo' => '_other'], 'The response does not answer the request of this browser.'];
        yield 'another destination' => [['destination' => 'https://evil.example.org/acs'], 'The destination of the response is not the assertion consumer service.'];
        yield 'a destination that is no address' => [['destination' => 'not an address'], 'The destination of the response is not the assertion consumer service.'];
        yield 'another recipient' => [['recipient' => 'https://sp.example.org/saml/other/acs'], 'The recipient of the assertion is not the assertion consumer service.'];
        yield 'another audience' => [['audience' => 'https://other.example.org/saml/metadata'], 'The service provider is not an audience of the assertion.'];
        yield 'another issuer' => [['issuer' => 'https://sts.windows.net/ffffffff-0000-4000-8000-000000000001/'], 'The response is not issued by the identity provider.'];
        yield 'expired assertion' => [['notOnOrAfter' => '2026-11-19T11:58:00Z'], 'The assertion has expired.'];
        yield 'expired confirmation' => [['confirmationNotOnOrAfter' => '2026-11-19T11:58:59Z'], 'The subject confirmation has expired.'];
        yield 'assertion not valid yet' => [['notBefore' => '2026-11-19T12:03:01Z'], 'The assertion is not valid yet.'];
        yield 'issued in the future' => [['issueInstant' => '2026-11-19T12:03:01Z'], 'The response was issued outside the time the service provider accepts.'];
        yield 'issued too long ago' => [['issueInstant' => '2026-11-19T11:48:59Z'], 'The response was issued outside the time the service provider accepts.'];
        yield 'session ended' => [['sessionNotOnOrAfter' => '2026-11-19T11:58:00Z'], 'The session of the identity provider has expired.'];
        yield 'empty name' => [['nameId' => ''], 'The name identifier has 1 to 1024 bytes.'];
        yield 'long name' => [['nameId' => str_repeat('a', 1025)], 'The name identifier has 1 to 1024 bytes.'];
    }

    /**
     * @param array<string, string|null> $options
     */
    #[DataProvider('refusedOptions')]
    public function testACheckOfTheProfileRefuses(array $options, string $message): void
    {
        $this->expectException(InvalidMessage::class);
        $this->expectExceptionMessageIsOrContains($message);

        Stage::processor()->process($this->signed(Responses::entra($options)), Stage::entra(), Stage::sp(), Stage::REQUEST);
    }

    /**
     * @return iterable<string, array{array<string, string|null>}>
     */
    public static function acceptedOptions(): iterable
    {
        yield 'expiring within the skew' => [['notOnOrAfter' => '2026-11-19T11:59:00.001Z']];
        yield 'confirmation within the skew' => [['confirmationNotOnOrAfter' => '2026-11-19T11:59:01Z']];
        yield 'starting within the skew' => [['notBefore' => '2026-11-19T12:03:00Z']];
        yield 'issued within the skew' => [['issueInstant' => '2026-11-19T12:03:00Z']];
        yield 'issued at the oldest' => [['issueInstant' => '2026-11-19T11:49:00Z']];
        yield 'session going on' => [['sessionNotOnOrAfter' => '2026-11-19T20:00:00Z']];
        yield 'long name' => [['nameId' => str_repeat('a', 1024)]];
        yield 'an address written otherwise' => [['destination' => 'HTTPS://SP.example.org:443/saml/acme/acs', 'recipient' => 'https://sp.EXAMPLE.org/saml/./acme/acs']];
    }

    /**
     * @param array<string, string|null> $options
     */
    #[DataProvider('acceptedOptions')]
    public function testTheEdgesOfTheChecksAreAccepted(array $options): void
    {
        $identity = Stage::processor()->process($this->signed(Responses::entra($options)), Stage::entra(), Stage::sp(), Stage::REQUEST);

        self::assertSame($options['nameId'] ?? 'ada.lovelace@example.org', $identity->nameId);
    }

    public function testTheSessionEndIsRead(): void
    {
        $identity = Stage::processor()->process($this->signed(Responses::entra(['sessionNotOnOrAfter' => '2026-11-19T20:00:00Z'])), Stage::entra(), Stage::sp(), Stage::REQUEST);

        self::assertSame('2026-11-19T20:02:00+00:00', $identity->sessionNotOnOrAfter?->format('c'));
    }

    public function testASignedResponseWithoutDestinationIsRefused(): void
    {
        $document = Responses::entra();
        Responses::root($document)->removeAttribute('Destination');
        TestSigner::sign(Responses::assertion($document), 'entra');

        self::assertSame('ada.lovelace@example.org', Stage::processor()->process(base64_encode((string) $document->saveXML()), Stage::entra(), Stage::sp(), Stage::REQUEST)->nameId);

        $document = Responses::entra();
        Responses::root($document)->removeAttribute('Destination');
        TestSigner::sign(Responses::assertion($document), 'entra');
        TestSigner::sign(Responses::root($document), 'entra');

        $this->expectExceptionMessageIsOrContains('The destination of the response is not the assertion consumer service.');
        Stage::processor(guard: new RecordingGuard())->process(base64_encode((string) $document->saveXML()), Stage::entra(), Stage::sp(), Stage::REQUEST);
    }

    /**
     * @return iterable<string, array{callable(DOMDocument): void, string}>
     */
    public static function malformed(): iterable
    {
        $remove = static fn (string $query): callable => static function (DOMDocument $document) use ($query): void {
            $node = Responses::first($document, $query);
            $node->parentNode?->removeChild($node);
        };
        $set = static fn (string $query, string $attribute, string $value): callable => static function (DOMDocument $document) use ($query, $attribute, $value): void {
            Responses::first($document, $query)->setAttribute($attribute, $value);
        };
        $unset = static fn (string $query, string $attribute): callable => static function (DOMDocument $document) use ($query, $attribute): void {
            Responses::first($document, $query)->removeAttribute($attribute);
        };

        yield 'response issuer of another format' => [$set('/samlp:Response/saml:Issuer', 'Format', 'urn:oasis:names:tc:SAML:2.0:nameid-format:transient'), 'The response is not issued by the identity provider.'];
        yield 'no assertion issuer' => [$remove('//saml:Assertion/saml:Issuer'), 'The assertion has one issuer.'];
        yield 'assertion of another issuer' => [static function (DOMDocument $document): void {
            Responses::first($document, '//saml:Assertion/saml:Issuer')->textContent = 'https://evil.example.org';
        }, 'The assertion is not issued by the identity provider.'];
        yield 'assertion version' => [$set('//saml:Assertion', 'Version', '1.1'), 'The assertion is not a SAML 2.0 assertion with an ID.'];
        yield 'no subject' => [$remove('//saml:Subject'), 'The assertion has one subject.'];
        yield 'no name identifier' => [$remove('//saml:NameID'), 'The subject has one name identifier, not encrypted.'];
        yield 'no bearer' => [$set('//saml:SubjectConfirmation', 'Method', 'urn:oasis:names:tc:SAML:2.0:cm:holder-of-key'), 'The subject has no bearer confirmation.'];
        yield 'no confirmation data' => [$remove('//saml:SubjectConfirmationData'), 'The subject has no bearer confirmation.'];
        yield 'no recipient' => [$unset('//saml:SubjectConfirmationData', 'Recipient'), 'The recipient of the assertion is not the assertion consumer service.'];
        yield 'no confirmation expiry' => [$unset('//saml:SubjectConfirmationData', 'NotOnOrAfter'), 'The subject confirmation has a NotOnOrAfter.'];
        yield 'confirmation not valid yet' => [$set('//saml:SubjectConfirmationData', 'NotBefore', '2026-11-19T12:10:00Z'), 'The subject confirmation is not valid yet.'];
        yield 'no conditions' => [$remove('//saml:Conditions'), 'The assertion has one Conditions.'];
        yield 'no conditions expiry' => [$unset('//saml:Conditions', 'NotOnOrAfter'), 'The conditions of the assertion have a NotOnOrAfter.'];
        yield 'no audience' => [$remove('//saml:AudienceRestriction'), 'The assertion is restricted to an audience.'];
        yield 'a second restriction without the service provider' => [static function (DOMDocument $document): void {
            $restriction = Responses::first($document, '//saml:AudienceRestriction');
            $other = Dom::create($document, Responses::ASSERTION, 'AudienceRestriction');
            Dom::append($other, Responses::ASSERTION, 'Audience', 'https://other.example.org');
            $restriction->parentNode?->appendChild($other);
        }, 'The service provider is not an audience of the assertion.'];
        yield 'no authentication statement' => [$remove('//saml:AuthnStatement'), 'The assertion has an authentication statement.'];
        yield 'no authentication instant' => [$unset('//saml:AuthnStatement', 'AuthnInstant'), 'The authentication instant is not an instant in UTC.'];
        yield 'no assertion issue instant' => [$unset('//saml:Assertion', 'IssueInstant'), 'The assertion issue instant is not an instant in UTC.'];
    }

    /**
     * @param callable(DOMDocument): void $change
     */
    #[DataProvider('malformed')]
    public function testAMalformedAssertionIsRefused(callable $change, string $message): void
    {
        $document = Responses::entra();
        $change($document);

        $this->expectException(InvalidMessage::class);
        $this->expectExceptionMessageIsOrContains($message);

        Stage::processor()->process($this->signed($document), Stage::entra(), Stage::sp(), Stage::REQUEST);
    }

    public function testTheOptionalPartsMayBeAbsent(): void
    {
        $document = Responses::entra();

        foreach (['/samlp:Response/saml:Issuer', '//saml:AuthnContext', '//saml:AttributeStatement'] as $query) {
            $node = Responses::first($document, $query);
            $node->parentNode?->removeChild($node);
        }

        Responses::first($document, '//saml:AuthnStatement')->removeAttribute('SessionIndex');
        Responses::first($document, '//saml:Conditions')->removeAttribute('NotBefore');
        Responses::first($document, '//saml:NameID')->removeAttribute('Format');
        $identity = Stage::processor()->process($this->signed($document), Stage::entra(), Stage::sp(), Stage::REQUEST);

        self::assertNull($identity->authnContext);
        self::assertNull($identity->sessionIndex);
        self::assertNull($identity->nameIdFormat);
        self::assertSame([], $identity->attributes);
        self::assertSame('ada.lovelace@example.org', $identity->subject());
    }

    public function testAnAuthnContextWithoutClassIsRead(): void
    {
        $document = Responses::entra();
        $class = Responses::first($document, '//saml:AuthnContextClassRef');
        $class->parentNode?->removeChild($class);

        self::assertNull(Stage::processor()->process($this->signed($document), Stage::entra(), Stage::sp(), Stage::REQUEST)->authnContext);
    }

    public function testTheConfirmationsAreReadUntilABearerOne(): void
    {
        $document = Responses::entra();
        $confirmation = Responses::first($document, '//saml:SubjectConfirmation');
        $holder = $confirmation->cloneNode(true);
        $this->assertInstanceOf(DOMElement::class, $holder);
        $holder->setAttribute('Method', 'urn:oasis:names:tc:SAML:2.0:cm:holder-of-key');
        $confirmation->parentNode?->insertBefore($holder, $confirmation);
        $empty = Dom::create($document, Responses::ASSERTION, 'SubjectConfirmation');
        $empty->setAttribute('Method', 'urn:oasis:names:tc:SAML:2.0:cm:bearer');
        $confirmation->parentNode?->insertBefore($empty, $confirmation);

        self::assertSame('ada.lovelace@example.org', Stage::processor()->process($this->signed($document), Stage::entra(), Stage::sp(), Stage::REQUEST)->nameId);
    }

    public function testAttributesAreMergedAndNilValuesLeftOut(): void
    {
        $document = Responses::entra();
        $statement = Responses::first($document, '//saml:AttributeStatement');
        $other = Dom::create($document, Responses::ASSERTION, 'AttributeStatement');
        Dom::append(Dom::append($other, Responses::ASSERTION, 'Attribute'), Responses::ASSERTION, 'AttributeValue', 'nameless');
        $role = Dom::create($document, Responses::ASSERTION, 'Attribute');
        $role->setAttribute('Name', 'http://schemas.microsoft.com/ws/2008/06/identity/claims/role');
        Dom::append($role, Responses::ASSERTION, 'AttributeValue', ' Admin ');

        $nil = Dom::create($document, Responses::ASSERTION, 'AttributeValue');
        $nil->setAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'xsi:nil', 'true');

        $role->appendChild($nil);
        $other->appendChild($role);
        $statement->parentNode?->insertBefore($other, $statement->nextSibling);

        $identity = Stage::processor()->process($this->signed($document), Stage::entra(), Stage::sp(), Stage::REQUEST);

        self::assertSame(['Teacher', 'Admin'], $identity->values(UserField::Roles));
        self::assertArrayNotHasKey('', $identity->attributes);
    }

    public function testAnAssertionMustBeEncryptedWhenTheIdentityProviderIsConfiguredSo(): void
    {
        self::assertSame('ada.lovelace@example.org', Stage::processor()->process(Stage::fixture('entra-encrypted.xml'), Stage::entra(wantAssertionsEncrypted: true), Stage::sp(), Stage::REQUEST)->nameId);

        $this->expectExceptionMessageIsOrContains('The assertion is not encrypted.');
        Stage::processor()->process(Stage::fixture('entra-signed-assertion.xml'), Stage::entra(wantAssertionsEncrypted: true), Stage::sp(), Stage::REQUEST);
    }

    public function testAnEncryptedAssertionNeedsADecryptionKey(): void
    {
        $this->expectExceptionMessageIsOrContains('The assertion is encrypted, and the service provider has no decryption key.');

        Stage::processor()->process(Stage::fixture('entra-encrypted.xml'), Stage::entra(), Stage::sp(encryption: false), Stage::REQUEST);
    }

    public function testAnEncryptedAssertionMustHoldOneAssertion(): void
    {
        foreach (['<saml:Assertion xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" ID="a" Version="2.0"/><saml:Assertion xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" ID="b" Version="2.0"/>', '<other/>', 'text', '<saml:Assertion ID="a" Version="2.0"><saml:Advice><saml:Assertion ID="b"/></saml:Advice></saml:Assertion>'] as $plain) {
            try {
                Stage::processor()->process(base64_encode($this->encryptedResponse($plain)), Stage::entra(), Stage::sp(), Stage::REQUEST);
                self::fail('The plaintext was accepted: '.$plain);
            } catch (InvalidMessage $exception) {
                self::assertSame('An encrypted assertion holds exactly one assertion.', $exception->getMessage());
            }
        }
    }

    public function testADecryptedAssertionIsReadWithItsDeclaration(): void
    {
        $document = Responses::entra();
        $assertion = Responses::assertion($document);
        TestSigner::sign($assertion, 'entra', '');
        $plain = '<?xml version="1.0" encoding="UTF-8"?>'.$document->saveXML($assertion);

        self::assertSame('ada.lovelace@example.org', Stage::processor()->process(base64_encode($this->encryptedResponse($plain)), Stage::entra(), Stage::sp(), Stage::REQUEST)->nameId);
    }

    public function testThePrefixesOfTheDecryptedAssertionAreThoseOfTheProfile(): void
    {
        $document = Responses::okta();
        $assertion = Responses::assertion($document);
        TestSigner::sign($assertion, 'okta', 'ds', ['xs']);
        $plain = (string) preg_replace('/ xmlns:(saml2|ds|xs|xsi)="[^"]*"/', '', (string) $document->saveXML($assertion));

        self::assertDoesNotMatchRegularExpression('/xmlns:(saml2|ds|xs|xsi)=/', $plain);
        self::assertSame('00u1a2b3c4d5e6f7g8h9', Stage::processor()->process(base64_encode($this->encryptedResponse($plain, Responses::okta())), Stage::okta(), Stage::sp(), Stage::REQUEST)->subject());
    }

    public function testAResponseAndItsAssertionNeverShareAnId(): void
    {
        $document = Responses::entra(['responseId' => '_same', 'assertionId' => '_same']);

        $this->expectException(InvalidSignature::class);
        $this->expectExceptionMessageIsOrContains('Two elements of the document carry the same ID.');

        Stage::processor()->process(base64_encode((string) $document->saveXML()), Stage::entra(), Stage::sp(), Stage::REQUEST);
    }

    /**
     * @return iterable<string, array{callable(DOMElement): void}>
     */
    public static function assertionsWithoutAnId(): iterable
    {
        yield 'empty' => [static function (DOMElement $assertion): void {
            $assertion->setAttribute('ID', '');
        }];
        yield 'absent' => [static function (DOMElement $assertion): void {
            $assertion->removeAttribute('ID');
        }];
    }

    /**
     * @param callable(DOMElement): void $change
     */
    #[DataProvider('assertionsWithoutAnId')]
    public function testAnAssertionNeedsAnId(callable $change): void
    {
        $document = Responses::entra();
        $change(Responses::assertion($document));
        TestSigner::sign(Responses::root($document), 'entra');

        $this->expectException(InvalidMessage::class);
        $this->expectExceptionMessageIsOrContains('The assertion is not a SAML 2.0 assertion with an ID.');

        Stage::processor()->process(base64_encode((string) $document->saveXML()), Stage::entra(wantAssertionsSigned: false), Stage::sp(), Stage::REQUEST);
    }

    public function testTheConsumerServiceMayBeOnLocalhost(): void
    {
        $sp = new ServiceProvider(Stage::SP, Url::fromString('https://localhost/acs', true), signing: Keys::pair('sp'));
        $document = Responses::entra(['destination' => 'https://localhost/acs', 'recipient' => 'https://localhost/acs']);

        self::assertSame('ada.lovelace@example.org', Stage::processor()->process($this->signed($document), Stage::entra(), $sp, Stage::REQUEST)->nameId);
    }

    public function testARepeatedIdInsideTheDecryptedAssertionIsRefused(): void
    {
        $this->expectException(InvalidSignature::class);
        $this->expectExceptionMessageIsOrContains('Two elements of the document carry the same ID.');

        Stage::processor()->process(base64_encode($this->encryptedResponse('<saml:Assertion ID="a" Version="2.0"><saml:Subject ID="a"/></saml:Assertion>')), Stage::entra(), Stage::sp(), Stage::REQUEST);
    }

    public function testAnEncryptedAssertionForAnotherKeyIsRefused(): void
    {
        $document = Responses::entra();
        TestEncrypter::encrypt(Responses::assertion($document), 'okta');

        $this->expectException(DecryptionFailed::class);
        Stage::processor()->process(base64_encode((string) $document->saveXML()), Stage::entra(), Stage::sp(), Stage::REQUEST);
    }

    /**
     * @return iterable<string, array{string, class-string<Throwable>, string}>
     */
    public static function documents(): iterable
    {
        yield 'not base64' => ['***', InvalidMessage::class, 'A posted message is base64 of 262144 bytes at most.'];
        yield 'not XML' => [base64_encode('hello'), InvalidXml::class, 'The document is not well-formed XML.'];
        yield 'not a response' => [base64_encode('<samlp:AuthnRequest xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" Version="2.0"/>'), InvalidMessage::class, 'The message is not a SAML 2.0 response.'];
        yield 'another version' => [base64_encode('<samlp:Response xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" Version="1.1"/>'), InvalidMessage::class, 'The message is not a SAML 2.0 response.'];
        yield 'no assertion' => [base64_encode('<samlp:Response xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" Version="2.0"/>'), InvalidMessage::class, 'A response holds exactly one assertion, as its own child.'];
        yield 'two assertions' => [base64_encode('<samlp:Response xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" Version="2.0"><saml:Assertion/><saml:EncryptedAssertion/></samlp:Response>'), InvalidMessage::class, 'A response holds exactly one assertion, as its own child.'];
        yield 'a nested assertion' => [base64_encode('<samlp:Response xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" Version="2.0"><samlp:Extensions><saml:Assertion/></samlp:Extensions></samlp:Response>'), InvalidMessage::class, 'A response holds exactly one assertion, as its own child.'];
        yield 'no status' => [base64_encode('<samlp:Response xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" Version="2.0"><saml:Assertion/></samlp:Response>'), InvalidMessage::class, 'The response has one status code.'];
        yield 'no status value' => [base64_encode('<samlp:Response xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" Version="2.0"><samlp:Status><samlp:StatusCode/></samlp:Status><saml:Assertion/></samlp:Response>'), InvalidMessage::class, 'The response has one status code.'];
        yield 'a second-level status' => [base64_encode('<samlp:Response xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" Version="2.0"><samlp:Status><samlp:StatusCode Value="urn:oasis:names:tc:SAML:2.0:status:Requester"><samlp:StatusCode Value="urn:oasis:names:tc:SAML:2.0:status:RequestDenied"/></samlp:StatusCode></samlp:Status><saml:Assertion/></samlp:Response>'), IdentityProviderRefused::class, 'The identity provider answered the status "urn:oasis:names:tc:SAML:2.0:status:Requester" (urn:oasis:names:tc:SAML:2.0:status:RequestDenied).'];
    }

    /**
     * @param class-string<Throwable> $exception
     */
    #[DataProvider('documents')]
    public function testADocumentThatIsNoResponseIsRefused(string $encoded, string $exception, string $message): void
    {
        $this->expectException($exception);
        $this->expectExceptionMessageIsOrContains($message);

        Stage::processor()->process($encoded, Stage::entra(), Stage::sp(), Stage::REQUEST);
    }

    public function testTheRefusalCarriesTheStatusCodes(): void
    {
        try {
            Stage::processor()->process(base64_encode('<samlp:Response xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" Version="2.0"><samlp:Status><samlp:StatusCode Value="urn:oasis:names:tc:SAML:2.0:status:Responder"><samlp:StatusCode Value="urn:oasis:names:tc:SAML:2.0:status:AuthnFailed"/></samlp:StatusCode></samlp:Status><saml:Assertion/></samlp:Response>'), Stage::entra(), Stage::sp(), Stage::REQUEST);
            self::fail('The status was accepted.');
        } catch (IdentityProviderRefused $identityProviderRefused) {
            self::assertSame('urn:oasis:names:tc:SAML:2.0:status:Responder', $identityProviderRefused->status);
            self::assertSame('urn:oasis:names:tc:SAML:2.0:status:AuthnFailed', $identityProviderRefused->subStatus);
        }
    }

    public function testTheBudgetOfThePolicyBoundsTheMessage(): void
    {
        $policy = new SecurityPolicy(budget: new XmlBudget(maxNodes: 50));

        $this->expectException(InvalidXml::class);
        $this->expectExceptionMessageIsOrContains('A document has 50 nodes at most.');

        Stage::processor(policy: $policy)->process(Stage::fixture('entra-signed-assertion.xml'), Stage::entra(), Stage::sp(), Stage::REQUEST);
    }

    public function testAMessageTooLargeForTheBudgetIsRefusedBeforeDecoding(): void
    {
        $policy = new SecurityPolicy(budget: new XmlBudget(maxBytes: 1000));

        $this->expectException(InvalidMessage::class);
        $this->expectExceptionMessageIsOrContains('A posted message is base64 of 1000 bytes at most.');

        Stage::processor(policy: $policy)->process(Stage::fixture('entra-signed-assertion.xml'), Stage::entra(), Stage::sp(), Stage::REQUEST);
    }

    public function testAClockSkewOfZeroIsStrict(): void
    {
        $policy = new SecurityPolicy(clockSkew: 0);

        $this->expectExceptionMessageIsOrContains('The assertion has expired.');
        Stage::processor(policy: $policy)->process($this->signed(Responses::entra(['notOnOrAfter' => '2026-11-19T12:01:00Z'])), Stage::entra(), Stage::sp(), Stage::REQUEST);
    }

    private function signed(DOMDocument $document): string
    {
        TestSigner::sign(Responses::assertion($document), 'entra');

        return base64_encode((string) $document->saveXML());
    }

    private function encryptedResponse(string $plain, ?DOMDocument $document = null): string
    {
        $key = random_bytes(32);
        $iv = random_bytes(16);
        $cipher = $iv.openssl_encrypt($plain, 'aes-256-cbc', $key, \OPENSSL_RAW_DATA, $iv);
        openssl_public_encrypt($key, $wrapped, Keys::pem('sp.crt'), \OPENSSL_PKCS1_OAEP_PADDING);
        $document ??= Responses::entra();
        $assertion = Responses::assertion($document);
        $encrypted = Dom::load('<saml:EncryptedAssertion xmlns:saml="'.Responses::ASSERTION.'" xmlns:xenc="http://www.w3.org/2001/04/xmlenc#" xmlns:ds="http://www.w3.org/2000/09/xmldsig#"><xenc:EncryptedData><xenc:EncryptionMethod Algorithm="http://www.w3.org/2001/04/xmlenc#aes256-cbc"/><ds:KeyInfo><xenc:EncryptedKey><xenc:EncryptionMethod Algorithm="http://www.w3.org/2001/04/xmlenc#rsa-oaep-mgf1p"/><xenc:CipherData><xenc:CipherValue>'.base64_encode(\is_string($wrapped) ? $wrapped : '').'</xenc:CipherValue></xenc:CipherData></xenc:EncryptedKey></ds:KeyInfo><xenc:CipherData><xenc:CipherValue>'.base64_encode($cipher).'</xenc:CipherValue></xenc:CipherData></xenc:EncryptedData></saml:EncryptedAssertion>');
        $assertion->parentNode?->replaceChild($document->importNode(Responses::root($encrypted), true), $assertion);

        return (string) $document->saveXML();
    }
}
