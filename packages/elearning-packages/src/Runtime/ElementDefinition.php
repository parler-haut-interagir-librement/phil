<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Runtime;

/**
 * An element of the data model: its name, each index of a collection written `n` — `cmi.interactions.n.id` —, who
 * may read and write it, and what its value may be: a regular expression its whole value matches, written so that
 * PHP and the browser read it alike, a number of characters at most, a range for a number, and what it reads before
 * it is set. A null default means the element holds no value until it is set: SCORM 2004 answers `403` for it, SCORM
 * 1.2 the empty string.
 */
final readonly class ElementDefinition
{
    public function __construct(
        public string $name,
        public Access $access,
        public ?string $pattern = null,
        public ?int $maxChars = null,
        public ?float $min = null,
        public ?float $max = null,
        public ?string $default = null,
        public ValueCheck $check = ValueCheck::Pattern,
    ) {
    }

    /**
     * What the browser reads of the definition, with the keys it knows.
     *
     * @return array{access: string, pattern?: string, maxChars?: int, min?: float, max?: float, default?: string, check?: string}
     */
    public function describe(): array
    {
        $description = ['access' => $this->access->value];

        if (null !== $this->pattern) {
            $description['pattern'] = $this->pattern;
        }

        if (null !== $this->maxChars) {
            $description['maxChars'] = $this->maxChars;
        }

        if (null !== $this->min) {
            $description['min'] = $this->min;
        }

        if (null !== $this->max) {
            $description['max'] = $this->max;
        }

        if (null !== $this->default) {
            $description['default'] = $this->default;
        }

        if (ValueCheck::Pattern !== $this->check) {
            $description['check'] = $this->check->value;
        }

        return $description;
    }
}
