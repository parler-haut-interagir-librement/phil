<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Infrastructure\StructuredData;

use Phil\LicensingBundle\Application\AttributedResource;
use Phil\LicensingBundle\Application\AttributionPage;
use Phil\LicensingBundle\Domain\Catalog\ResourceKind;
use Phil\StructuredData\Node;

/**
 * The licensing of a work as schema.org properties: `license`, the address of its license, `copyrightNotice`, and
 * `isBasedOn`, each attributed resource with its own `license`, `author`, `url` and `copyrightNotice`.
 *
 * `properties()` gives them to merge into the application's node of the work; `node()` gives a node of their own.
 */
final readonly class LicenseStructuredData
{
    private const array TYPES = [
        ResourceKind::Text->value => 'CreativeWork',
        ResourceKind::Image->value => 'ImageObject',
        ResourceKind::Map->value => 'Map',
        ResourceKind::Font->value => 'CreativeWork',
        ResourceKind::Other->value => 'CreativeWork',
    ];

    /**
     * @return array{license: string|null, copyrightNotice: string|null, isBasedOn: list<Node>}
     */
    public function properties(AttributionPage $page): array
    {
        return [
            'license' => $page->license?->url,
            'copyrightNotice' => $page->notice,
            'isBasedOn' => array_map($this->resource(...), $page->resources),
        ];
    }

    /**
     * @param string $type the schema.org type of the work: `Book`, `Product`, `CreativeWork`
     */
    public function node(AttributionPage $page, string $type = 'CreativeWork', ?string $id = null): Node
    {
        return Node::of($type, $this->properties($page), $id);
    }

    private function resource(AttributedResource $resource): Node
    {
        $attribution = $resource->attribution;

        return Node::of(self::TYPES[$attribution->kind->value], [
            'name' => $attribution->title,
            'author' => null === $attribution->author ? null : Node::of('Person', ['name' => $attribution->author]),
            'url' => $attribution->url,
            'license' => $resource->license?->url,
            'copyrightNotice' => $attribution->notice,
        ]);
    }
}
