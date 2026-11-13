<?php

declare(strict_types=1);

namespace Phil\Epub\Internal;

/**
 * A page break marker found in a content document: its identifier and the page label it carries.
 *
 * @internal
 */
final readonly class PageTarget
{
    public function __construct(public string $id, public string $label)
    {
    }
}
