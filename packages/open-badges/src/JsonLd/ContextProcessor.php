<?php

declare(strict_types=1);

namespace Phil\OpenBadges\JsonLd;

use Phil\OpenBadges\Contract\ContextLoader;
use Phil\OpenBadges\Exception\InvalidCredential;

/**
 * The context processing of JSON-LD 1.1, for the subset verifiable credentials use: contexts by URL through the
 * loader, embedded contexts, `@vocab`, `@propagate`, terms with `@id`, `@var`, `@container` (`@set`, `@list`,
 * `@graph`), `@context` and `@prefix`, keyword aliases and compact IRIs. `@protected` is accepted and not enforced.
 * Anything else — `@import`, `@base`, a default `@language` or `@direction`, reverse properties, nesting, indexes —
 * is refused rather than read differently than a full processor would.
 *
 * @internal
 */
final readonly class ContextProcessor
{
    /**
     * How deep contexts may load other contexts.
     */
    public const int MAX_DEPTH = 8;

    private const array GEN_DELIMS = [':', '/', '?', '#', '[', ']', '@'];

    private const array CONTAINERS = ['@set' => null, '@list' => '@list', '@graph' => '@graph'];

    public function __construct(
        private ContextLoader $loader,
    ) {
    }

    public function process(ActiveContext $active, mixed $local, bool $propagate = true, int $depth = 0): ActiveContext
    {
        if ($depth > self::MAX_DEPTH) {
            throw InvalidCredential::because('The JSON-LD contexts load each other too deeply.');
        }

        $terms = $active->terms;
        $vocab = $active->vocab;
        $previous = !$propagate && !$active->previous instanceof ActiveContext ? $active : $active->previous;

        foreach (\is_array($local) && array_is_list($local) ? $local : [$local] as $context) {
            if (null === $context) {
                [$terms, $vocab] = [[], null];
            } elseif (\is_string($context)) {
                $loaded = $this->process(new ActiveContext($terms, $vocab), $this->loader->load($context)['@context'] ?? null, depth: $depth + 1);
                [$terms, $vocab] = [$loaded->terms, $loaded->vocab];
            } elseif (\is_array($context)) {
                if (false === ($context['@propagate'] ?? true) && !$previous instanceof ActiveContext) {
                    $previous = $active;
                }

                [$terms, $vocab] = $this->local($context, $terms, $vocab);
            } else {
                throw InvalidCredential::because('A JSON-LD context is a URL, an object, null or a list of them.');
            }
        }

        return new ActiveContext($terms, $vocab, $previous);
    }

    /**
     * @param array<array-key, mixed>            $context
     * @param array<string, TermDefinition|null> $terms
     *
     * @return array{array<string, TermDefinition|null>, ?string}
     */
    private function local(array $context, array $terms, ?string $vocab): array
    {
        foreach (['@import', '@base', '@language', '@direction'] as $unsupported) {
            if (\array_key_exists($unsupported, $context)) {
                throw InvalidCredential::because(\sprintf('A JSON-LD context with "%s" is not processed.', $unsupported));
            }
        }

        if (\array_key_exists('@vocab', $context)) {
            $mapping = $context['@vocab'];

            if (null !== $mapping && !\is_string($mapping)) {
                throw InvalidCredential::because('The "@vocab" of a JSON-LD context is an IRI or null.');
            }

            $vocab = null === $mapping ? null : new ActiveContext($terms, $vocab)->expandIri($mapping, true);
        }

        $defining = new TermDefining($context, $terms, $vocab);

        foreach (array_keys($context) as $term) {
            if (!\in_array($term, ['@version', '@vocab', '@protected', '@propagate'], true)) {
                $this->term($defining, (string) $term);
            }
        }

        return [$defining->terms, $vocab];
    }

    private function term(TermDefining $defining, string $term): void
    {
        $state = $defining->defined[$term] ?? null;

        if (false === $state) {
            throw InvalidCredential::because(\sprintf('The JSON-LD term "%s" is defined through itself.', $term));
        }

        if (null === $state) {
            $this->define($defining, $term);
        }
    }

    private function define(TermDefining $defining, string $term): void
    {
        if (str_starts_with($term, '@')) {
            throw InvalidCredential::because(\sprintf('A JSON-LD context does not redefine the keyword "%s".', $term));
        }

        $defining->defined[$term] = false;
        $value = $defining->context[$term];

        if (null === $value || (\is_array($value) && \array_key_exists('@id', $value) && null === $value['@id'])) {
            $defining->terms[$term] = null;
            $defining->defined[$term] = true;

            return;
        }

        $simple = \is_string($value);
        $value = $simple ? ['@id' => $value] : $value;

        if (!\is_array($value)) {
            throw InvalidCredential::because(\sprintf('The JSON-LD term "%s" is defined by an IRI or an object.', $term));
        }

        foreach (array_keys($value) as $key) {
            if (!\in_array($key, ['@id', '@type', '@container', '@context', '@prefix', '@protected'], true)) {
                throw InvalidCredential::because(\sprintf('The JSON-LD term "%s" uses "%s", which is not processed.', $term, $key));
            }
        }

        $iri = $this->iri($defining, $term, $value['@id'] ?? null);

        $defining->terms[$term] = new TermDefinition(
            $iri,
            $this->type($defining, $term, $value['@type'] ?? null),
            $this->container($term, $value['@container'] ?? null),
            \array_key_exists('@context', $value),
            $value['@context'] ?? null,
            \is_bool($value['@prefix'] ?? null) ? $value['@prefix'] : $simple && !str_contains($term, ':') && !str_contains($term, '/') && \in_array(substr($iri, -1), self::GEN_DELIMS, true),
        );
        $defining->defined[$term] = true;
    }

    private function iri(TermDefining $defining, string $term, mixed $id): string
    {
        if (null !== $id && $id !== $term) {
            if (!\is_string($id)) {
                throw InvalidCredential::because(\sprintf('The "@id" of the JSON-LD term "%s" is a string.', $term));
            }

            $iri = $this->expand($defining, $id);

            if (null === $iri || (!str_starts_with($iri, '@') && !str_contains($iri, ':'))) {
                throw InvalidCredential::because(\sprintf('The JSON-LD term "%s" does not expand to an absolute IRI.', $term));
            }

            return $iri;
        }

        if (str_contains($term, ':')) {
            return (string) $this->expand($defining, $term);
        }

        if (null === $defining->vocab) {
            throw InvalidCredential::because(\sprintf('The JSON-LD term "%s" has no IRI.', $term));
        }

        return $defining->vocab.$term;
    }

    private function type(TermDefining $defining, string $term, mixed $type): ?string
    {
        if (null === $type) {
            return null;
        }

        $expanded = \is_string($type) ? $this->expand($defining, $type) : null;

        if (null === $expanded || (str_starts_with($expanded, '@') ? !\in_array($expanded, ['@id', '@vocab', '@json', '@none'], true) : !str_contains($expanded, ':'))) {
            throw InvalidCredential::because(\sprintf('The type of the JSON-LD term "%s" is not processed.', $term));
        }

        return $expanded;
    }

    private function container(string $term, mixed $container): ?string
    {
        $kept = null;

        foreach (\is_array($container) ? $container : (null === $container ? [] : [$container]) as $one) {
            if (!\is_string($one) || !\array_key_exists($one, self::CONTAINERS)) {
                throw InvalidCredential::because(\sprintf('The container of the JSON-LD term "%s" is not processed.', $term));
            }

            $kept ??= self::CONTAINERS[$one];
        }

        return $kept;
    }

    /**
     * An IRI of a term definition, through the terms the same context defines.
     */
    private function expand(TermDefining $defining, string $value): ?string
    {
        $prefix = explode(':', $value)[0];

        if (!str_starts_with($value, '@') && \array_key_exists($prefix, $defining->context)) {
            $this->term($defining, $prefix);
        }

        return new ActiveContext($defining->terms, $defining->vocab)->expandIri($value, true);
    }
}
