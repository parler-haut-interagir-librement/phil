<?php

declare(strict_types=1);

namespace Phil\SamlSp\Key;

use DateTimeImmutable;
use OpenSSLAsymmetricKey;
use Phil\SamlSp\Exception\InvalidKey;
use Phil\SamlSp\Xml\Base64;

/**
 * An X.509 certificate: the public key of a signature or of an encryption, read from PEM or from the base64 DER of
 * metadata. Only its key is used to verify: its chain and its dates are not, as in SAML metadata, where the certificate
 * carries a key, and its expiry is what `notAfter()` reports for monitoring.
 */
final readonly class Certificate
{
    private function __construct(public string $pem)
    {
    }

    public static function fromPem(string $pem): self
    {
        $certificate = @openssl_x509_read($pem);

        if (false === $certificate || !openssl_x509_export($certificate, $normalised) || !\is_string($normalised)) {
            throw InvalidKey::because('The certificate is not an X.509 certificate in PEM.');
        }

        return new self($normalised);
    }

    /**
     * The certificate as the `X509Certificate` element of XML Signature and metadata holds it: base64 DER, with spaces
     * and line breaks allowed.
     */
    public static function fromBase64(string $base64): self
    {
        $der = Base64::decode($base64) ?? throw InvalidKey::because('The certificate is not base64 DER.');

        return self::fromPem("-----BEGIN CERTIFICATE-----\n".chunk_split(Base64::encode($der), 64, "\n")."-----END CERTIFICATE-----\n");
    }

    /**
     * The base64 DER, on one line, as metadata and `KeyInfo` write it.
     */
    public function base64(): string
    {
        return (string) preg_replace('/-----[A-Z ]+-----|\s+/', '', $this->pem);
    }

    public function publicKey(): OpenSSLAsymmetricKey
    {
        $key = openssl_pkey_get_public($this->pem);

        if (false === $key) {
            throw InvalidKey::because('The certificate holds no public key that can be read.');
        }

        return $key;
    }

    /**
     * The size of an RSA key in bits, 0 for another kind of key.
     */
    public function rsaBits(): int
    {
        $details = openssl_pkey_get_details($this->publicKey());

        return false !== $details && \OPENSSL_KEYTYPE_RSA === $details['type'] && \is_int($details['bits']) ? $details['bits'] : 0;
    }

    public function notBefore(): DateTimeImmutable
    {
        return $this->instant('validFrom_time_t');
    }

    public function notAfter(): DateTimeImmutable
    {
        return $this->instant('validTo_time_t');
    }

    public function subject(): string
    {
        $parsed = openssl_x509_parse($this->pem);

        return false !== $parsed && \is_string($parsed['name'] ?? null) ? $parsed['name'] : '';
    }

    /**
     * The SHA-256 fingerprint of the DER, in lowercase hexadecimal.
     */
    public function fingerprint(): string
    {
        return hash('sha256', (string) Base64::decode($this->base64()));
    }

    public function equals(self $other): bool
    {
        return hash_equals($this->fingerprint(), $other->fingerprint());
    }

    private function instant(string $field): DateTimeImmutable
    {
        $parsed = openssl_x509_parse($this->pem);
        $time = false === $parsed ? null : $parsed[$field] ?? null;

        if (!\is_int($time)) {
            throw InvalidKey::because('The certificate has no validity period that can be read.');
        }

        return new DateTimeImmutable('@'.$time);
    }
}
