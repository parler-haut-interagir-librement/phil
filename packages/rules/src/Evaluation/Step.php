<?php

declare(strict_types=1);

namespace Phil\Rules\Evaluation;

/**
 * One condition in the trace of an evaluation: where it stands in the rule, as the JSON Pointer of its node in the
 * rule's JSON form, what became of it, and why for a leaf.
 */
final readonly class Step
{
    /**
     * @param array<string, scalar|null> $details
     * @param list<Step>                 $children
     */
    public function __construct(
        public string $path,
        public NodeKind $kind,
        public Outcome $outcome,
        public ?string $type = null,
        public ?string $reason = null,
        public array $details = [],
        public array $children = [],
    ) {
    }

    /**
     * The leaves that decided this step's outcome: for `All` and `Any`, those of the children that share its outcome
     * (the failed child of a failed `All`, every child of a failed `Any`…); for `Not`, those of its condition.
     *
     * @return list<Step>
     */
    public function decisive(): array
    {
        if (NodeKind::Leaf === $this->kind) {
            return [$this];
        }

        $decisive = [];

        foreach ($this->children as $child) {
            if (NodeKind::Not === $this->kind || $child->outcome === $this->outcome) {
                array_push($decisive, ...$child->decisive());
            }
        }

        return $decisive;
    }

    /**
     * The step and its children as plain arrays, to show or to log.
     *
     * @return array{path: string, kind: string, outcome: string, type?: string, reason?: string, details?: array<string, scalar|null>, children?: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        $step = ['path' => $this->path, 'kind' => $this->kind->value, 'outcome' => $this->outcome->value];

        if (null !== $this->type) {
            $step['type'] = $this->type;
        }

        if (null !== $this->reason) {
            $step['reason'] = $this->reason;
        }

        if ([] !== $this->details) {
            $step['details'] = $this->details;
        }

        if ([] !== $this->children) {
            $step['children'] = array_map(static fn (Step $child): array => $child->toArray(), $this->children);
        }

        return $step;
    }
}
