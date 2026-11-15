<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Tests\Unit\Fixture;

use Override;
use Phil\LtiBundle\Application\Contract\KeySealer;
use RuntimeException;
use SensitiveParameter;

/**
 * A sealer that only wraps the key with its identifier, for the tests of the stores.
 */
final class PlainSealer implements KeySealer
{
    public int $opened = 0;

    #[Override]
    public function seal(string $keyId, #[SensitiveParameter] string $key): string
    {
        return $keyId.'|'.base64_encode($key);
    }

    #[Override]
    public function open(string $keyId, string $sealed): string
    {
        ++$this->opened;
        [$kid, $pem] = explode('|', $sealed, 2) + [1 => ''];

        return $kid === $keyId ? (string) base64_decode($pem, true) : throw new RuntimeException('Moved.');
    }
}
