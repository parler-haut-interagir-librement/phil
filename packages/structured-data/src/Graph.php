<?php

declare(strict_types=1);

namespace Phil\StructuredData;

use Countable;
use Override;
use Phil\StructuredData\Exception\InvalidStructuredData;

/**
 * The schema.org graph of a page: its nodes under `@graph`, one script element for all of them.
 *
 * Two nodes with the same `@id` are one node: the second merges into the first, where it stands, its values
 * replacing the first one's where both have one. A node with an `@id` nested in another rises to the top of the graph,
 * merged with the others of its `@id`, and the node that held it keeps a reference to it. A node without an `@id`
 * stays where it is given. A graph never changes: each `with()` gives a new one.
 *
 * A graph holds 10,000 nodes at most.
 */
final readonly class Graph implements Countable
{
    public const string CONTEXT = 'https://schema.org';

    public const int MAX_NODES = 10_000;

    /**
     * @param array<int|string, Node> $nodes by `@id`, or by position for a node without one
     */
    private function __construct(
        private array $nodes,
        public ?string $language,
    ) {
    }

    public static function of(Node ...$nodes): self
    {
        return new self([], null)->with(...$nodes);
    }

    /**
     * The graph with the nodes added, merged by `@id` with those it holds.
     *
     * @throws InvalidStructuredData when the graph would hold more than 10,000 nodes
     */
    public function with(Node ...$nodes): self
    {
        $entries = $this->nodes;

        foreach ($nodes as $node) {
            self::add($entries, $node);
        }

        return new self($entries, $this->language);
    }

    /**
     * This graph and the nodes of another one, merged by `@id`; this graph's language, or the other's when it has none.
     */
    public function merge(self $other): self
    {
        return new self($this->with(...$other->nodes())->nodes, $this->language ?? $other->language);
    }

    /**
     * The graph whose texts are in that language unless they say otherwise, written as the `@language` of its context.
     *
     * @throws InvalidStructuredData when the language is no BCP 47 tag
     */
    public function withLanguage(?string $language): self
    {
        return new self($this->nodes, null === $language ? null : LanguageTag::normalize($language));
    }

    /**
     * @return list<Node>
     */
    public function nodes(): array
    {
        return array_values($this->nodes);
    }

    public function get(string $id): ?Node
    {
        return $this->nodes['@'.$id] ?? null;
    }

    /**
     * The node of the graph a reference names, or the node itself; `null` for a reference to a node the graph lacks.
     */
    public function resolve(Reference|Node $value): ?Node
    {
        return $value instanceof Node ? $value : $this->get($value->id);
    }

    public function isEmpty(): bool
    {
        return [] === $this->nodes;
    }

    #[Override]
    public function count(): int
    {
        return \count($this->nodes);
    }

    /**
     * The JSON-LD document: the schema.org context, with the language when the graph has one, and the nodes.
     *
     * @return array{'@context': string|list<string|array{'@language': string}>, '@graph': list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            '@context' => null === $this->language ? self::CONTEXT : [self::CONTEXT, ['@language' => $this->language]],
            '@graph' => array_map(static fn (Node $node): array => $node->toArray(), array_values($this->nodes)),
        ];
    }

    /**
     * @param array<int|string, Node> $entries
     */
    private static function add(array &$entries, Node $node): void
    {
        if ($node->nestsIdentified) {
            $node = self::liftAll($entries, $node);
        }

        $key = null === $node->id ? null : '@'.$node->id;

        if (null !== $key && isset($entries[$key])) {
            $entries[$key] = $entries[$key]->mergedWith($node);

            return;
        }

        if (\count($entries) >= self::MAX_NODES) {
            throw InvalidStructuredData::because(\sprintf('A graph holds %d nodes at most.', self::MAX_NODES));
        }

        null === $key ? $entries[] = $node : $entries[$key] = $node;
    }

    /**
     * The node with each nested node that has an `@id` added to the graph and replaced by a reference to it.
     *
     * @param array<int|string, Node> $entries
     */
    private static function liftAll(array &$entries, Node $node): Node
    {
        $properties = [];

        foreach ($node->properties() as $name => $value) {
            if (!\is_array($value)) {
                $properties[$name] = self::lift($entries, $value);

                continue;
            }

            $list = [];

            foreach ($value as $item) {
                $list[] = self::lift($entries, $item);
            }

            $properties[$name] = $list;
        }

        return $node->withProperties($properties);
    }

    /**
     * @param array<int|string, Node> $entries
     */
    private static function lift(array &$entries, mixed $value): mixed
    {
        if (!$value instanceof Node || (null === $value->id && !$value->nestsIdentified)) {
            return $value;
        }

        if (null === $value->id) {
            return self::liftAll($entries, $value);
        }

        self::add($entries, $value);

        return new Reference($value->id);
    }
}
