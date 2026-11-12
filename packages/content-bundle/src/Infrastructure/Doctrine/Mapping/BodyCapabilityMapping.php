<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Infrastructure\Doctrine\Mapping;

use Override;
use Phil\ContentBundle\Domain\Capability\BodyCapable;

/**
 * `BodyCapable`: the source and the HTML in text columns, without a length, which is `LONGTEXT` on MySQL and
 * `TEXT` on PostgreSQL; and the name of the format in a `VARCHAR(16)`, the length of a format's name.
 */
final class BodyCapabilityMapping extends CapabilityMapping
{
    #[Override]
    public function capability(): string
    {
        return BodyCapable::class;
    }

    #[Override]
    protected function fields(): array
    {
        return [
            ['fieldName' => 'bodySource', 'type' => 'text', 'columnName' => 'body_source', 'nullable' => true],
            ['fieldName' => 'bodyFormat', 'type' => 'string', 'columnName' => 'body_format', 'length' => 16, 'nullable' => true],
            ['fieldName' => 'bodyHtml', 'type' => 'text', 'columnName' => 'body_html', 'nullable' => true],
        ];
    }
}
