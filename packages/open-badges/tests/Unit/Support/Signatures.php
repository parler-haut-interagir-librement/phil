<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Tests\Unit\Support;

use Phil\OpenBadges\Contract\Signer;
use Phil\OpenBadges\Contract\VerificationKey;
use Phil\OpenBadges\Jwt\Algorithms;
use Throwable;

/**
 * Raw signatures through the algorithms of `web-token/jwt-library`: to build tokens the signers would never build.
 */
final class Signatures
{
    public static function sign(Signer $signer, string $data): string
    {
        return $signer->signatureAlgorithm()->sign($signer->jwk(), $data);
    }

    public static function verifies(VerificationKey $key, string $data, string $signature): bool
    {
        try {
            return Algorithms::of($key->algorithm())->verify($key->jwk(), $data, $signature);
        } catch (Throwable) {
            return false;
        }
    }
}
