<?php

declare(strict_types=1);

namespace Phil\Automations\Catalogue;

use Phil\Automations\Definition\ComponentType;
use Phil\Automations\Definition\Options;

/**
 * What a trigger or an action says of itself, for a no-code builder and for the validator: its type, a label, its
 * options, and, for an action, whether it can undo what it did.
 */
final readonly class ComponentDescription
{
    public string $type;

    /**
     * @param list<OptionSpec> $options
     */
    public function __construct(
        string $type,
        public string $label,
        public array $options = [],
        public bool $compensable = false,
    ) {
        $this->type = ComponentType::check($type);
    }

    /**
     * What is wrong with a set of options for this component: an option it does not take, one it needs, one of the
     * wrong type.
     *
     * @return list<string>
     */
    public function problems(Options $options): array
    {
        $problems = [];
        $known = [];

        foreach ($this->options as $spec) {
            $known[$spec->name] = true;
            $problem = $spec->problem($options);

            if (null !== $problem) {
                $problems[] = $problem;
            }
        }

        foreach (array_keys($options->values) as $name) {
            if (!isset($known[$name])) {
                $problems[] = \sprintf('"%s" takes no option "%s".', $this->type, $name);
            }
        }

        return $problems;
    }
}
