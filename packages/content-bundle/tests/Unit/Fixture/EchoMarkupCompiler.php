<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Fixture;

use Phil\Markup\Exception\UnknownFormat;
use Phil\Markup\MarkupCompiler;
use Phil\Markup\MarkupFormat;

/**
 * Names the format and repeats the source, so a test sees what the data mapper asked it to compile; a format that is
 * not built in is unknown, as to `DefaultCompiler` without add-ons.
 */
final class EchoMarkupCompiler implements MarkupCompiler
{
    public function compile(string $source, MarkupFormat|string $format): string
    {
        $name = MarkupFormat::nameOf($format);

        return null === MarkupFormat::tryFrom($name) ? throw UnknownFormat::named($name, MarkupFormat::values()) : \sprintf('<%s>%s</%1$s>', $name, $source);
    }
}
