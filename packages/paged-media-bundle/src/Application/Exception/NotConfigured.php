<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Application\Exception;

use LogicException;
use Phil\PagedMedia\Exception\PagedMediaException;

/**
 * A feature the configuration does not enable: Gotenberg without its URL, a signature without a certificate, a
 * timestamp without an authority, HTML without a print engine.
 */
final class NotConfigured extends LogicException implements PagedMediaException
{
    public static function gotenberg(): self
    {
        return new self('Gotenberg is not configured: set phil_paged_media.gotenberg.url.');
    }

    public static function printEngine(): self
    {
        return new self('No print engine is configured: register a service as Phil\PagedMediaBundle\Application\Contract\PrintEngine.');
    }

    public static function signing(): self
    {
        return new self('Signing is not configured: set phil_paged_media.signing.certificate.');
    }

    public static function timestamping(): self
    {
        return new self('Timestamping is not configured: set phil_paged_media.signing.timestamp_url.');
    }
}
