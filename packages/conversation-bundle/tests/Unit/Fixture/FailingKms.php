<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Tests\Unit\Fixture;

use Override;
use SensitiveParameter;
use Symfony\Component\KeyManagement\Ciphertext;
use Symfony\Component\KeyManagement\DataKey;
use Symfony\Component\KeyManagement\DataKeyGeneratorInterface;
use Throwable;

/**
 * A key management service that generates keys as usual and fails with the exception given when one is unwrapped.
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
        return Keys::kms()->generateDataKey($keyId, $length, $aad);
    }

    #[Override]
    public function wrapDataKey(string $keyId, #[SensitiveParameter] string $dataKey, string $aad = ''): Ciphertext
    {
        return Keys::kms()->wrapDataKey($keyId, $dataKey, $aad);
    }

    #[Override]
    public function unwrapDataKey(Ciphertext $wrapped, string $aad = ''): DataKey
    {
        throw $this->failure;
    }
}
