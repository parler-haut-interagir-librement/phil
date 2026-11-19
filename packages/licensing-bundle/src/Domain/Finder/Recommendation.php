<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Domain\Finder;

use Phil\LicensingBundle\Domain\Catalog\LicenseCatalog;

/**
 * The license the finder recommends: a license of the catalog by its code, or `custom` with the terms it wrote.
 */
final readonly class Recommendation
{
    private function __construct(
        public string $license,
        public ?CustomTerms $terms,
        public bool $imposed,
    ) {
    }

    /**
     * @param bool $imposed whether the license is the one of the share-alike material the work adapts
     */
    public static function catalog(string $license, bool $imposed = false): self
    {
        return new self($license, null, $imposed);
    }

    public static function custom(CustomTerms $terms): self
    {
        return new self(LicenseCatalog::CUSTOM, $terms, false);
    }

    public function isCustom(): bool
    {
        return $this->terms instanceof CustomTerms;
    }
}
