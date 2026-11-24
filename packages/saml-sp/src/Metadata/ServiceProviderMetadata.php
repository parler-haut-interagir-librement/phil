<?php

declare(strict_types=1);

namespace Phil\SamlSp\Metadata;

use DateTimeInterface;
use DOMElement;
use Phil\SamlSp\Binding\Binding;
use Phil\SamlSp\Encryption\DataEncryption;
use Phil\SamlSp\Key\Certificate;
use Phil\SamlSp\Key\KeyPair;
use Phil\SamlSp\Provider\ServiceProvider;
use Phil\SamlSp\Xml\Instant;
use Phil\SamlSp\Xml\Ns;
use Phil\SamlSp\Xml\XmlWriter;
use Phil\Url\Url;

/**
 * Writes the metadata of the service provider, which the identity provider imports: its entity ID, whether its requests
 * are signed and its assertions must be, its signing and encryption certificates, its logout service on HTTP-Redirect
 * and HTTP-POST, its name identifier format, and its assertion consumer service on HTTP-POST.
 */
final class ServiceProviderMetadata
{
    private function __construct()
    {
    }

    public static function xml(ServiceProvider $sp, bool $wantAssertionsSigned = true, ?DateTimeInterface $validUntil = null): string
    {
        $root = XmlWriter::document(Ns::METADATA, 'md:EntityDescriptor');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ds', Ns::DSIG);
        $root->setAttribute('entityID', $sp->entityId);

        if ($validUntil instanceof DateTimeInterface) {
            $root->setAttribute('validUntil', Instant::format($validUntil));
        }

        $descriptor = XmlWriter::child($root, Ns::METADATA, 'md:SPSSODescriptor', [
            'AuthnRequestsSigned' => $sp->signRequests ? 'true' : 'false',
            'WantAssertionsSigned' => $wantAssertionsSigned ? 'true' : 'false',
            'protocolSupportEnumeration' => Ns::PROTOCOL,
        ]);

        if ($sp->signing instanceof KeyPair) {
            self::key($descriptor, 'signing', $sp->signing->certificate);
        }

        foreach ($sp->encryption as $pair) {
            $key = self::key($descriptor, 'encryption', $pair->certificate);

            foreach ([DataEncryption::Aes256Gcm, DataEncryption::Aes128Gcm, DataEncryption::Aes256Cbc, DataEncryption::Aes128Cbc] as $algorithm) {
                XmlWriter::child($key, Ns::METADATA, 'md:EncryptionMethod', ['Algorithm' => $algorithm->value]);
            }
        }

        if ($sp->singleLogoutService instanceof Url) {
            foreach ([Binding::Redirect, Binding::Post] as $binding) {
                XmlWriter::child($descriptor, Ns::METADATA, 'md:SingleLogoutService', ['Binding' => $binding->value, 'Location' => $sp->singleLogoutService->toString()]);
            }
        }

        if (null !== $sp->nameIdFormat) {
            XmlWriter::child($descriptor, Ns::METADATA, 'md:NameIDFormat', [], $sp->nameIdFormat);
        }

        XmlWriter::child($descriptor, Ns::METADATA, 'md:AssertionConsumerService', [
            'Binding' => Binding::Post->value,
            'Location' => $sp->assertionConsumerService->toString(),
            'index' => '0',
            'isDefault' => 'true',
        ]);

        return (string) $root->ownerDocument?->saveXML();
    }

    private static function key(DOMElement $descriptor, string $use, Certificate $certificate): DOMElement
    {
        $key = XmlWriter::child($descriptor, Ns::METADATA, 'md:KeyDescriptor', ['use' => $use]);
        $data = XmlWriter::child(XmlWriter::child($key, Ns::DSIG, 'ds:KeyInfo'), Ns::DSIG, 'ds:X509Data');
        XmlWriter::child($data, Ns::DSIG, 'ds:X509Certificate', [], $certificate->base64());

        return $key;
    }
}
