<?php

declare(strict_types=1);

namespace Phil\SamlSp\Tests\Unit\Signature;

use DOMDocument;
use DOMElement;
use DOMNode;
use InvalidArgumentException;
use Phil\SamlSp\Exception\InvalidSignature;
use Phil\SamlSp\Signature\DigestAlgorithm;
use Phil\SamlSp\Signature\SignatureAlgorithm;
use Phil\SamlSp\Signature\SignaturePolicy;
use Phil\SamlSp\Signature\SignatureVerifier;
use Phil\SamlSp\Signature\Signer;
use Phil\SamlSp\Tests\Support\Dom;
use Phil\SamlSp\Tests\Support\Keys;
use Phil\SamlSp\Tests\Support\Responses;
use Phil\SamlSp\Tests\Support\TestSigner;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SignatureVerifier::class)]
#[CoversClass(Signer::class)]
#[CoversClass(SignatureAlgorithm::class)]
#[CoversClass(DigestAlgorithm::class)]
#[CoversClass(SignaturePolicy::class)]
final class SignatureTest extends TestCase
{
    public function testWhatTheSignerSignsVerifies(): void
    {
        foreach ([SignatureAlgorithm::RsaSha256, SignatureAlgorithm::RsaSha384, SignatureAlgorithm::RsaSha512] as $algorithm) {
            $document = Responses::entra();
            $assertion = Responses::assertion($document);
            new Signer(Keys::pair('sp'), $algorithm)->sign($assertion);

            new SignatureVerifier()->verify($assertion, [Keys::certificate('entra'), Keys::certificate('sp')]);
            self::assertSame($algorithm->value, Responses::first($document, '//ds:SignatureMethod')->getAttribute('Algorithm'));
            self::assertSame($algorithm->digest()->value, Responses::first($document, '//ds:DigestMethod')->getAttribute('Algorithm'));
        }
    }

    public function testTheSignatureGoesRightAfterTheIssuer(): void
    {
        $document = Responses::entra();
        $assertion = Responses::assertion($document);
        new Signer(Keys::pair('sp'))->sign($assertion);

        $children = array_values(array_filter(iterator_to_array($assertion->childNodes), static fn (mixed $node): bool => $node instanceof DOMElement));
        self::assertSame(['Issuer', 'Signature', 'Subject'], array_map(static fn (DOMElement $element): string => (string) $element->localName, \array_slice($children, 0, 3)));
        self::assertSame(Keys::certificate('sp')->base64(), Responses::first($document, '//ds:X509Certificate')->textContent);
        self::assertSame('#'.$assertion->getAttribute('ID'), Responses::first($document, '//ds:Reference')->getAttribute('URI'));
        self::assertSame(2, Responses::first($document, '//ds:Transforms')->childNodes->length);
        self::assertTrue(SignatureVerifier::hasSignature($assertion));
        self::assertFalse(SignatureVerifier::hasSignature(Responses::root($document)));
    }

    public function testWithoutAnIssuerTheSignatureComesFirst(): void
    {
        $document = new DOMDocument();
        $document->loadXML('<a ID="_a"><b/></a>');

        $root = Responses::root($document);
        new Signer(Keys::pair('sp'))->sign($root);

        self::assertSame('Signature', $root->firstChild?->localName);
        new SignatureVerifier()->verify($root, [Keys::certificate('sp')]);
    }

    public function testTheSignerNeverSignsWithSha1(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('The library never signs with SHA-1.');

        new Signer(Keys::pair('sp'), SignatureAlgorithm::RsaSha1);
    }

    public function testTheSignerNeedsAnId(): void
    {
        $document = new DOMDocument();
        $document->loadXML('<a ID=""/>');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('The signed element has an ID and a document.');

        new Signer(Keys::pair('sp'))->sign(Responses::root($document));
    }

