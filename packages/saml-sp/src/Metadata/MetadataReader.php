<?php

declare(strict_types=1);

namespace Phil\SamlSp\Metadata;

use DOMElement;
use Phil\SamlSp\Binding\Binding;
use Phil\SamlSp\Exception\InvalidKey;
use Phil\SamlSp\Exception\InvalidMetadata;
use Phil\SamlSp\Exception\InvalidSignature;
use Phil\SamlSp\Exception\InvalidXml;
use Phil\SamlSp\Exception\SamlException;
use Phil\SamlSp\Key\Certificate;
use Phil\SamlSp\Signature\SignaturePolicy;
use Phil\SamlSp\Signature\SignatureVerifier;
use Phil\SamlSp\Xml\Elements;
use Phil\SamlSp\Xml\Instant;
use Phil\SamlSp\Xml\Ns;
use Phil\SamlSp\Xml\SafeXml;
use Phil\SamlSp\Xml\XmlBudget;
use Phil\Url\Exception\UrlException;
use Phil\Url\Url;

/**
 * Reads the metadata of an identity provider: an `EntityDescriptor`, or the one of an `EntitiesDescriptor` named by its
 * entity ID. When trusted certificates are given, the root must carry a signature that verifies with one of them. The
 * services are HTTPS addresses read by phil/url — `localhost` only when allowed, for development —, the signing
 * certificates those of the `KeyDescriptor` for signing or for any use.
 */
final readonly class MetadataReader
{
    public function __construct(
        private SignaturePolicy $policy = new SignaturePolicy(),
        private XmlBudget $budget = new XmlBudget(4 * 1024 * 1024, 48, 200_000),
        private bool $allowLocalhost = false,
    ) {
    }

    /**
     * @param string|null       $entityId the entity ID to read, required when the document describes several entities
     * @param list<Certificate> $trusted  the certificates the metadata must be signed with; empty to read it unsigned
     */
    public function read(string $xml, ?string $entityId = null, array $trusted = []): IdentityProviderMetadata
    {
        try {
            $root = SafeXml::load($xml, $this->budget)->documentElement ?? throw InvalidMetadata::because('The metadata is empty.');

            if ([] !== $trusted) {
                new SignatureVerifier($this->policy)->verify($root, $trusted);
            }

            return $this->entity($this->descriptor($root, $entityId));
        } catch (InvalidXml|InvalidSignature|InvalidKey $exception) {
            throw InvalidMetadata::because('The metadata is refused: '.$exception->getMessage(), $exception);
        }
    }

    private function descriptor(DOMElement $root, ?string $entityId): DOMElement
    {
        if (Elements::is($root, Ns::METADATA, 'EntityDescriptor')) {
            if (null !== $entityId && $entityId !== Elements::attribute($root, 'entityID')) {
                throw InvalidMetadata::because(\sprintf('The metadata describes another entity than "%s".', $entityId));
            }

            return $root;
        }

        if (!Elements::is($root, Ns::METADATA, 'EntitiesDescriptor')) {
            throw InvalidMetadata::because('The metadata is not an EntityDescriptor nor an EntitiesDescriptor.');
        }

        $candidates = array_values(array_filter(
            Elements::all($root, Ns::METADATA, 'EntityDescriptor'),
            static fn (DOMElement $entity): bool => null === $entityId || $entityId === Elements::attribute($entity, 'entityID'),
        ));

        if (1 !== \count($candidates)) {
            throw InvalidMetadata::because(null === $entityId ? 'The metadata describes several entities: name the one to read.' : \sprintf('The metadata does not describe "%s".', $entityId));
        }

        return $candidates[0];
    }

    private function entity(DOMElement $entity): IdentityProviderMetadata
    {
        $idp = array_find(
            Elements::all($entity, Ns::METADATA, 'IDPSSODescriptor'),
            static fn (DOMElement $descriptor): bool => \in_array(Ns::PROTOCOL, explode(' ', (string) preg_replace('/\s+/', ' ', (string) Elements::attribute($descriptor, 'protocolSupportEnumeration'))), true),
        ) ?? throw InvalidMetadata::because('The metadata has no SAML 2.0 identity provider.');
        $validUntil = Elements::attribute($entity, 'validUntil');

        try {
            return new IdentityProviderMetadata(
                (string) Elements::attribute($entity, 'entityID'),
                $this->services($idp, 'SingleSignOnService'),
                $this->services($idp, 'SingleLogoutService'),
                $this->certificates($idp),
                'true' === Elements::attribute($idp, 'WantAuthnRequestsSigned'),
                null === $validUntil ? null : Instant::parse($validUntil, 'The validUntil of the metadata'),
            );
        } catch (InvalidMetadata $exception) {
            throw $exception;
        } catch (SamlException $exception) {
            throw InvalidMetadata::because($exception->getMessage(), $exception);
        }
    }

    /**
     * @return array<value-of<Binding>, Url>
     */
    private function services(DOMElement $idp, string $name): array
    {
        $services = [];

        foreach (Elements::all($idp, Ns::METADATA, $name) as $service) {
            $binding = Binding::tryFrom((string) Elements::attribute($service, 'Binding'));

            if (null !== $binding && !isset($services[$binding->value])) {
                $services[$binding->value] = $this->url((string) Elements::attribute($service, 'Location'));
            }
        }

        return $services;
    }

    private function url(string $location): Url
    {
        try {
            $url = Url::fromString($location, $this->allowLocalhost);
        } catch (UrlException $urlException) {
            throw InvalidMetadata::because(\sprintf('The service address "%s" is refused.', $location), $urlException);
        }

        if ('https' !== $url->scheme && !$this->allowLocalhost) {
            throw InvalidMetadata::because(\sprintf('The service address "%s" is not HTTPS.', $location));
        }

        return $url;
    }

    /**
     * @return list<Certificate>
     */
    private function certificates(DOMElement $idp): array
    {
        $certificates = [];

        foreach (Elements::all($idp, Ns::METADATA, 'KeyDescriptor') as $descriptor) {
            if ('encryption' === Elements::attribute($descriptor, 'use')) {
                continue;
            }

            $keyInfo = Elements::one($descriptor, Ns::DSIG, 'KeyInfo');

            foreach ($keyInfo instanceof DOMElement ? Elements::all($keyInfo, Ns::DSIG, 'X509Data') : [] as $data) {
                foreach (Elements::all($data, Ns::DSIG, 'X509Certificate') as $element) {
                    $certificate = Certificate::fromBase64($element->textContent);
                    $certificates[$certificate->fingerprint()] = $certificate;
                }
            }
        }

        return array_values($certificates);
    }
}
