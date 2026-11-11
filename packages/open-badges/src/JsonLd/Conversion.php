<?php

declare(strict_types=1);

namespace Phil\OpenBadges\JsonLd;

use Phil\OpenBadges\Exception\InvalidCredential;
use Phil\OpenBadges\Rdf\NQuads;

/**
 * One document turned into quads: the expansion and the RDF conversion of JSON-LD 1.1 in a single walk, each node
 * read in its active context — reverted in the nodes a type-scoped context does not propagate to, then given the
 * property-scoped, embedded and type-scoped contexts, in that order.
 *
 * @internal
 */
final class Conversion
{
    public const int MAX_DEPTH = 32;

    private const string TYPE = '<'.NQuads::RDF.'type>';

    private const string ABSOLUTE = '/^[a-zA-Z][a-zA-Z0-9+.\-]*:[^\x00-\x20<>"{}|^`\\\\]*$/D';

    private int $blanks = 0;

    /**
     * @var array<string, array{string, string, string, string}>
     */
    private array $quads = [];

    public function __construct(
        private readonly ContextProcessor $contexts,
        private readonly int $max,
    ) {
    }

    /**
     * @param array<array-key, mixed> $document
     *
     * @return list<array{string, string, string, string}>
     */
    public function document(array $document): array
    {
        if (array_is_list($document)) {
            throw InvalidCredential::because('A JSON-LD document read as a credential is an object.');
        }

        $this->node($document, new ActiveContext(), null, '', 0);

        return array_values($this->quads);
    }

    /**
     * @param array<array-key, mixed> $element
     *
     * @return string the node's subject
     */
    private function node(array $element, ActiveContext $active, ?TermDefinition $property, string $graph, int $depth): string
    {
        if ($depth > self::MAX_DEPTH) {
            throw InvalidCredential::because(\sprintf('A JSON-LD document nests nodes at most %d deep.', self::MAX_DEPTH));
        }

        $context = $active->previous instanceof ActiveContext && !$this->isReference($element, $active) ? $active->previous : $active;

        if ($property instanceof TermDefinition && $property->hasContext) {
            $context = $this->contexts->process($context, $property->context);
        }

        if (\array_key_exists('@context', $element)) {
            $context = $this->contexts->process($context, $element['@context']);
        }

        $typeScoped = $context;
        $types = $this->types($element, $context);

        foreach ($types as $type) {
            $definition = $typeScoped->term($type);

            if ($definition instanceof TermDefinition && $definition->hasContext) {
                $context = $this->contexts->process($context, $definition->context, false);
            }
        }

        $subject = $this->subject($element, $context);

        foreach ($types as $type) {
            $this->emit($subject, self::TYPE, $this->reference($typeScoped->expandIri($type, true), \sprintf('The type "%s"', $type)), $graph);
        }

        foreach ($element as $key => $value) {
            $key = (string) $key;
            $iri = $context->expandIri($key, true);

            if (\in_array($iri, ['@context', '@id', '@type'], true) || '@context' === $key) {
                continue;
            }

            if (null === $iri || str_starts_with($iri, '@') || 1 !== preg_match(self::ABSOLUTE, $iri)) {
                throw InvalidCredential::because(\sprintf('The property "%s" expands to no absolute IRI in the contexts of the document.', $key));
            }

            $this->property($subject, NQuads::iri($iri), $value, $context, $context->term($key), $graph, $depth);
        }

        return $subject;
    }

    /**
     * @param array<array-key, mixed> $element
     */
    private function isReference(array $element, ActiveContext $context): bool
    {
        return 1 === \count($element) && '@id' === $context->expandIri((string) array_key_first($element), true);
    }

    /**
     * The node's types as written, in lexicographic order.
     *
     * @param array<array-key, mixed> $element
     *
     * @return list<string>
     */
    private function types(array $element, ActiveContext $context): array
    {
        $types = [];

        foreach ($element as $key => $value) {
            if ('@type' !== $context->expandIri((string) $key, true)) {
                continue;
            }

            foreach (\is_array($value) ? $value : [$value] as $type) {
                if (!\is_string($type)) {
                    throw InvalidCredential::because('The type of a JSON-LD node is a string or a list of strings.');
                }

                $types[] = $type;
            }
        }

        sort($types, \SORT_STRING);

        return $types;
    }

    /**
     * @param array<array-key, mixed> $element
     */
    private function subject(array $element, ActiveContext $context): string
    {
        foreach ($element as $key => $value) {
            if ('@id' === $context->expandIri((string) $key, true)) {
                if (!\is_string($value)) {
                    throw InvalidCredential::because('The identifier of a JSON-LD node is a string.');
                }

                return $this->reference($context->expandIri($value, false), \sprintf('The identifier "%s"', $value));
            }
        }

        return '_:b'.$this->blanks++;
    }

    private function property(string $subject, string $predicate, mixed $value, ActiveContext $context, ?TermDefinition $definition, string $graph, int $depth): void
    {
        if ('@json' === $definition?->type) {
            throw InvalidCredential::because('A JSON literal is not processed.');
        }

        $values = \is_array($value) && array_is_list($value) ? $value : [$value];

        if ('@list' === $definition?->container) {
            $this->emit($subject, $predicate, $this->list($values, $context, $definition, $graph, $depth), $graph);

            return;
        }

        foreach ($values as $one) {
            $object = $this->object($one, $context, $definition, $graph, $depth);

            if (null !== $object) {
                $this->emit($subject, $predicate, $object, $graph);
            }
        }
    }

