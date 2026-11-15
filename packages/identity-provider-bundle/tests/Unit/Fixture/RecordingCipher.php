<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Fixture;

use Override;
use Phil\IdentityProviderBundle\Application\Contract\SecretCipher;

/**
 * A cipher that keeps the contexts it is given, for the tests.
 */
final class RecordingCipher implements SecretCipher
{
    /** @var list<string> */
    public array $contexts = [];

    #[Override]
    public function seal(string $plaintext, string $context): string
    {
        $this->contexts[] = $context;

        return 'sealed.'.$plaintext;
    }

    #[Override]
    public function open(string $sealed, string $context): string
    {
        $this->contexts[] = $context;

        return substr($sealed, 7);
    }
}