    public function testTheSignerNeedsAnIdAttribute(): void
    {
        $document = new DOMDocument();
        $document->loadXML('<a/>');

        $this->expectExceptionMessageIsOrContains('The signed element has an ID and a document.');

        new Signer(Keys::pair('sp'))->sign(Responses::root($document));
    }

    public function testSha1IsVerifiedOnlyWhenAllowed(): void
    {
        $document = Responses::entra();
        $assertion = Responses::assertion($document);
        TestSigner::sign($assertion, 'entra', 'ds', [], 'http://www.w3.org/2000/09/xmldsig#rsa-sha1', 'http://www.w3.org/2001/04/xmlenc#sha256');

        new SignatureVerifier(new SignaturePolicy(allowSha1: true))->verify($assertion, [Keys::certificate('entra')]);

        $this->expectException(InvalidSignature::class);
        $this->expectExceptionMessageIsOrContains('The signature algorithm "http://www.w3.org/2000/09/xmldsig#rsa-sha1" is refused.');
        new SignatureVerifier()->verify($assertion, [Keys::certificate('entra')]);
    }

    public function testASha1DigestIsVerifiedOnlyWhenAllowed(): void
    {
        $document = Responses::entra();
        $assertion = Responses::assertion($document);
        TestSigner::sign($assertion, 'entra', 'ds', [], 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256', 'http://www.w3.org/2000/09/xmldsig#sha1');

        new SignatureVerifier(new SignaturePolicy(allowSha1: true))->verify($assertion, [Keys::certificate('entra')]);

        $this->expectExceptionMessageIsOrContains('The digest algorithm "http://www.w3.org/2000/09/xmldsig#sha1" is refused.');
        new SignatureVerifier()->verify($assertion, [Keys::certificate('entra')]);
    }

    public function testASha512DigestWithAPrefixList(): void
    {
        $document = Responses::okta();
        $assertion = Responses::assertion($document);
        TestSigner::sign($assertion, 'okta', 'ds', ['xs', 'xsi'], 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha512', 'http://www.w3.org/2001/04/xmlenc#sha512');

        new SignatureVerifier()->verify($assertion, [Keys::certificate('okta')]);
        self::assertTrue(SignatureVerifier::hasSignature($assertion));
    }

    public function testTextAndCommentsAmongTheTransformsAreSkipped(): void
    {
        $document = Responses::entra();
        $assertion = Responses::assertion($document);
        TestSigner::sign($assertion, 'entra', 'ds', [], indent: true);

        new SignatureVerifier()->verify($assertion, [Keys::certificate('entra')]);
        self::assertSame(4, Responses::first($document, '//ds:Transforms')->childNodes->length);
    }

    public function testACommentInTheSignedElementIsLeftOutOfTheDigest(): void
    {
        $document = Responses::entra();
        $assertion = Responses::assertion($document);
        $assertion->appendChild($document->createComment('an identity provider may comment'));
        new Signer(Keys::pair('entra'))->sign($assertion);

        new SignatureVerifier()->verify($assertion, [Keys::certificate('entra')]);
        self::assertTrue(SignatureVerifier::hasSignature($assertion));
    }

    public function testThePrefixListMayBeSurroundedBySpaces(): void
    {
        $document = Responses::okta();
        $assertion = Responses::assertion($document);
        TestSigner::sign($assertion, 'okta', 'ds', ['xs'], prefixListText: "\n xs \n");

        new SignatureVerifier()->verify($assertion, [Keys::certificate('okta')]);
        self::assertTrue(SignatureVerifier::hasSignature($assertion));
    }

    public function testTheInclusivePrefixesChangeTheDigest(): void
    {
        $document = Responses::okta();
        $assertion = Responses::assertion($document);
        TestSigner::sign($assertion, 'okta', 'ds', ['xs']);
        $inclusive = Responses::first($document, '//ds:Transform[2]/*');
        $inclusive->parentNode?->removeChild($inclusive);

        $this->expectExceptionMessageIsOrContains('The digest of the signed element does not match its reference.');
        new SignatureVerifier()->verify($assertion, [Keys::certificate('okta')]);
    }

    public function testOneOfSeveralCertificatesVerifies(): void
    {
        $document = Responses::entra();
        $assertion = Responses::assertion($document);
        TestSigner::sign($assertion, 'entra');

        new SignatureVerifier()->verify($assertion, [Keys::certificate('okta'), Keys::certificate('entra')]);

        $this->expectExceptionMessageIsOrContains('The signature does not verify with the certificates of the identity provider.');
        new SignatureVerifier()->verify($assertion, [Keys::certificate('okta')]);
    }

    public function testNoCertificateVerifiesNothing(): void
    {
        $document = Responses::entra();
        $assertion = Responses::assertion($document);
        TestSigner::sign($assertion, 'entra');

        $this->expectExceptionMessageIsOrContains('The signature does not verify with the certificates of the identity provider.');
        new SignatureVerifier()->verify($assertion, []);
    }

    public function testAShortKeyIsRefused(): void
    {
        $document = Responses::entra();
        $assertion = Responses::assertion($document);
        TestSigner::sign($assertion, 'weak');

        new SignatureVerifier(new SignaturePolicy(minRsaBits: 1024))->verify($assertion, [Keys::certificate('weak')]);

        $this->expectExceptionMessageIsOrContains('A trusted certificate holds an RSA key shorter than 2048 bits.');
        new SignatureVerifier()->verify($assertion, [Keys::certificate('weak')]);
    }

    /**
     * @return iterable<string, array{callable(DOMDocument): void, string}>
     */
    public static function tampered(): iterable
    {
        $remove = static fn (string $query): callable => static function (DOMDocument $document) use ($query): void {
            $node = Responses::first($document, $query);
            $node->parentNode?->removeChild($node);
        };
        $set = static fn (string $query, string $attribute, string $value): callable => static function (DOMDocument $document) use ($query, $attribute, $value): void {
            Responses::first($document, $query)->setAttribute($attribute, $value);
        };
        $duplicate = static fn (string $query): callable => static function (DOMDocument $document) use ($query): void {
            $node = Responses::first($document, $query);
            $copy = $node->cloneNode(true);
            Assert::assertInstanceOf(DOMNode::class, $copy);
            $node->parentNode?->appendChild($copy);
        };
        $text = static fn (string $query, string $value): callable => static function (DOMDocument $document) use ($query, $value): void {
            Responses::first($document, $query)->textContent = $value;
        };
        $transform = static fn (string $algorithm): callable => static function (DOMDocument $document) use ($algorithm): void {
            $transform = Dom::create($document, TestSigner::DSIG, 'ds:Transform');
            $transform->setAttribute('Algorithm', $algorithm);
            Responses::first($document, '//ds:Transforms')->appendChild($transform);
        };

        yield 'no signature' => [$remove('//ds:Signature'), 'The element carries no signature, or more than one.'];
        yield 'two signatures' => [$duplicate('//ds:Signature'), 'The element carries no signature, or more than one.'];
        yield 'no SignedInfo' => [$remove('//ds:SignedInfo'), 'A signature has one SignedInfo.'];
        yield 'no SignatureValue' => [$remove('//ds:SignatureValue'), 'A signature has one SignatureValue.'];
        yield 'two references' => [$duplicate('//ds:Reference'), 'A signature has exactly one Reference.'];
        yield 'no reference' => [$remove('//ds:Reference'), 'A signature has exactly one Reference.'];
        yield 'another reference' => [$set('//ds:Reference', 'URI', '#_other'), 'The reference of the signature is not the element it signs.'];
        yield 'empty reference' => [$set('//ds:Reference', 'URI', ''), 'The reference of the signature is not the element it signs.'];
        yield 'reference without hash' => [$set('//ds:Reference', 'URI', '_9a6c6f9b-2f1e-4a43-9d36-7fd3e5f4b200'), 'The reference of the signature is not the element it signs.'];
        yield 'inclusive canonicalisation' => [$set('//ds:CanonicalizationMethod', 'Algorithm', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315'), 'The SignedInfo is canonicalised with exclusive C14N without comments.'];
        yield 'with comments' => [$set('//ds:CanonicalizationMethod', 'Algorithm', 'http://www.w3.org/2001/10/xml-exc-c14n#WithComments'), 'The SignedInfo is canonicalised with exclusive C14N without comments.'];
        yield 'no canonicalisation' => [$remove('//ds:CanonicalizationMethod'), 'A SignedInfo has one CanonicalizationMethod.'];
        yield 'no signature method' => [$remove('//ds:SignatureMethod'), 'A signature has one SignatureMethod.'];
        yield 'unknown signature' => [$set('//ds:SignatureMethod', 'Algorithm', 'http://www.w3.org/2001/04/xmldsig-more#hmac-sha256'), 'The signature algorithm "http://www.w3.org/2001/04/xmldsig-more#hmac-sha256" is refused.'];
        yield 'no transforms' => [$remove('//ds:Transforms'), 'A reference has its transforms.'];
        yield 'an XPath transform' => [$transform('http://www.w3.org/TR/1999/REC-xpath-19991116'), 'The transforms of a reference are the enveloped signature and exclusive C14N, once each.'];
        yield 'a repeated transform' => [$transform('http://www.w3.org/2000/09/xmldsig#enveloped-signature'), 'The transforms of a reference are the enveloped signature and exclusive C14N, once each.'];
        yield 'one transform' => [$remove('//ds:Transform[1]'), 'The transforms of a reference are the enveloped signature and exclusive C14N.'];
        yield 'no digest method' => [$remove('//ds:DigestMethod'), 'A signature has one DigestMethod.'];
        yield 'unknown digest' => [$set('//ds:DigestMethod', 'Algorithm', 'http://www.w3.org/2001/04/xmldsig-more#md5'), 'The digest algorithm "http://www.w3.org/2001/04/xmldsig-more#md5" is refused.'];
        yield 'no digest value' => [$remove('//ds:DigestValue'), 'A reference has one DigestValue.'];
        yield 'bad digest value' => [$text('//ds:DigestValue', '***'), 'A digest or a signature value is not base64.'];
        yield 'other digest value' => [$text('//ds:DigestValue', base64_encode(str_repeat('a', 32))), 'The digest of the signed element does not match its reference.'];
        yield 'bad signature value' => [$text('//ds:SignatureValue', ''), 'A digest or a signature value is not base64.'];
        yield 'other signature value' => [$text('//ds:SignatureValue', base64_encode(str_repeat('a', 256))), 'The signature does not verify with the certificates of the identity provider.'];
        yield 'signed info changed' => [$set('//ds:Reference', 'Id', 'x'), 'The signature does not verify with the certificates of the identity provider.'];
        yield 'content changed' => [$text('//saml:NameID', 'mallory@example.org'), 'The digest of the signed element does not match its reference.'];
    }

    /**
     * @param callable(DOMDocument): void $tamper
     */
    #[DataProvider('tampered')]
    public function testATamperedSignatureIsRefused(callable $tamper, string $message): void
    {
        $document = Responses::entra();
        $assertion = Responses::assertion($document);
        TestSigner::sign($assertion, 'entra');
        $tamper($document);

        $this->expectException(InvalidSignature::class);
        $this->expectExceptionMessageIsOrContains($message);

        new SignatureVerifier()->verify($assertion, [Keys::certificate('entra')]);
    }

    public function testAnElementWithoutIdIsRefused(): void
    {
        $document = Responses::entra();
        $assertion = Responses::assertion($document);
        TestSigner::sign($assertion, 'entra');
        $assertion->removeAttribute('ID');

        $this->expectExceptionMessageIsOrContains('The reference of the signature is not the element it signs.');
        new SignatureVerifier()->verify($assertion, [Keys::certificate('entra')]);
    }

    public function testAnIdResolvingElsewhereIsRefused(): void
    {
        $document = Responses::entra();
        $assertion = Responses::assertion($document);
        TestSigner::sign($assertion, 'entra');
        $free = new DOMDocument()->importNode($assertion, true);
        $this->assertInstanceOf(DOMElement::class, $free);

        $this->expectExceptionMessageIsOrContains('The reference of the signature resolves to another element.');
        new SignatureVerifier()->verify($free, [Keys::certificate('entra')]);
    }

    public function testTheDocumentIsLeftAsItWas(): void
    {
        $document = Responses::entra();
        $assertion = Responses::assertion($document);
        TestSigner::sign($assertion, 'entra', '');
        $before = $document->saveXML();

        new SignatureVerifier()->verify($assertion, [Keys::certificate('entra')]);

        self::assertSame($before, $document->saveXML());
    }

    public function testTheDocumentIsLeftAsItWasWhenTheDigestFails(): void
    {
        $document = Responses::entra();
        $assertion = Responses::assertion($document);
        TestSigner::sign($assertion, 'entra', '');
        Responses::first($document, '//saml:NameID')->textContent = 'x';
        $before = $document->saveXML();

        try {
            new SignatureVerifier()->verify($assertion, [Keys::certificate('entra')]);
            self::fail('The digest matched.');
        } catch (InvalidSignature) {
        }

        self::assertSame($before, $document->saveXML());
    }

    public function testTheAlgorithms(): void
    {
        self::assertSame(\OPENSSL_ALGO_SHA1, SignatureAlgorithm::RsaSha1->openssl());
        self::assertSame(\OPENSSL_ALGO_SHA256, SignatureAlgorithm::RsaSha256->openssl());
        self::assertSame(\OPENSSL_ALGO_SHA384, SignatureAlgorithm::RsaSha384->openssl());
        self::assertSame(\OPENSSL_ALGO_SHA512, SignatureAlgorithm::RsaSha512->openssl());
        self::assertSame(DigestAlgorithm::Sha1, SignatureAlgorithm::RsaSha1->digest());
        self::assertSame(DigestAlgorithm::Sha384, SignatureAlgorithm::RsaSha384->digest());
        self::assertSame(DigestAlgorithm::Sha512, SignatureAlgorithm::RsaSha512->digest());
        self::assertSame(['sha1', 'sha256', 'sha384', 'sha512'], array_map(static fn (DigestAlgorithm $digest): string => $digest->hash(), DigestAlgorithm::cases()));
        self::assertSame(SignatureAlgorithm::RsaSha384, SignatureAlgorithm::fromUri('http://www.w3.org/2001/04/xmldsig-more#rsa-sha384', new SignaturePolicy()));
        self::assertSame(DigestAlgorithm::Sha384, DigestAlgorithm::fromUri('http://www.w3.org/2001/04/xmldsig-more#sha384', new SignaturePolicy()));
    }

    public function testThePolicyIsBounded(): void
    {
        self::assertSame(1024, new SignaturePolicy(minRsaBits: 1024)->minRsaBits);
        self::assertSame(16384, new SignaturePolicy(minRsaBits: 16384)->minRsaBits);
        self::assertFalse(new SignaturePolicy()->allowSha1);
        self::assertSame(2048, new SignaturePolicy()->minRsaBits);

        foreach ([1023, 16385] as $bits) {
            try {
                new SignaturePolicy(minRsaBits: $bits);
                self::fail('The policy accepted '.$bits.' bits.');
            } catch (InvalidArgumentException $exception) {
                self::assertSame(\sprintf('The smallest RSA key is between 1024 and 16384 bits; %d is not.', $bits), $exception->getMessage());
            }
        }
    }
}
