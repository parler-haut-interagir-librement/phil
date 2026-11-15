<?php

declare(strict_types=1);

namespace Phil\LegalNoticesBundle\Infrastructure\Consent;

use Override;
use Phil\LegalNotices\ConsentCategory;
use Phil\LegalNotices\Contract\ConsentCategories;
use Phil\LegalNotices\Exception\InvalidLegalNotice;

/**
 * The consent categories written in the configuration, in its order, built once when the service is.
 *
 * @phpstan-type CategoryConfig array{necessary: bool, labels: array<string, string>, descriptions: array<string, string>, cookies: list<string>}
 */
final readonly class ConfiguredConsentCategories implements ConsentCategories
{
    /**
     * @var list<ConsentCategory>
     */
    private array $categories;

    /**
     * @param array<array-key, CategoryConfig> $categories by key
     *
     * @throws InvalidLegalNotice
     */
    public function __construct(array $categories)
    {
        $built = [];

        foreach ($categories as $key => $category) {
            $built[] = new ConsentCategory((string) $key, $category['necessary'], $category['labels'], $category['descriptions'], $category['cookies']);
        }

        $this->categories = $built;
    }

    #[Override]
    public function categories(): array
    {
        return $this->categories;
    }
}
