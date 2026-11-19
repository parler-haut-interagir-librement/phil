<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Tests\Unit\Fixture;

use Override;
use SensitiveParameter;
use Symfony\Component\KeyManagement\Ciphertext;
use Symfony\Component\KeyManagement\DataKey;
use Symfony\Component\KeyManagement\DataKeyGeneratorInterface;
use Throwable;

/**
 * A KMS that cannot be reached: every call throws the failure it is given.
 */
final readonly class FailingKms implements DataKeyGeneratorInterface
{
    public function __construct(
        private Throwable $failure,
    ) {
    }

    #[Override]
    public function generateDataKey(string $keyId, int $length = 32, string $aad = ''): DataKey
    {
        throw $this->failure;
    }

    #[Override]
    public function wrapDataKey(string $keyId, #[SensitiveParameter] string $dataKey, string $aad = ''): Ciphertext
    {
        throw $this->failure;
    }

    #[Override]
    public function unwrapDataKey(Ciphertext $wrapped, string $aad = ''): DataKey
    {
        throw $this->failure;
    }
}
