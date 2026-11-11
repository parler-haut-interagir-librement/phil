<?php

declare(strict_types=1);

namespace Phil\ContentWarningsBundle\Tests\Unit\Fixtures;

use Phil\ContentWarnings\Catalogue;
use Phil\ContentWarnings\CatalogueLoader;

/**
 * A small catalogue for the tests: two categories, three themes, labelled in English and French.
 */
final class Catalogues
{
    public static function small(): Catalogue
    {
        return new CatalogueLoader()->apply([
            'categories' => [
                'phobias' => ['label' => ['fr' => 'Phobies', 'en' => 'Phobias']],
                'violence' => ['label' => ['en' => 'Violence', 'fr' => 'Violence']],
                'empty' => ['label' => ['en' => 'Empty']],
            ],
            'themes' => [
                'spiders' => ['category' => 'phobias', 'label' => ['en' => 'Spiders', 'fr' => 'Araignées'], 'description' => ['fr' => 'Des araignées', 'en' => 'Spiders, seen or described']],
                'gore' => ['category' => 'violence', 'label' => ['en' => 'Gore', 'fr' => 'Gore']],
                'war' => ['category' => 'violence', 'label' => ['fr' => 'Guerre', 'en' => 'War']],
            ],
        ]);
    }
}
