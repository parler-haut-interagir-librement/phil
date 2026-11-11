<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Infrastructure\Workflow;

use BackedEnum;
use InvalidArgumentException;
use Phil\Enums\Meta\Reflection;
use UnitEnum;

/**
 * The configuration of a Symfony state machine built from the `#[TransitionsTo]` of an enum's cases: one place per
 * case value, one transition per declared move — named `<from>_to_<to>` —, the initial marking on the cases no case
 * moves to, and a `method` marking store on the property that holds the case.
 *
 * The bundle prepends it to FrameworkBundle's `workflows`, so the guards, events, history and audit trail of the
 * component apply to the lifecycle the enum declares.
 */
final class EnumWorkflows
{
    private function __construct()
    {
    }

    /**
     * @param class-string<UnitEnum> $enum
     * @param list<class-string>     $supports
     *
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException when the enum is not backed, or declares no transition
     */
    public static function definition(string $enum, array $supports, string $property): array
    {
        if (!is_subclass_of($enum, BackedEnum::class)) {
            throw new InvalidArgumentException(\sprintf('A workflow marks its subject with backed cases; %s is not a backed enum.', $enum));
        }

        $cases = $enum::cases();
        $place = static fn (BackedEnum $case): string => (string) $case->value;
        $transitions = [];
        $reached = [];

        foreach ($cases as $from) {
            foreach (Reflection::transitions($from) as $to) {
                \assert($to instanceof BackedEnum);
                $transitions[$place($from).'_to_'.$place($to)] = ['from' => [$place($from)], 'to' => [$place($to)]];
                $reached[] = $to;
            }
        }

        if ([] === $transitions) {
            throw new InvalidArgumentException(\sprintf('%s declares no #[TransitionsTo]: it has no workflow.', $enum));
        }

        return [
            'type' => 'state_machine',
            'supports' => $supports,
            'marking_store' => ['type' => 'method', 'property' => $property],
            'places' => array_map($place, $cases),
            'initial_marking' => array_map($place, array_values(array_filter($cases, static fn (BackedEnum $case): bool => !\in_array($case, $reached, true)))),
            'transitions' => $transitions,
        ];
    }
}
