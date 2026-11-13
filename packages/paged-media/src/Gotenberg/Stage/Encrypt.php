<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Stage;

use Override;
use Phil\PagedMedia\Gotenberg\Exception\InvalidStage;
use Phil\PagedMedia\Gotenberg\Internal\Check;
use SensitiveParameter;

/**
 * Protects the PDF with a password to open it, a password to lift its permissions, or both.
 */
final readonly class Encrypt implements Stage
{
    public function __construct(
        #[SensitiveParameter]
        private ?string $userPassword = null,
        #[SensitiveParameter]
        private ?string $ownerPassword = null,
        public EncryptionAlgorithm $algorithm = EncryptionAlgorithm::Aes256,
        public Permissions $permissions = new Permissions(),
    ) {
        if (null === $userPassword && null === $ownerPassword) {
            throw InvalidStage::because('Encrypting a PDF takes a user password, an owner password, or both.');
        }

        Check::notEmpty('A user password', $userPassword);
        Check::notEmpty('An owner password', $ownerPassword);
    }

    #[Override]
    public function operation(): Operation
    {
        return Operation::Encrypt;
    }

    #[Override]
    public function options(): array
    {
        $denied = $this->permissions->denied();

        return Check::set([
            'userPassword' => $this->userPassword,
            'ownerPassword' => $this->ownerPassword,
            'algorithm' => $this->algorithm->value,
            'permissions' => [] === $denied ? null : $denied,
        ]);
    }

    #[Override]
    public function files(): array
    {
        return [];
    }
}
