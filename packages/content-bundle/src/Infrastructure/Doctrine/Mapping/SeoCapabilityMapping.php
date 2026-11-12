<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Infrastructure\Doctrine\Mapping;

use Override;
use Phil\ContentBundle\Domain\Capability\SeoCapable;

/**
 * `SeoCapable`: the title in a `VARCHAR(255)`, the description in a text column, the keywords as a JSON list,
 * the canonical URL in a `VARCHAR(2048)`, and the three directives as nullable booleans — `NULL` is a
 * directive the row does not set.
 */
final class SeoCapabilityMapping extends CapabilityMapping
{
    #[Override]
    public function capability(): string
    {
        return SeoCapable::class;
    }

    #[Override]
    protected function fields(): array
    {
        return [
            ['fieldName' => 'seoTitle', 'type' => 'string', 'columnName' => 'seo_title', 'length' => 255, 'nullable' => true],
            ['fieldName' => 'seoDescription', 'type' => 'text', 'columnName' => 'seo_description', 'nullable' => true],
            ['fieldName' => 'seoKeywords', 'type' => 'json', 'columnName' => 'seo_keywords'],
            ['fieldName' => 'seoCanonicalUrl', 'type' => 'string', 'columnName' => 'seo_canonical_url', 'length' => 2048, 'nullable' => true],
            ['fieldName' => 'seoNoIndex', 'type' => 'boolean', 'columnName' => 'seo_no_index', 'nullable' => true],
            ['fieldName' => 'seoNoFollow', 'type' => 'boolean', 'columnName' => 'seo_no_follow', 'nullable' => true],
            ['fieldName' => 'seoHideInSitemap', 'type' => 'boolean', 'columnName' => 'seo_hide_in_sitemap', 'nullable' => true],
        ];
    }
}
