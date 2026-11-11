<?php

declare(strict_types=1);

namespace Phil\Ujo\Html;

/**
 * A last pass over the HTML of a rendered document, such as typographic fixes. A filter receives purified HTML and must
 * return HTML as safe: it may rewrite text, never add markup or attributes.
 */
interface HtmlFilter
{
    public function filter(string $html): string;
}
