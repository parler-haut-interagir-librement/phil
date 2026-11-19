<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Application;

use Phil\CommerceProductTemplate\Variant;

/**
 * @internal what one production run of `PrintProduction` reads once and remembers until it returns: the plan of each
 *           group, the bytes of each image, the digest of each font, the print files already written
 */
final class ProductionCache
{
    /** @var array<string, array<string, Variant>> by group, then SKU */
    public array $plans = [];

    /** @var array<string, string> by path */
    public array $images = [];

    /** @var array<string, string> by font name */
    public array $fonts = [];

    /** @var array<string, true> by path */
    public array $written = [];
}
