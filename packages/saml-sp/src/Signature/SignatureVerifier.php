<?php

declare(strict_types=1);

namespace Phil\SamlSp\Signature;

use DOMElement;
use Phil\SamlSp\Exception\InvalidSignature;
use Phil\SamlSp\Key\Certificate;
use Phil\SamlSp\Xml\Base64;
use Phil\SamlSp\Xml\Elements;
use Phil\SamlSp\Xml\IdMap;
use Phil\SamlSp\Xml\Ns;

/**
 * Verifies the enveloped XML signature of one element, as SAML signs a response, an assertion or metadata, and nothing
 * looser:
 *
 * - the element has exactly one `ds:Signature` child, whose `SignedInfo` has exactly one `Reference`, whose `URI` is
 *   `#` and the `ID` of that element; every `ID` of the document is unique, and the `ID` resolves to that very element,
 *   so that what is verified is what the caller goes on to read;
 * - the canonicalisation is exclusive C14N without comments, the transforms are the enveloped signature and exclusive
 *   C14N, nothing else (no XPath, no XSLT);
 * - the digest and the signature use SHA-256 or above, SHA-1 only when the policy allows it;
 * - the signature verifies with one of the trusted certificates, never with a key the signature carries in `KeyInfo`;
 *   an RSA key shorter than the policy's is refused.
 */
