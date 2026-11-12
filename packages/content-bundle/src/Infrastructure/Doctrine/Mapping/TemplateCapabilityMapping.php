<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Infrastructure\Doctrine\Mapping;

use Override;
use Phil\ContentBundle\Domain\Capability\TemplateCapable;

/**
 * `TemplateCapable`: the template key, and the field values as one JSON document.
 *
 * The values are one document rather than one column per field because the fields are the template's, and
 * a template changes without a migration. `JSON` on MySQL and PostgreSQL, text on SQLite.
 */
final class TemplateCapabilityMapping extends CapabilityMapping
{
    #[Override]
    public function capability(): string
    {
        return TemplateCapable::class;
    }

    #[Override]
    protected function fields(): array
    {
        return [
            ['fieldName' => 'templateKey', 'type' => 'string', 'columnName' => 'template_key', 'length' => 64, 'nullable' => true],
            ['fieldName' => 'templateData', 'type' => 'json', 'columnName' => 'template_data'],
        ];
    }
}
