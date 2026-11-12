<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Domain\Dimension;

use Phil\ContentBundle\Domain\Exception\InvalidDimension;
use Phil\LocaleDimensions\DeclaredLocales;
use Phil\LocaleDimensions\Exception\InvalidLocaleDimension;
use Phil\LocaleDimensions\FallbackChain;
use Phil\LocaleDimensions\Locale;

/**
 * The locale of a dimension row read from a string, and its declared chain, with the refusals of
 * `phil/locale-dimensions` raised as `InvalidDimension`.
 *
 * Every error of the bundle is a `DomainError`, which the application, the console commands, the Twig functions and
 * the bundles built on this one catch at their boundary. A malformed locale or one that is not served reaches them
 * the same way, so that a locale typed in a URL or a form is refused like any other value of the content.
 */
final class DimensionLocales
{
    /**
     * @throws InvalidDimension when the string is not a locale of the form `fr` or `fr_CA`
     */
    public static function parse(string $value): Locale
    {
        try {
            return Locale::fromString($value);
        } catch (InvalidLocaleDimension $invalidLocaleDimension) {
            throw InvalidDimension::from($invalidLocaleDimension);
        }
    }

    /**
     * @throws InvalidDimension when the locale is not served
     */
    public static function chain(DeclaredLocales $locales, Locale $locale): FallbackChain
    {
        try {
            return $locales->chainFor($locale);
        } catch (InvalidLocaleDimension $invalidLocaleDimension) {
            throw InvalidDimension::from($invalidLocaleDimension);
        }
    }
}
