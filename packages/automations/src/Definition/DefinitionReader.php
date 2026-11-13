<?php

declare(strict_types=1);

namespace Phil\Automations\Definition;

use JsonException;
use Phil\Automations\Exception\InvalidDefinition;

/**
 * Reads a definition from the JSON a no-code builder saves, strictly: every key known, every value of its type, the
 * size, the nesting and the number of steps bounded. It throws `InvalidDefinition` on anything else and never another
 * exception, whatever the input.
 *
 * ```json
 * {"id": "welcome", "version": 1, "name": "Welcome",
 *  "trigger": {"type": "newsletter.confirmed", "options": {"list": "news"}},
 *  "steps": [
 *    {"id": "hello", "type": "action", "action": "newsletter.send", "options": {"template": "hello"}, "retries": 3},
 *    {"id": "wait", "type": "delay", "seconds": 86400},
 *    {"id": "pro", "type": "branch", "condition": {"language": "expression", "source": "payload['plan'] == 'pro'"},
 *     "then": [...], "else": [...]}
 *  ]}
 * ```
 */
final readonly class DefinitionReader
{
    public const int MAX_BYTES = 262_144;

    private const int JSON_DEPTH = 64;

    public function read(string $json): AutomationDefinition
    {
        if (\strlen($json) > self::MAX_BYTES) {
            throw InvalidDefinition::because(\sprintf('A definition is at most %d bytes of JSON.', self::MAX_BYTES));
        }

        try {
            $data = json_decode($json, true, self::JSON_DEPTH, \JSON_THROW_ON_ERROR | \JSON_BIGINT_AS_STRING);
        } catch (JsonException $jsonException) {
            throw InvalidDefinition::because('A definition is a JSON object: '.$jsonException->getMessage());
        }

        if (!\is_array($data)) {
            throw InvalidDefinition::because('A definition is a JSON object.');
        }

        return $this->fromArray($data);
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public function fromArray(array $data): AutomationDefinition
    {
        $this->keys($data, ['id', 'version', 'name', 'trigger', 'steps'], ['id', 'version', 'name', 'trigger', 'steps'], 'the definition');
        $trigger = $this->object($data['trigger'], 'the trigger');
        $this->keys($trigger, ['type', 'options'], ['type'], 'the trigger');

        return new AutomationDefinition(
            AutomationId::fromString($this->string($data['id'], 'id')),
            $this->integer($data['version'], 'version'),
            $this->string($data['name'], 'name'),
            TriggerReference::of($this->string($trigger['type'], 'the trigger type'), $this->options($trigger['options'] ?? [], 'the trigger')),
            $this->steps($data['steps'], 1),
        );
    }

    /**
     * @return list<Step>
     */
    private function steps(mixed $steps, int $depth): array
    {
        if (!\is_array($steps) || !array_is_list($steps)) {
            throw InvalidDefinition::because('Steps are a JSON array.');
        }

        if ($depth > StepPath::MAX_DEPTH || \count($steps) > AutomationDefinition::MAX_STEPS) {
            throw InvalidDefinition::because(\sprintf('A definition holds at most %d steps, nested at most %d levels deep.', AutomationDefinition::MAX_STEPS, StepPath::MAX_DEPTH - 1));
        }

        return array_map(fn (mixed $step): Step => $this->step($this->object($step, 'a step'), $depth), $steps);
    }

    /**
     * @param array<array-key, mixed> $step
     */
    private function step(array $step, int $depth): Step
    {
        $id = $this->string($step['id'] ?? null, 'the id of a step');
        $kind = StepKind::tryFrom($this->string($step['type'] ?? null, 'the type of a step'));
        $where = \sprintf('the step "%s"', $id);

        switch ($kind) {
            case StepKind::Action:
                $this->keys($step, ['id', 'type', 'action', 'options', 'retries'], ['id', 'type', 'action'], $where);

                return Step::action($id, $this->string($step['action'], 'the action of '.$where), $this->options($step['options'] ?? [], $where), $this->integer($step['retries'] ?? 0, 'the retries of '.$where));
            case StepKind::Delay:
                $this->keys($step, ['id', 'type', 'seconds'], ['id', 'type', 'seconds'], $where);

                return Step::delay($id, $this->integer($step['seconds'], 'the seconds of '.$where));
            case StepKind::Filter:
                $this->keys($step, ['id', 'type', 'condition'], ['id', 'type', 'condition'], $where);

                return Step::filter($id, $this->condition($step['condition'], $where));
            case StepKind::Branch:
                $this->keys($step, ['id', 'type', 'condition', 'then', 'else'], ['id', 'type', 'condition', 'then'], $where);

                return Step::branch($id, $this->condition($step['condition'], $where), $this->steps($step['then'], $depth + 1), $this->steps($step['else'] ?? [], $depth + 1));
            default:
                throw InvalidDefinition::because(\sprintf('The type of %s is "action", "delay", "filter" or "branch".', $where));
        }
    }

    private function condition(mixed $condition, string $where): Condition
    {
        $condition = $this->object($condition, 'the condition of '.$where);
        $this->keys($condition, ['language', 'source'], ['source'], 'the condition of '.$where);

        return Condition::of($this->string($condition['language'] ?? 'expression', 'the language of '.$where), $this->string($condition['source'], 'the condition of '.$where));
    }

    private function options(mixed $options, string $where): Options
    {
        if ([] === $options) {
            return Options::none();
        }

        return Options::of($this->object($options, 'the options of '.$where));
    }

    /**
     * Refuses an unknown key or a missing one.
     *
     * @param array<array-key, mixed> $data
     * @param list<string>            $known
     * @param list<string>            $required
     */
    private function keys(array $data, array $known, array $required, string $where): void
    {
        foreach (array_keys($data) as $key) {
            if (!\in_array($key, $known, true)) {
                throw InvalidDefinition::because(\sprintf('%s has no key "%s".', ucfirst($where), $key));
            }
        }

        foreach ($required as $key) {
            if (!\array_key_exists($key, $data)) {
                throw InvalidDefinition::because(\sprintf('%s needs the key "%s".', ucfirst($where), $key));
            }
        }
    }

    /**
     * @return array<array-key, mixed>
     */
    private function object(mixed $value, string $what): array
    {
        if (!\is_array($value) || ([] !== $value && array_is_list($value))) {
            throw InvalidDefinition::because(\sprintf('%s is a JSON object.', ucfirst($what)));
        }

        return $value;
    }

    private function string(mixed $value, string $what): string
    {
        if (!\is_string($value)) {
            throw InvalidDefinition::because(\sprintf('%s is a string.', ucfirst($what)));
        }

        return $value;
    }

    private function integer(mixed $value, string $what): int
    {
        if (!\is_int($value)) {
            throw InvalidDefinition::because(\sprintf('%s is an integer.', ucfirst($what)));
        }

        return $value;
    }
}
