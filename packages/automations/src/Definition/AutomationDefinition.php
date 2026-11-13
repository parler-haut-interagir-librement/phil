<?php

declare(strict_types=1);

namespace Phil\Automations\Definition;

use Phil\Automations\Exception\InvalidDefinition;

/**
 * One version of an automation: the trigger it starts on and its steps, in order, branches holding their own.
 *
 * A run keeps the version it started with, so that a definition edited while runs wait never changes the steps of
 * those runs.
 */
final readonly class AutomationDefinition
{
    public const int MAX_STEPS = 200;

    public const int MAX_NAME_LENGTH = 120;

    /**
     * @param list<Step> $steps
     *
     * @throws InvalidDefinition when the version or the name is refused, two steps share an id, the steps are too
     *                           many or nested too deep
     */
    public function __construct(
        public AutomationId $id,
        public int $version,
        public string $name,
        public TriggerReference $trigger,
        public array $steps,
    ) {
        if ($version < 1) {
            throw InvalidDefinition::because('The version of a definition starts at 1.');
        }

        if ('' === trim($name) || mb_strlen($name) > self::MAX_NAME_LENGTH || 1 !== preg_match('//u', $name) || 1 === preg_match('/[\x00-\x1F\x7F]/', $name)) {
            throw InvalidDefinition::because(\sprintf('The name of a definition is not blank, at most %d characters, without control characters.', self::MAX_NAME_LENGTH));
        }

        if ([] === $steps) {
            throw InvalidDefinition::because('A definition holds at least one step.');
        }

        $ids = [];
        self::collect($steps, $ids, 1);
    }

    /**
     * Every step, branches included, in reading order.
     *
     * @return list<Step>
     */
    public function allSteps(): array
    {
        $ids = [];

        return self::collect($this->steps, $ids, 1);
    }

    public function first(): StepPath
    {
        return StepPath::top(0);
    }

    /**
     * The step at a path; `null` when the path leads nowhere in this definition.
     */
    public function stepAt(StepPath $path): ?Step
    {
        $step = $this->steps[$path->index] ?? null;

        foreach ($path->nested as [$branch, $index]) {
            if (!$step instanceof Step || StepKind::Branch !== $step->kind) {
                return null;
            }

            $step = $step->path($branch)[$index] ?? null;
        }

        return $step;
    }

    /**
     * The step that follows the one at a path once it is done: the next in its list, or, at the end of a branch's
     * path, the step after that branch; `null` at the end of the definition.
     */
    public function after(StepPath $path): ?StepPath
    {
        $next = $path->sibling();

        while (!$this->stepAt($next) instanceof Step) {
            $parent = $next->parent();

            if (!$parent instanceof StepPath) {
                return null;
            }

            $next = $parent->sibling();
        }

        return $next;
    }

    /**
     * The first step of a path of a branch, or the step after the branch when that path is empty.
     */
    public function enter(StepPath $branch, BranchPath $path): ?StepPath
    {
        $first = $branch->into($path);

        return $this->stepAt($first) instanceof Step ? $first : $this->after($branch);
    }

    /**
     * @param list<Step>          $steps
     * @param array<string, true> $ids
     *
     * @return list<Step>
     */
    private static function collect(array $steps, array &$ids, int $depth): array
    {
        if ($depth > StepPath::MAX_DEPTH) {
            throw InvalidDefinition::because(\sprintf('Branches nest at most %d levels deep.', StepPath::MAX_DEPTH - 1));
        }

        $all = [];

        foreach ($steps as $step) {
            if (isset($ids[$step->id])) {
                throw InvalidDefinition::because(\sprintf('Two steps share the id "%s".', $step->id));
            }

            $ids[$step->id] = true;
            $all[] = $step;

            if (\count($ids) > self::MAX_STEPS) {
                throw InvalidDefinition::because(\sprintf('A definition holds at most %d steps.', self::MAX_STEPS));
            }

            if (StepKind::Branch === $step->kind) {
                array_push($all, ...self::collect($step->then, $ids, $depth + 1), ...self::collect($step->else, $ids, $depth + 1));
            }
        }

        return $all;
    }
}
