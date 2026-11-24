<?php

declare(strict_types=1);

namespace Phil\SamlSp\Tests\Support;

use DOMDocument;
use DOMElement;
use RuntimeException;

/**
 * A second signer, written for the tests apart from the library's: it signs with any prefix for the signature — none,
 * as Microsoft Entra ID writes it, or `ds`, as Okta does —, with an `InclusiveNamespaces` prefix list, and with any
 * algorithm, SHA-1 included, so that the verifier is tried on signatures the library would never write.
 */
final class TestSigner
{
    public const string DSIG = 'http://www.w3.org/2000/09/xmldsig#';

    public const string EXC = 'http://www.w3.org/2001/10/xml-exc-c14n#';

    /**
     * @param list<string> $prefixList     the prefixes of the `InclusiveNamespaces` of the reference
     * @param string|null  $prefixListText the text of the PrefixList attribute when it is not the prefixes joined by a space
     */
    public static function sign(
        DOMElement $element,
        string $keyName,
        string $prefix = 'ds',
        array $prefixList = [],
        string $signatureAlgorithm = 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256',
        string $digestAlgorithm = 'http://www.w3.org/2001/04/xmlenc#sha256',
        bool $indent = false,
        ?string $prefixListText = null,
    ): void {
        $document = $element->ownerDocument ?? throw new RuntimeException('The element has no document.');
        $hash = ['http://www.w3.org/2000/09/xmldsig#sha1' => 'sha1', 'http://www.w3.org/2001/04/xmlenc#sha256' => 'sha256', 'http://www.w3.org/2001/04/xmlenc#sha512' => 'sha512'][$digestAlgorithm];
        $openssl = ['http://www.w3.org/2000/09/xmldsig#rsa-sha1' => \OPENSSL_ALGO_SHA1, 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256' => \OPENSSL_ALGO_SHA256, 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha512' => \OPENSSL_ALGO_SHA512][$signatureAlgorithm];
        $digest = base64_encode((string) openssl_digest((string) $element->C14N(true, false, null, [] === $prefixList ? null : $prefixList), $hash, true));

        $name = static fn (string $local): string => '' === $prefix ? $local : $prefix.':'.$local;
        $signature = self::add($document, null, $name('Signature'));
        $signedInfo = self::add($document, $signature, $name('SignedInfo'));
        self::add($document, $signedInfo, $name('CanonicalizationMethod'))->setAttribute('Algorithm', self::EXC);
        self::add($document, $signedInfo, $name('SignatureMethod'))->setAttribute('Algorithm', $signatureAlgorithm);
        $reference = self::add($document, $signedInfo, $name('Reference'));
        $reference->setAttribute('URI', '#'.$element->getAttribute('ID'));

        $transforms = self::add($document, $reference, $name('Transforms'));

        if ($indent) {
            $transforms->appendChild($document->createTextNode("\n    "));
            $transforms->appendChild($document->createComment(' the transforms '));
        }

        self::add($document, $transforms, $name('Transform'))->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#enveloped-signature');
        $exclusive = self::add($document, $transforms, $name('Transform'));
        $exclusive->setAttribute('Algorithm', self::EXC);

        if ([] !== $prefixList) {
            $inclusive = Dom::create($document, self::EXC, 'ec:InclusiveNamespaces');
            $inclusive->setAttribute('PrefixList', $prefixListText ?? implode(' ', $prefixList));
            $exclusive->appendChild($inclusive);
        }

        self::add($document, $reference, $name('DigestMethod'))->setAttribute('Algorithm', $digestAlgorithm);
        self::add($document, $reference, $name('DigestValue'))->appendChild($document->createTextNode($digest));
        $value = self::add($document, $signature, $name('SignatureValue'));
        $keyInfo = self::add($document, $signature, $name('KeyInfo'));
        $data = self::add($document, $keyInfo, $name('X509Data'));
        self::add($document, $data, $name('X509Certificate'))->appendChild($document->createTextNode(Keys::certificate($keyName)->base64()));

        $issuer = null;

        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement && 'Issuer' === $child->localName) {
                $issuer = $child;

                break;
            }
        }

        $element->insertBefore($signature, $issuer instanceof DOMElement ? $issuer->nextSibling : $element->firstChild);
        openssl_sign((string) $signedInfo->C14N(true, false), $signed, Keys::pem($keyName.'.key'), $openssl);
        $value->appendChild($document->createTextNode(chunk_split(base64_encode(\is_string($signed) ? $signed : ''), 76, "\n")));
    }

    private static function add(DOMDocument $document, ?DOMElement $parent, string $name): DOMElement
    {
        $element = Dom::create($document, self::DSIG, $name);
        $parent?->appendChild($element);

        return $element;
    }
}
