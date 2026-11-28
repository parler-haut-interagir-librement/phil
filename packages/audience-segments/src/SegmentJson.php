<?php

declare(strict_types=1);

namespace Phil\AudienceSegments;

use JsonException;
use Phil\AudienceSegments\Exception\InvalidSegment;
use Phil\Rules\Exception\InvalidRule;
use Phil\Rules\Exception\LimitExceeded;
use Phil\Rules\Json\RuleReader;
use Phil\Rules\Json\RuleWriter;
use Phil\Rules\Limits;
use Phil\Rules\Rule;
use Phil\Rules\Type\ConditionTypes;

/**
 * The query language of segments, shared by the API and a builder: the versioned JSON form of phil/rules with the
 * segment's name beside its condition.
 *
 *     {"version": 1, "name": "Spring fair buyers", "condition": {"type": "ticket.holder", "operands": {"event": "spring-fair"}}}
 *
 * The identifier is the application's, given when reading, never stored in the definition.
 */
final readonly class SegmentJson
{
    private RuleReader $reader;

    private RuleWriter $writer;

    public function __construct(ConditionTypes $types, private Limits $limits = new Limits())
    {
        $this->reader = new RuleReader($types, $limits);
        $this->writer = new RuleWriter();
    }

    /**
     * @throws InvalidSegment when the definition is not the segment form, or the segment is refused
     * @throws InvalidRule    when its condition is refused
     * @throws LimitExceeded  when its condition is larger or deeper than the limits
     */
    public function read(string $id, string $json): Segment
    {
        if (\strlen($json) > $this->limits->maxBytes) {
            throw LimitExceeded::bytes(\strlen($json), $this->limits->maxBytes);
        }

        try {
            $definition = json_decode($json, true, 2 * $this->limits->maxDepth + 4, \JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            if (\JSON_ERROR_DEPTH === $jsonException->getCode()) {
                throw LimitExceeded::depth(Rule::ROOT, $this->limits->maxDepth);
            }

            throw InvalidRule::notJson($jsonException->getMessage());
        }

        if (!\is_array($definition)) {
            throw InvalidSegment::malformed('a segment is an object with "version", "name" and "condition"');
        }

        return $this->fromArray($id, $definition);
    }

    /**
     * @param array<array-key, mixed> $definition
     */
    public function fromArray(string $id, array $definition): Segment
    {
        if (array_is_list($definition) || [] !== array_diff(array_keys($definition), ['version', 'name', 'condition'])) {
            throw InvalidSegment::malformed('a segment is an object with "version", "name" and "condition" only');
        }

        $name = $definition['name'] ?? null;

        if (!\is_string($name)) {
            throw InvalidSegment::name();
        }

        unset($definition['name']);

        return new Segment($id, $name, $this->reader->fromArray($definition));
    }

    /**
     * @return array{version: int, name: string, condition: array<string, mixed>}
     */
    public function toArray(Segment $segment): array
    {
        $rule = $this->writer->toArray($segment->rule);

        return ['version' => $rule['version'], 'name' => $segment->name, 'condition' => $rule['condition']];
    }

    public function write(Segment $segment): string
    {
        $rule = $this->writer->toJson($segment->rule);
        $name = json_encode($segment->name, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);

        // The rule's canonical form, with the name after the version.
        $head = '{"version":'.Rule::VERSION.',';

        return $head.'"name":'.$name.','.substr($rule, \strlen($head));
    }

    /**
     * The fingerprint of the segment's rule: two segments with the same fingerprint select the same members.
     */
    public function fingerprint(Segment $segment): string
    {
        return $this->writer->fingerprint($segment->rule);
    }
}
