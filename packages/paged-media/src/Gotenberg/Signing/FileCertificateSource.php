<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Signing;

use Override;
use Phil\PagedMedia\Gotenberg\InputFile;
use SensitiveParameter;

/**
 * A certificate on disk: a PKCS#12 file and its password, or a PEM certificate and the file of its PEM key.
 */
final readonly class FileCertificateSource implements CertificateSource
{
    public function __construct(
        private string $certificatePath,
        private ?string $keyPath = null,
        #[SensitiveParameter]
        private ?string $password = null,
    ) {
    }

    #[Override]
    public function certificate(): Certificate
    {
        $certificate = InputFile::fromPath($this->certificatePath);

        return null === $this->keyPath ? Certificate::pkcs12($certificate, $this->password) : Certificate::pem($certificate, InputFile::fromPath($this->keyPath));
    }
}