    private function object(mixed $value, ActiveContext $context, ?TermDefinition $definition, string $graph, int $depth): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!\is_array($value)) {
            if (!\is_scalar($value)) {
                throw InvalidCredential::because('A JSON-LD document holds only JSON values.');
            }

            $scoped = $definition instanceof TermDefinition && $definition->hasContext ? $this->contexts->process($context, $definition->context) : $context;

            return $this->scalar($value, $definition, $scoped);
        }

        if (array_is_list($value)) {
            throw InvalidCredential::because('A JSON-LD value is not a list nested in a list.');
        }

        if (\array_key_exists('@value', $value)) {
            return $this->value($value, $context);
        }

        if (\array_key_exists('@list', $value)) {
            $items = \is_array($value['@list']) && array_is_list($value['@list']) ? $value['@list'] : [$value['@list']];

            return $this->list($items, $context, $definition, $graph, $depth);
        }

        if ('@graph' === $definition?->container) {
            $name = '_:b'.$this->blanks++;
            $this->node($value, $context, $definition, $name, $depth + 1);

            return $name;
        }

        return $this->node($value, $context, $definition, $graph, $depth + 1);
    }

    private function scalar(bool|float|int|string $value, ?TermDefinition $definition, ActiveContext $context): string
    {
        $type = $definition?->type;

        if (\is_string($value) && \in_array($type, ['@id', '@vocab'], true)) {
            return $this->reference($context->expandIri($value, '@vocab' === $type), \sprintf('The value "%s"', $value));
        }

        return $this->literal($value, \in_array($type, ['@id', '@vocab', '@none'], true) ? null : $type);
    }

    /**
     * @param array<array-key, mixed> $value
     */
    private function value(array $value, ActiveContext $context): ?string
    {
        $literal = $value['@value'];
        $type = $value['@type'] ?? null;
        $language = $value['@language'] ?? null;

        if ([] !== array_diff(array_keys($value), ['@value', '@type', '@language']) || (null !== $type && null !== $language)) {
            throw InvalidCredential::because('A JSON-LD value object has "@value" and a "@type" or a "@language".');
        }

        if (null === $literal) {
            return null;
        }

        if (!\is_scalar($literal) || (null !== $type && !\is_string($type)) || (null !== $language && (!\is_string($language) || !\is_string($literal)))) {
            throw InvalidCredential::because('A JSON-LD value object holds a scalar, a type IRI or a language tag.');
        }

        if (\is_string($language)) {
            return NQuads::literal($literal, language: $language);
        }

        $datatype = null === $type ? null : $context->expandIri($type, true);

        if (null !== $datatype && 1 !== preg_match(self::ABSOLUTE, $datatype)) {
            throw InvalidCredential::because(\sprintf('The type "%s" of a JSON-LD value expands to no absolute IRI.', $type));
        }

        return $this->literal($literal, $datatype);
    }

    /**
     * @param list<mixed> $items
     */
    private function list(array $items, ActiveContext $context, ?TermDefinition $definition, string $graph, int $depth): string
    {
        $objects = [];

        foreach ($items as $item) {
            $object = $this->object($item, $context, $definition, $graph, $depth);

            if (null !== $object) {
                $objects[] = $object;
            }
        }

        $rest = '<'.NQuads::RDF.'nil>';

        foreach (array_reverse($objects) as $object) {
            $node = '_:b'.$this->blanks++;
            $this->emit($node, '<'.NQuads::RDF.'first>', $object, $graph);
            $this->emit($node, '<'.NQuads::RDF.'rest>', $rest, $graph);
            $rest = $node;
        }

        return $rest;
    }

    private function literal(bool|float|int|string $value, ?string $datatype): string
    {
        if (\is_bool($value)) {
            return NQuads::literal($value ? 'true' : 'false', $datatype ?? NQuads::XSD.'boolean');
        }

        if (\is_string($value)) {
            return NQuads::literal($value, $datatype ?? NQuads::XSD.'string');
        }

        if (NQuads::XSD.'double' === $datatype || (\is_float($value) && (0.0 !== fmod($value, 1.0) || abs($value) >= 1e21))) {
            return NQuads::literal($this->double((float) $value), $datatype ?? NQuads::XSD.'double');
        }

        return NQuads::literal(\is_int($value) ? (string) $value : \sprintf('%.0f', $value), $datatype ?? NQuads::XSD.'integer');
    }

    /**
     * The canonical lexical form of an `xsd:double`: one digit before the point, no trailing zero after it but one,
     * and the exponent without its sign when positive nor leading zeros.
     */
    private function double(float $value): string
    {
        [$mantissa, $exponent] = explode('E', \sprintf('%.15E', $value));
        $mantissa = rtrim($mantissa, '0');

        return (str_ends_with($mantissa, '.') ? $mantissa.'0' : $mantissa).'E'.(int) $exponent;
    }

    private function reference(?string $iri, string $what): string
    {
        if (null !== $iri && str_starts_with($iri, '_:')) {
            return '_:d'.sodium_bin2hex($iri);
        }

        if (null === $iri || 1 !== preg_match(self::ABSOLUTE, $iri)) {
            throw InvalidCredential::because(\sprintf('%s expands to no absolute IRI in the contexts of the document.', $what));
        }

        return NQuads::iri($iri);
    }

    private function emit(string $subject, string $predicate, string $object, string $graph): void
    {
        $quad = [$subject, $predicate, $object, $graph];
        $key = implode(' ', $quad);

        if (!isset($this->quads[$key]) && \count($this->quads) >= $this->max) {
            throw InvalidCredential::because(\sprintf('A JSON-LD document yields at most %d quads.', $this->max));
        }

        $this->quads[$key] = $quad;
    }
}
