<?php

declare(strict_types=1);

namespace Phil\Automations\Definition;

/**
 * Writes a definition as the JSON `DefinitionReader` reads: keys in a fixed order, optional keys left out when they
 * hold their default, so that one definition always gives the same bytes.
 */
final readonly class DefinitionWriter
{
    public function write(AutomationDefinition $definition): string
    {
        return json_encode($this->toArray($definition), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(AutomationDefinition $definition): array
    {
        $trigger = ['type' => $definition->trigger->type];

        if ([] !== $definition->trigger->options->values) {
            $trigger['options'] = $definition->trigger->options->values;
        }

        return [
            'id' => $definition->id->value,
            'version' => $definition->version,
            'name' => $definition->name,
            'trigger' => $trigger,
            'steps' => self::steps($definition->steps),
        ];
    }

    /**
     * @param list<Step> $steps
     *
     * @return list<array<string, mixed>>
     */
    private static function steps(array $steps): array
    {
        return array_map(self::step(...), $steps);
    }

    /**
     * @return array<string, mixed>
     */
    private static function step(Step $step): array
    {
        $data = ['id' => $step->id, 'type' => $step->kind->value];

        switch ($step->kind) {
            case StepKind::Action:
                $data['action'] = $step->action;

                if ([] !== $step->options->values) {
                    $data['options'] = $step->options->values;
                }

                if (0 !== $step->retries) {
                    $data['retries'] = $step->retries;
                }

                break;
            case StepKind::Delay:
                $data['seconds'] = $step->seconds;

                break;
            case StepKind::Filter:
            case StepKind::Branch:
                $data['condition'] = ['language' => $step->condition?->language, 'source' => $step->condition?->source];

                if (StepKind::Branch === $step->kind) {
                    $data['then'] = self::steps($step->then);

                    if ([] !== $step->else) {
                        $data['else'] = self::steps($step->else);
                    }
                }

                break;
        }

        return $data;
    }
}
