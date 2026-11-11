<?php

declare(strict_types=1);

namespace Phil\ReferencesBundle\Tests\Unit\Fixtures;

/**
 * A page whose blocks hold links in JSON, or an image others link to.
 */
final class Document
{
    /**
     * @param list<string> $images
     */
    public function __construct(
        public string $kind,
        public string $id,
        public array $images = [],
    ) {
    }
}
