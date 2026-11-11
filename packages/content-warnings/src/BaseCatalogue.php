<?php

declare(strict_types=1);

namespace Phil\ContentWarnings;

/**
 * The base list shipped with the library: about seventy common themes in twelve categories, labelled in English,
 * French, Spanish, German, Italian and Portuguese. An application completes it, changes its labels or removes themes
 * with `CatalogueLoader::apply()`, or starts from its own list instead.
 */
final class BaseCatalogue
{
    public const string DEFINITION = __DIR__.'/../resources/base-catalogue.php';

    public static function load(): Catalogue
    {
        return new CatalogueLoader()->applyFile(self::DEFINITION);
    }
}
