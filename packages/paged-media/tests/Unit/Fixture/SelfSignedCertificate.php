<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Tests\Unit\Fixture;

use RuntimeException;

/**
 * A self-signed signing certificate made for the test, never kept: its PEM certificate and key, and the same as a
 * PKCS#12 file opened by a password.
 */
final readonly class SelfSignedCertificate
{
    private function __construct(
        public string $certificate,
        public string $key,
        public string $pkcs12,
        public string $password,
    ) {
    }

    public static function make(string $password = 'test-password'): self
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);

        if (false === $key) {
            throw new RuntimeException('OpenSSL could not make a key.');
        }

        $requestKey = $key;

        $request = openssl_csr_new(['commonName' => 'Test Signer'], $requestKey, ['digest_alg' => 'sha256']);

        if (false === $request || true === $request) {
            throw new RuntimeException('OpenSSL could not make a signing request.');
        }

        $certificate = openssl_csr_sign($request, null, $key, 2, ['digest_alg' => 'sha256']);

        if (false === $certificate || !openssl_x509_export($certificate, $pem) || !openssl_pkey_export($key, $privateKey) || !openssl_pkcs12_export($certificate, $pkcs12, $key, $password)) {
            throw new RuntimeException('OpenSSL could not export the certificate.');
        }

        if (!\is_string($pem) || !\is_string($privateKey) || !\is_string($pkcs12)) {
            throw new RuntimeException('OpenSSL exported something other than text.');
        }

        return new self($pem, $privateKey, $pkcs12, $password);
    }
}
