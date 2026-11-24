<?php

declare(strict_types=1);

namespace Phil\SamlSp\Key;

use Phil\SamlSp\Exception\InvalidKey;

/**
 * A private key and the certificate of its public key: the certificate is published in the metadata of the service
 * provider, the key signs or decrypts. The pair is checked on creation.
 */
final readonly class KeyPair
{
    public function __construct(public PrivateKey $privateKey, public Certificate $certificate)
    {
        if (!openssl_x509_check_private_key($certificate->pem, $privateKey->key())) {
            throw InvalidKey::because('The private key is not the key of the certificate.');
        }
    }
}
