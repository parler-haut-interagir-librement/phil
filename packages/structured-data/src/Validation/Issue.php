<?php

declare(strict_types=1);

namespace Phil\StructuredData\Validation;

/**
 * One thing a graph lacks or holds wrongly: its weight, a code to sort or silence it by, the node — its `@id`, or its
 * type and place in the graph —, the property, and a sentence that says it.
 */
final readonly class Issue
{
    public function __construct(
        public Severity $severity,
        public string $code,
        public string $node,
        public ?string $property,
        public string $message,
    ) {
    }

    /**
     * @return array{severity: string, code: string, node: string, property: ?string, message: string}
     */
    public function toArray(): array
    {
        return ['severity' => $this->severity->value, 'code' => $this->code, 'node' => $this->node, 'property' => $this->property, 'message' => $this->message];
    }
}
