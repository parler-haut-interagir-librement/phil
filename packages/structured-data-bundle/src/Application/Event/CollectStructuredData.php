<?php

declare(strict_types=1);

namespace Phil\StructuredDataBundle\Application\Event;

use Phil\StructuredData\Node;
use Phil\StructuredDataBundle\Application\PageContext;

/**
 * Dispatched once per page, after the providers have given their nodes: a listener adds the nodes of the page it
 * knows of, as a provider would.
 */
final class CollectStructuredData
{
    /** @var list<Node> */
    private array $nodes = [];

    public function __construct(public readonly PageContext $page)
    {
    }

    public function add(Node ...$nodes): void
    {
        foreach ($nodes as $node) {
            $this->nodes[] = $node;
        }
    }

    /**
     * @return list<Node>
     */
    public function nodes(): array
    {
        return $this->nodes;
    }
}
