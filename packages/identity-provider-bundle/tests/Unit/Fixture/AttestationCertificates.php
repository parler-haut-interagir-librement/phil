<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Fixture;

use OpenSSLAsymmetricKey;
use OpenSSLCertificate;
use RuntimeException;

/**
 * Certificates of attestation for the tests: a root, an intermediate authority and the certificate of an
 * authenticator, made by OpenSSL with the extensions WebAuthn reads, valid from now for the days given.
 */
final class AttestationCertificates
{
    private function __construct()
    {
    }

    public static function key(): OpenSSLAsymmetricKey
    {
        $key = openssl_pkey_new(['private_key_type' => \OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);

        return $key instanceof OpenSSLAsymmetricKey ? $key : throw new RuntimeException('No key.');
    }

    /**
     * A certificate of the key, signed by the issuer or by itself.
     *
     * @param array<string, string>                               $subject
     * @param array{cert: string, key: OpenSSLAsymmetricKey}|null $issuer
     * @param bool                                                $authority whether it may sign certificates
     * @param ?string                                             $aaguid    the 16 bytes of its AAGUID extension
     *
     * @return array{cert: string, key: OpenSSLAsymmetricKey} the certificate in PEM and its key
     */
    public static function issue(array $subject, ?array $issuer = null, bool $authority = true, ?string $aaguid = null, int $days = 30, ?OpenSSLAsymmetricKey $key = null): array
    {
        $key ??= self::key();
        $config = tempnam(sys_get_temp_dir(), 'phil-attestation-');

        if (!\is_string($config)) {
            throw new RuntimeException('No file.');
        }

        $extensions = 'basicConstraints = critical,'.($authority ? 'CA:TRUE' : 'CA:FALSE')."\n";

        if (null !== $aaguid) {
            $extensions .= '1.3.6.1.4.1.45724.1.1.4 = DER:04:10:'.implode(':', str_split(bin2hex($aaguid), 2))."\n";
        }

        file_put_contents($config, "[ req ]\ndistinguished_name = dn\n[ dn ]\n[ extensions ]\n".$extensions);

        try {
            $options = ['config' => $config, 'digest_alg' => 'sha256', 'x509_extensions' => 'extensions'];
            $csr = openssl_csr_new($subject, $key, $options);

            if (\is_bool($csr)) {
                throw new RuntimeException('No request.');
            }

            $signer = null === $issuer ? $key : $issuer['key'];
            \assert($signer instanceof OpenSSLAsymmetricKey && $key instanceof OpenSSLAsymmetricKey);
            $certificate = openssl_csr_sign($csr, null === $issuer ? null : $issuer['cert'], $signer, $days, $options, random_int(1, \PHP_INT_MAX));

            if (!$certificate instanceof OpenSSLCertificate || !openssl_x509_export($certificate, $pem) || !\is_string($pem)) {
                throw new RuntimeException('No certificate.');
            }
        } finally {
            unlink($config);
        }

        return ['cert' => $pem, 'key' => $key];
    }

    public static function der(string $pem): string
    {
        return (string) base64_decode(implode('', \array_slice(explode("\n", trim($pem)), 1, -1)), true);
    }
}
