<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Infrastructure\Doctrine\Mapping;

use Override;
use Phil\ContentBundle\Domain\Capability\ExcerptCapable;

/**
 * `ExcerptCapable`: the title in a `VARCHAR(255)`, the description in a text column, and the image reference
 * in a `VARCHAR(2048)`, the length of a URL.
 */
final class ExcerptCapabilityMapping extends CapabilityMapping
{
    #[Override]
    public function capability(): string
    {
        return ExcerptCapable::class;
    }

    #[Override]
    protected function fields(): array
    {
        return [
            ['fieldName' => 'excerptTitle', 'type' => 'string', 'columnName' => 'excerpt_title', 'length' => 255, 'nullable' => true],
            ['fieldName' => 'excerptDescription', 'type' => 'text', 'columnName' => 'excerpt_description', 'nullable' => true],
            ['fieldName' => 'excerptImage', 'type' => 'string', 'columnName' => 'excerpt_image', 'length' => 2048, 'nullable' => true],
        ];
    }
}
