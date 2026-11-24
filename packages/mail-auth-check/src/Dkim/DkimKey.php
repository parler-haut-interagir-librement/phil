<?php

declare(strict_types=1);

namespace Phil\MailAuthCheck\Dkim;

/**
 * A DKIM key record read by `DkimKeyParser`.
 */
final readonly class DkimKey
{
    /**
     * @param int  $bits    the size of the key: the modulus of an RSA key, 256 for Ed25519; 0 when revoked
     * @param bool $revoked the key is empty (`p=`): its signatures no longer verify
     * @param bool $testing the domain is testing DKIM (`t=y`): receivers may treat its signatures as unsigned
     */
    public function __construct(
        public DkimKeyType $type,
        public int $bits,
        public bool $revoked = false,
        public bool $testing = false,
    ) {
    }
}
