<?php

declare(strict_types=1);

namespace Phil\StructuredData\Validation;

use Phil\StructuredData\Graph;
use Phil\StructuredData\LanguageTag;
use Phil\StructuredData\Node;
use Phil\StructuredData\Reference;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;

/**
 * Checks a graph against the rules of its types: an error for each property a node needs and lacks, or holds in a form
 * nobody reads — a date that is not ISO 8601, an address that is not absolute, a currency that is not ISO 4217, a
 * language that is not BCP 47 —, a warning for each property it should have, for a reference to a node the graph
 * lacks, and for a node at the top without a type.
 *
 * Every node is checked, the nested ones too, each by the rules of its types and of their parents.
 */
final readonly class Validator
{
    /** @var list<string> */
    public const array DATE_PROPERTIES = ['startDate', 'endDate', 'datePublished', 'dateModified', 'dateCreated', 'uploadDate', 'priceValidUntil', 'validFrom', 'validThrough', 'birthDate', 'foundingDate'];

    /** @var list<string> */
    public const array URL_PROPERTIES = ['url', 'logo', 'image', 'sameAs', 'codeRepository', 'contentUrl', 'thumbnailUrl', 'downloadUrl', 'installUrl', 'item'];

    private const string DATE = '/^(?<year>\d{4})-(?<month>\d{2})-(?<day>\d{2})(?:T(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d(?:\.\d+)?)?(?:Z|[+-](?:[01]\d|2[0-3]):?[0-5]\d)?)?$/D';

    /** @var array<string, TypeRules> */
    private array $rules;

    /**
     * @param iterable<TypeRules>|null $rules          the rules of each type; `null` for those of `Rules::schemaOrg()`
     * @param bool                     $allowLocalhost whether an address on `localhost` or under `.localhost` is valid,
     *                                                 for development and tests
     */
    public function __construct(?iterable $rules = null, private bool $allowLocalhost = false)
    {
        $byType = [];

        foreach ($rules ?? Rules::schemaOrg() as $rule) {
            $byType[$rule->type] = $rule;
        }

        $this->rules = $byType;
    }

    /**
     * The validator with these rules added, replacing those of the same types.
     */
    public function withRules(TypeRules ...$rules): self
    {
        return new self([...$this->rules, ...$rules], $this->allowLocalhost);
    }

    public function validate(Graph $graph): Report
    {
        $issues = [];

        foreach ($graph->nodes() as $index => $node) {
            $label = $node->id ?? \sprintf('%s @graph[%d]', $node->types[0] ?? 'node', $index);

            if ([] === $node->types) {
                $issues[] = new Issue(Severity::Warning, 'untyped-node', $label, null, \sprintf('%s has no @type: no rule applies to it.', $label));
            }

            $this->check($graph, $node, $label, $issues);
        }

        return new Report($issues);
    }

    /**
     * @param list<Issue> $issues
     */
    private function check(Graph $graph, Node $node, string $label, array &$issues): void
    {
        [$required, $recommended] = $this->needs($node);

        foreach ($required as $group) {
            if (!$this->hasOne($node, $group)) {
                $issues[] = new Issue(Severity::Error, 'missing-property', $label, $group[0], \sprintf('%s needs %s.', $label, $this->names($group)));
            }
        }

        foreach ($recommended as $group) {
            if (!$this->hasOne($node, $group)) {
                $issues[] = new Issue(Severity::Warning, 'missing-recommended', $label, $group[0], \sprintf('%s should have %s.', $label, $this->names($group)));
            }
        }

        foreach ($node->properties() as $property => $value) {
            foreach (\is_array($value) ? $value : [$value] as $position => $item) {
                $path = $label.' › '.$property.(\is_array($value) ? '['.$position.']' : '');
                $this->checkValue($graph, $property, $item, $path, $label, $issues);
            }
        }
    }

    /**
     * @param list<Issue> $issues
     */
    private function checkValue(Graph $graph, string $property, mixed $value, string $path, string $label, array &$issues): void
    {
        $issue = match (true) {
            $value instanceof Node => $this->nested($graph, $value, $path, $issues),
            $value instanceof Reference => $graph->get($value->id) instanceof Node ? null : [Severity::Warning, 'unresolved-reference', \sprintf('%s refers to "%s", which the graph lacks.', $path, $value->id)],
            !\is_string($value) => null,
            \in_array($property, self::DATE_PROPERTIES, true) && !$this->isDate($value) => [Severity::Error, 'invalid-date', \sprintf('%s is an ISO 8601 date or date and time; "%s" is not.', $path, $value)],
            \in_array($property, self::URL_PROPERTIES, true) && !$this->isUrl($value) => [Severity::Error, 'invalid-url', \sprintf('%s is an absolute HTTP or HTTPS address; "%s" is not.', $path, $value)],
            'priceCurrency' === $property && 1 !== preg_match('/^[A-Z]{3}$/D', $value) => [Severity::Error, 'invalid-currency', \sprintf('%s is an ISO 4217 code of three capitals; "%s" is not.', $path, $value)],
            'inLanguage' === $property && !LanguageTag::isValid($value) => [Severity::Error, 'invalid-language', \sprintf('%s is a BCP 47 language tag; "%s" is not.', $path, $value)],
            default => null,
        };

        if (\is_array($issue)) {
            $issues[] = new Issue($issue[0], $issue[1], $label, $property, $issue[2]);
        }
    }

    /**
     * Checks a nested node by the rules of its own types; it raises no issue of the node that holds it.
     *
     * @param list<Issue> $issues
     */
    private function nested(Graph $graph, Node $node, string $path, array &$issues): null
    {
        $this->check($graph, $node, $path, $issues);

        return null;
    }

    /**
     * What the node needs and should have, by its types and their parents, each group once.
     *
     * @return array{array<string, list<string>>, array<string, list<string>>}
     */
    private function needs(Node $node): array
    {
        $required = [];
        $recommended = [];
        $seen = [];

        foreach ($node->types as $type) {
            while (null !== $type && !\in_array($type, $seen, true) && isset($this->rules[$type])) {
                $seen[] = $type;
                $rule = $this->rules[$type];

                foreach ($rule->required as $group) {
                    $required[implode('|', (array) $group)] = (array) $group;
                }

                foreach ($rule->recommended as $group) {
                    $recommended[implode('|', (array) $group)] = (array) $group;
                }

                $type = $rule->parent;
            }
        }

        return [$required, array_diff_key($recommended, $required)];
    }

    /**
     * @param list<string> $group
     */
    private function hasOne(Node $node, array $group): bool
    {
        return array_any($group, $node->has(...));
    }

    /**
     * @param list<string> $group
     */
    private function names(array $group): string
    {
        return 1 === \count($group) ? $group[0] : 'one of '.implode(', ', $group);
    }

    private function isDate(string $value): bool
    {
        return 1 === preg_match(self::DATE, $value, $parts) && checkdate((int) $parts['month'], (int) $parts['day'], (int) $parts['year']);
    }

    /**
     * An absolute `http` or `https` address as a browser reads it, by phil/url: no user name or password, which would
     * make `https://example.com@evil.example/` lead to evil.example, and a host that is a name, not an IP address. A
     * space or a control character, even at an end, makes it no address: the value is published as written.
     */
    private function isUrl(string $value): bool
    {
        if (1 === preg_match('/[\x00-\x20]/', $value)) {
            return false;
        }

        try {
            Url::fromString($value, allowLocalhost: $this->allowLocalhost);
        } catch (InvalidUrl) {
            return false;
        }

        return true;
    }
}
