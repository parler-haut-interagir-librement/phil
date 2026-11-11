<?php

declare(strict_types=1);

namespace Phil\StructuredData;

/**
 * A link to a node by its `@id`, written `{"@id": "…"}`: the node itself sits once at the top of the graph, and every
 * node that names it refers to it.
 */
final readonly class Reference
{
    public string $id;

    public function __construct(string $id)
    {
        $this->id = Id::check($id);
    }

    public static function to(string $id): self
    {
        return new self($id);
    }

    /**
     * @return array{'@id': string}
     */
    public function toArray(): array
    {
        return ['@id' => $this->id];
    }
}
