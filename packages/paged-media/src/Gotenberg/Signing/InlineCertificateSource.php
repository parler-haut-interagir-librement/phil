<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Signing;

use Override;
use Phil\PagedMedia\Gotenberg\InputFile;
use SensitiveParameter;

/**
 * A certificate the application holds as text, from an environment variable or Symfony's secrets vault: a PEM
 * certificate with its PEM key, or the bytes of a PKCS#12 file with its password. It is sent as `signer.pem` and
 * `signer.key`, or as `signer.p12`.
 */
final readonly class InlineCertificateSource implements CertificateSource
{
    public function __construct(
        #[SensitiveParameter]
        private string $certificate,
        #[SensitiveParameter]
        private ?string $key = null,
        #[SensitiveParameter]
        private ?string $password = null,
    ) {
    }

    #[Override]
    public function certificate(): Certificate
    {
        return null === $this->key
            ? Certificate::pkcs12(InputFile::fromContents('signer.p12', $this->certificate), $this->password)
            : Certificate::pem(InputFile::fromContents('signer.pem', $this->certificate), InputFile::fromContents('signer.key', $this->key));
    }
}
