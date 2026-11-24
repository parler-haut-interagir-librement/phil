<?php

declare(strict_types=1);

namespace Phil\SamlSp\Signature;

use DOMDocument;
use DOMElement;
use DOMNode;
use InvalidArgumentException;
use Phil\SamlSp\Exception\InvalidKey;
use Phil\SamlSp\Key\KeyPair;
use Phil\SamlSp\Xml\Base64;
use Phil\SamlSp\Xml\Elements;
use Phil\SamlSp\Xml\Ns;
use Phil\SamlSp\Xml\XmlWriter;

/**
 * Signs an element as SAML wants it: an enveloped signature placed right after its `Issuer`, exclusive C14N, a digest
 * matching the signature algorithm (SHA-256 by default), and the certificate in `KeyInfo`.
 */
final readonly class Signer
{
    public function __construct(
        private KeyPair $keys,
        private SignatureAlgorithm $algorithm = SignatureAlgorithm::RsaSha256,
    ) {
        if (SignatureAlgorithm::RsaSha1 === $algorithm) {
            throw new InvalidArgumentException('The library never signs with SHA-1.');
        }
    }

    public function sign(DOMElement $element): void
    {
        $id = Elements::attribute($element, 'ID');
        $document = $element->ownerDocument;

        if (null === $id || '' === $id || !$document instanceof DOMDocument) {
            throw new InvalidArgumentException('The signed element has an ID and a document.');
        }

        $canonical = $element->C14N(true, false);
        $digest = $this->algorithm->digest();
        $signature = XmlWriter::element($document, Ns::DSIG, 'ds:Signature');
        $signedInfo = XmlWriter::child($signature, Ns::DSIG, 'ds:SignedInfo');
        XmlWriter::child($signedInfo, Ns::DSIG, 'ds:CanonicalizationMethod', ['Algorithm' => Ns::EXC_C14N]);
        XmlWriter::child($signedInfo, Ns::DSIG, 'ds:SignatureMethod', ['Algorithm' => $this->algorithm->value]);
        $reference = XmlWriter::child($signedInfo, Ns::DSIG, 'ds:Reference', ['URI' => '#'.$id]);
        $transforms = XmlWriter::child($reference, Ns::DSIG, 'ds:Transforms');
        XmlWriter::child($transforms, Ns::DSIG, 'ds:Transform', ['Algorithm' => Ns::ENVELOPED]);
        XmlWriter::child($transforms, Ns::DSIG, 'ds:Transform', ['Algorithm' => Ns::EXC_C14N]);
        XmlWriter::child($reference, Ns::DSIG, 'ds:DigestMethod', ['Algorithm' => $digest->value]);
        XmlWriter::child($reference, Ns::DSIG, 'ds:DigestValue', [], Base64::encode(hash($digest->hash(), (string) $canonical, true)));
        $value = XmlWriter::child($signature, Ns::DSIG, 'ds:SignatureValue');
        $x509 = XmlWriter::child(XmlWriter::child($signature, Ns::DSIG, 'ds:KeyInfo'), Ns::DSIG, 'ds:X509Data');
        XmlWriter::child($x509, Ns::DSIG, 'ds:X509Certificate', [], $this->keys->certificate->base64());

        $element->insertBefore($signature, $this->afterIssuer($element));

        if (!openssl_sign((string) $signedInfo->C14N(true, false), $signed, $this->keys->privateKey->key(), $this->algorithm->openssl()) || !\is_string($signed)) {
            throw InvalidKey::because('The private key cannot sign.');
        }

        $value->appendChild($document->createTextNode(Base64::encode($signed)));
    }

    private function afterIssuer(DOMElement $element): ?DOMNode
    {
        $issuer = Elements::one($element, Ns::ASSERTION, 'Issuer');

        return $issuer instanceof DOMElement ? $issuer->nextSibling : $element->firstChild;
    }
}
