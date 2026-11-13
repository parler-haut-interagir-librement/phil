<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Signing;

use Phil\PagedMedia\Gotenberg\Exception\InvalidStage;
use Phil\PagedMedia\Gotenberg\InputFile;
use SensitiveParameter;

/**
 * The certificate a PDF is signed with: a PKCS#12 file that holds the certificate and its key, opened by its
 * password, or a PEM certificate with its PEM private key.
 */
final readonly class Certificate
{
    private function __construct(
        public InputFile $certificate,
        public ?InputFile $key,
        #[SensitiveParameter]
        private ?string $password,
    ) {
    }

    public static function pkcs12(
        InputFile $file,
        #[SensitiveParameter]
        ?string $password = null,
    ): self {
        if (!\in_array($file->extension(), ['p12', 'pfx'], true)) {
            throw InvalidStage::because(\sprintf('A PKCS#12 certificate is a .p12 or .pfx file; "%s" is not.', $file->name));
        }

        return new self($file, null, $password);
    }

    public static function pem(InputFile $certificate, InputFile $key): self
    {
        if ($certificate->name === $key->name) {
            throw InvalidStage::because('A PEM certificate and its key are two files of different names.');
        }

        return new self($certificate, $key, null);
    }

    public function password(): ?string
    {
        return $this->password;
    }
}
