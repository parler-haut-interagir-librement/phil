<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Fixture;

use Phil\Markup\HtmlPurifier;
use Phil\Markup\Level;

/**
 * Wraps what it is given in a marker, so a test sees that the HTML went through a purifier, and how many times.
 */
final class MarkingHtmlPurifier implements HtmlPurifier
{
    public function purify(string $html, Level $level): string
    {
        return '<purified>'.$html.'</purified>';
    }
}
