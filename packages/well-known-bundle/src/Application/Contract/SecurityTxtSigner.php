<?php

declare(strict_types=1);

namespace Phil\WellKnownBundle\Application\Contract;

/**
 * Signs a security.txt file, as RFC 9116 recommends: an OpenPGP cleartext signature around the text. The bundle holds no
 * key and signs nothing itself; the application implements this with the tool and the key it trusts, and names the
 * service in `security_txt.signer`.
 *
 * Called on each request of `/.well-known/security.txt` with the same text: an implementation that signs slowly keeps
 * the signed text in a cache of its own.
 */
interface SecurityTxtSigner
{
    /**
     * @return string the text, signed
     */
    public function sign(string $text): string;
}
