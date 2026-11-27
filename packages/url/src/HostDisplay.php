<?php

declare(strict_types=1);

namespace Phil\Url;

use Spoofchecker;

/**
 * How a host reads in an address shown to people: in its own letters, `bücher.example`, when they can be trusted; in
 * its ASCII form, `xn--bcher-kva.example`, when ICU's spoof checker finds them suspicious, mixing scripts or passing
 * for another host's, and whenever there is no spoof checker to ask, PHP running without `ext-intl`. A host already
 * in plain ASCII reads as it is.
 *
 * @internal the display of `Url`; its checker is a parameter so that both paths are tested
 */
final readonly class HostDisplay
{
    public function __construct(
        private ?Spoofchecker $checker,
    ) {
    }

    /**
     * One display for every address shown, with a spoof checker when `ext-intl` is loaded: the checker holds no state
     * between two hosts.
     */
    public static function shared(): self
    {
        static $display = null;

        if (!$display instanceof self) {
            $display = new self(class_exists(Spoofchecker::class) ? new Spoofchecker() : null);
        }

        return $display;
    }

    public function show(string $host): string
    {
        // A host without an `xn--` label is plain ASCII, with nothing to convert and nothing to hide.
        if (!str_contains($host, 'xn--') || !$this->checker instanceof Spoofchecker) {
            return $host;
        }

        $unicode = idn_to_utf8($host, \IDNA_NONTRANSITIONAL_TO_UNICODE, \INTL_IDNA_VARIANT_UTS46);

        // A host whose letters mix scripts or imitate another's, `аpple.com` in Cyrillic, stays in ASCII.
        if (false === $unicode || $this->checker->isSuspicious($unicode)) {
            return $host;
        }

        return $unicode;
    }
}