final readonly class SignatureVerifier
{
    public function __construct(private SignaturePolicy $policy = new SignaturePolicy())
    {
    }

    public static function hasSignature(DOMElement $element): bool
    {
        return [] !== Elements::all($element, Ns::DSIG, 'Signature');
    }

    /**
     * @param list<Certificate> $certificates the trusted certificates, one of which made the signature
     */
    public function verify(DOMElement $element, array $certificates): void
    {
        $signature = Elements::one($element, Ns::DSIG, 'Signature') ?? throw InvalidSignature::because('The element carries no signature, or more than one.');
        $signedInfo = Elements::one($signature, Ns::DSIG, 'SignedInfo') ?? throw InvalidSignature::because('A signature has one SignedInfo.');
        $value = Elements::one($signature, Ns::DSIG, 'SignatureValue') ?? throw InvalidSignature::because('A signature has one SignatureValue.');

        $this->checkTarget($element, $signedInfo);
        $canonicalPrefixes = $this->canonicalization($signedInfo);
        $algorithm = SignatureAlgorithm::fromUri($this->algorithmOf($signedInfo, 'SignatureMethod'), $this->policy);
        [$digest, $expected, $prefixes] = $this->reference($signedInfo);

        $canonicalSignedInfo = $signedInfo->C14N(true, false, null, $canonicalPrefixes);

        if (false === $canonicalSignedInfo) {
            throw InvalidSignature::because('The SignedInfo cannot be canonicalised.');
        }

        if (!hash_equals($expected, hash($digest->hash(), $this->canonicalWithout($element, $signature, $prefixes), true))) {
            throw InvalidSignature::because('The digest of the signed element does not match its reference.');
        }

        $this->checkValue($canonicalSignedInfo, $this->decode(Elements::text($value)), $algorithm, $certificates);
    }

    private function checkTarget(DOMElement $element, DOMElement $signedInfo): void
    {
        $id = Elements::attribute($element, 'ID');
        $reference = Elements::one($signedInfo, Ns::DSIG, 'Reference') ?? throw InvalidSignature::because('A signature has exactly one Reference.');

        if (null === $id || '' === $id || '#'.$id !== Elements::attribute($reference, 'URI')) {
            throw InvalidSignature::because('The reference of the signature is not the element it signs.');
        }

        $document = $element->ownerDocument ?? throw InvalidSignature::because('The signed element has no document.');

        if (true !== IdMap::of($document)->element($id)?->isSameNode($element)) {
            throw InvalidSignature::because('The reference of the signature resolves to another element.');
        }
    }

    /**
     * @return list<string>|null the prefixes of `InclusiveNamespaces`, null when there are none
     */
    private function canonicalization(DOMElement $signedInfo): ?array
    {
        $method = Elements::one($signedInfo, Ns::DSIG, 'CanonicalizationMethod') ?? throw InvalidSignature::because('A SignedInfo has one CanonicalizationMethod.');

        if (Ns::EXC_C14N !== Elements::attribute($method, 'Algorithm')) {
            throw InvalidSignature::because('The SignedInfo is canonicalised with exclusive C14N without comments.');
        }

        return $this->prefixes($method);
    }

    /**
     * @return array{DigestAlgorithm, string, list<string>|null}
     */
    private function reference(DOMElement $signedInfo): array
    {
        $reference = Elements::one($signedInfo, Ns::DSIG, 'Reference') ?? throw InvalidSignature::because('A signature has exactly one Reference.');
        $transforms = Elements::one($reference, Ns::DSIG, 'Transforms') ?? throw InvalidSignature::because('A reference has its transforms.');
        $seen = [];
        $prefixes = null;

        foreach ($transforms->childNodes as $transform) {
            if (!$transform instanceof DOMElement) {
                continue;
            }

            $algorithm = Elements::is($transform, Ns::DSIG, 'Transform') ? Elements::attribute($transform, 'Algorithm') : null;

            if (!\in_array($algorithm, [Ns::ENVELOPED, Ns::EXC_C14N], true) || isset($seen[$algorithm])) {
                throw InvalidSignature::because('The transforms of a reference are the enveloped signature and exclusive C14N, once each.');
            }

            $seen[$algorithm] = true;
            $prefixes = Ns::EXC_C14N === $algorithm ? $this->prefixes($transform) : $prefixes;
        }

        if (2 !== \count($seen)) {
            throw InvalidSignature::because('The transforms of a reference are the enveloped signature and exclusive C14N.');
        }

        $digest = DigestAlgorithm::fromUri($this->algorithmOf($reference, 'DigestMethod'), $this->policy);
        $value = Elements::one($reference, Ns::DSIG, 'DigestValue') ?? throw InvalidSignature::because('A reference has one DigestValue.');

        return [$digest, $this->decode(Elements::text($value)), $prefixes];
    }

    /**
     * @param list<Certificate> $certificates
     */
    private function checkValue(string $signed, string $value, SignatureAlgorithm $algorithm, array $certificates): void
    {
        foreach ($certificates as $certificate) {
            if ($certificate->rsaBits() < $this->policy->minRsaBits) {
                throw InvalidSignature::because(\sprintf('A trusted certificate holds an RSA key shorter than %d bits.', $this->policy->minRsaBits));
            }

            if (1 === openssl_verify($signed, $value, $certificate->publicKey(), $algorithm->openssl())) {
                return;
            }
        }

        throw InvalidSignature::because('The signature does not verify with the certificates of the identity provider.');
    }

    /**
     * @param list<string>|null $prefixes
     */
    private function canonicalWithout(DOMElement $element, DOMElement $signature, ?array $prefixes): string
    {
        $next = $signature->nextSibling;
        $element->removeChild($signature);

        try {
            $canonical = $element->C14N(true, false, null, $prefixes);
        } finally {
            $element->insertBefore($signature, $next);
        }

        if (false === $canonical) {
            throw InvalidSignature::because('The signed element cannot be canonicalised.');
        }

        return $canonical;
    }

    private function algorithmOf(DOMElement $parent, string $name): string
    {
        $method = Elements::one($parent, Ns::DSIG, $name) ?? throw InvalidSignature::because(\sprintf('A signature has one %s.', $name));

        return (string) Elements::attribute($method, 'Algorithm');
    }

    /**
     * @return list<string>|null
     */
    private function prefixes(DOMElement $method): ?array
    {
        $inclusive = Elements::one($method, Ns::EXC_C14N, 'InclusiveNamespaces');
        $list = $inclusive instanceof DOMElement ? trim((string) Elements::attribute($inclusive, 'PrefixList')) : '';

        return '' === $list ? null : explode(' ', (string) preg_replace('/\s+/', ' ', $list));
    }

    private function decode(string $base64): string
    {
        return Base64::decode($base64) ?? throw InvalidSignature::because('A digest or a signature value is not base64.');
    }
}
