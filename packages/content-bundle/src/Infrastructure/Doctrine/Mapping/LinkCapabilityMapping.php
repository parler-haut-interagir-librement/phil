<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Infrastructure\Doctrine\Mapping;

use Override;
use Phil\ContentBundle\Domain\Capability\LinkCapable;
use Phil\ContentBundle\Infrastructure\Doctrine\Type\LocaleType;

/**
 * `LinkCapable`: four nullable columns, the resource key, the identifier and the locale of a content target,
 * or the URL. Columns rather than one document, so a query finds every row that points to one root by
 * `(link_resource_key, link_resource_id)`.
 */
final class LinkCapabilityMapping extends CapabilityMapping
{
    #[Override]
    public function capability(): string
    {
        return LinkCapable::class;
    }

    #[Override]
    protected function fields(): array
    {
        return [
            ['fieldName' => 'linkResourceKey', 'type' => 'string', 'columnName' => 'link_resource_key', 'length' => 64, 'nullable' => true],
            ['fieldName' => 'linkResourceId', 'type' => 'string', 'columnName' => 'link_resource_id', 'length' => 64, 'nullable' => true],
            ['fieldName' => 'linkLocale', 'type' => LocaleType::NAME, 'columnName' => 'link_locale', 'length' => 12, 'nullable' => true],
            ['fieldName' => 'linkUrl', 'type' => 'string', 'columnName' => 'link_url', 'length' => 2048, 'nullable' => true],
        ];
    }
}
