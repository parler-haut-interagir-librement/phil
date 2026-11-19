<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Tests\Unit\Fixture;

use Override;
use Phil\ApiKeysBundle\Application\Contract\ClientAddressAnonymizer;

/**
 * Keeps `anon(<address>)` of an address, and counts its calls.
 */
final class FixedAddress implements ClientAddressAnonymizer
{
    public int $calls = 0;

    #[Override]
    public function anonymize(string $address): string
    {
        ++$this->calls;

        return 'anon('.$address.')';
    }
}
