<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Application;

use Override;
use Phil\IdentityProviderBundle\Application\Contract\AddressCanonicalizer;

/**
 * The canonical form of an email address: the address as normalized, and, when `stripSubaddress` is on, without the
 * subaddress — the part of the mailbox after the first separator, `+` by default, as `ana+news@example.com` for
 * `ana@example.com`. Rules of one mail provider, such as the dots some ignore, belong to another implementation of the
 * port.
 */
final readonly class EmailCanonicalizer implements AddressCanonicalizer
{
    /**
     * @param non-empty-string $separator
     */
    public function __construct(
        private bool $stripSubaddress = false,
        private string $separator = '+',
    ) {
    }

    #[Override]
    public function canonical(string $normalized): string
    {
        $at = strrpos($normalized, '@');

        if (!$this->stripSubaddress || false === $at) {
            return $normalized;
        }

        $mailbox = substr($normalized, 0, $at);
        $cut = strpos($mailbox, $this->separator);

        return false === $cut || 0 === $cut ? $normalized : substr($mailbox, 0, $cut).substr($normalized, $at);
    }
}
