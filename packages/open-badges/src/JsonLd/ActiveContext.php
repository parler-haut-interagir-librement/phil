<?php

declare(strict_types=1);

namespace Phil\OpenBadges\JsonLd;

/**
 * The context a JSON-LD node is read in: its terms, its vocabulary mapping, and the context a type-scoped context
 * returns to in the nodes it does not propagate to.
 *
 * @internal
 */
final readonly class ActiveContext
{
    public const array KEYWORDS = [
        '@base', '@container', '@context', '@direction', '@graph', '@id', '@import', '@included', '@index', '@json',
        '@language', '@list', '@nest', '@none', '@prefix', '@propagate', '@protected', '@reverse', '@set', '@type',
        '@value', '@version', '@vocab',
    ];

    /**
     * @param array<string, TermDefinition|null> $terms
     */
    public function __construct(
        public array $terms = [],
        public ?string $vocab = null,
        public ?self $previous = null,
    ) {
    }

    public function term(string $term): ?TermDefinition
    {
        return $this->terms[$term] ?? null;
    }

    /**
     * The IRI a value expands to: through the terms and the vocabulary mapping when `$vocab`, through a prefix when it
     * is a compact IRI; a keyword stays one, and a value that expands to nothing is null. What is returned may still
     * be a relative reference, which the caller refuses.
     */
    public function expandIri(string $value, bool $vocab): ?string
    {
        if (str_starts_with($value, '@')) {
            return \in_array($value, self::KEYWORDS, true) ? $value : null;
        }

        if ($vocab && \array_key_exists($value, $this->terms)) {
            return $this->terms[$value]?->iri;
        }

        if (str_contains($value, ':')) {
            [$prefix, $suffix] = explode(':', $value, 2);
            $definition = $this->terms[$prefix] ?? null;

            if ('_' !== $prefix && !str_starts_with($suffix, '//') && $definition instanceof TermDefinition && $definition->prefix && null !== $definition->iri) {
                return $definition->iri.$suffix;
            }

            return $value;
        }

        return $vocab && null !== $this->vocab ? $this->vocab.$value : $value;
    }
}
