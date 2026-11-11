<?php

declare(strict_types=1);

namespace Phil\WellKnownBundle\Tests\Functional\App;

use Phil\WellKnownBundle\Application\Contract\SecurityTxtSigner;

/**
 * A signer that frames the text as a cleartext signature would, without a key.
 */
final readonly class PrefixSigner implements SecurityTxtSigner
{
    public function sign(string $text): string
    {
        return "-----BEGIN PGP SIGNED MESSAGE-----\nHash: SHA512\n\n".$text."-----BEGIN PGP SIGNATURE-----\n\nfake\n-----END PGP SIGNATURE-----\n";
    }
}
