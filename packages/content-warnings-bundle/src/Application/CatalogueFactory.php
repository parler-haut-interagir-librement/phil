<?php

declare(strict_types=1);

namespace Phil\ContentWarningsBundle\Application;

use Phil\ContentWarnings\BaseCatalogue;
use Phil\ContentWarnings\Catalogue;
use Phil\ContentWarnings\CatalogueLoader;
use Phil\ContentWarnings\Exception\InvalidCatalogue;

/**
 * The application's catalogue: the base list unless it is turned off, then each definition file in order, then the
 * definition of the configuration.
 */
final readonly class CatalogueFactory
{
    /**
     * @param list<string>            $files
     * @param array<array-key, mixed> $definition
     */
    public function __construct(
        private bool $base = true,
        private array $files = [],
        private array $definition = [],
    ) {
    }

    /**
     * @throws InvalidCatalogue
     */
    public function create(): Catalogue
    {
        $loader = new CatalogueLoader();
        $catalogue = $this->base ? BaseCatalogue::load() : new Catalogue();

        foreach ($this->files as $file) {
            $catalogue = $loader->applyFile($file, $catalogue);
        }

        return $loader->apply($this->definition, $catalogue);
    }
}
