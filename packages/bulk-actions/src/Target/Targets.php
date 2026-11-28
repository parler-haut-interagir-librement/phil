<?php

declare(strict_types=1);

namespace Phil\BulkActions\Target;

use Phil\BulkActions\Exception\InvalidInput;
use Phil\BulkActions\Exception\UnknownAction;

/**
 * The targets the configuration declares, by name.
 */
final readonly class Targets
{
    /** @var array<string, Target> */
    private array $targets;

    /**
     * @param iterable<Target> $targets
     */
    public function __construct(iterable $targets)
    {
        $byName = [];

        foreach ($targets as $target) {
            if (isset($byName[$target->name])) {
                throw InvalidInput::because('target "'.$target->name.'"', 'must be declared once');
            }

            $byName[$target->name] = $target;
        }

        $this->targets = $byName;
    }

    public function get(string $name): Target
    {
        return $this->targets[$name] ?? throw UnknownAction::target($name);
    }

    /**
     * The target whose class an outside tool names, such as an administration's entity class: the class must be the
     * target's own class or one of its subclasses.
     *
     * @param string $class the class the tool says its identifiers belong to
     */
    public function forClass(string $name, string $class): Target
    {
        $target = $this->get($name);

        if (!is_a($class, $target->class, true)) {
            throw InvalidInput::because('class of the selection', 'must be the class of target "'.$name.'"');
        }

        return $target;
    }
}
