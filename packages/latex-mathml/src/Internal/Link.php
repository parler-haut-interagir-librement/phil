<?php

declare(strict_types=1);

namespace Phil\LatexMathMl\Internal;

use Phil\Url\Exception\UrlException;
use Phil\Url\Url;

/**
 * The address of `\href` and `\url`: an `http` or `https` address read and normalised by `phil/url`, or a `mailto:`
 * address of one mailbox whose domain `phil/url` accepts as a host. Any other scheme is refused.
 *
 * @internal
 */
final class Link
{
    /** The characters of the local part of a mailbox, as RFC 5322 writes them without quotes. */
    private const string MAILBOX = '/^mailto:([A-Za-z0-9!#$%&\'*+\/=?^_`{|}~-]+(?:\.[A-Za-z0-9!#$%&\'*+\/=?^_`{|}~-]+)*)@([^@\/?#:\s]+)$/Di';

    /**
     * The address, normalised, or null when it is not one this class accepts.
     */
    public static function href(string $address): ?string
    {
        $address = trim($address);

        try {
            if (1 === preg_match(self::MAILBOX, $address, $match)) {
                return \strlen($match[1]) > 64 ? null : 'mailto:'.$match[1].'@'.Url::fromString('https://'.$match[2])->host;
            }

            return Url::fromString($address)->toString();
        } catch (UrlException) {
            return null;
        }
    }
}
