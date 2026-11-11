<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Infrastructure\Configuration;

use DateTimeImmutable;
use DateTimeZone;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\FeatureFlagsBundle\Application\Circuit\FlagCircuitOverride;
use Phil\FeatureFlagsBundle\Domain\Flag\Experiment;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagDefinition;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagKind;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagMetadata;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;
use Phil\FeatureFlagsBundle\Domain\Flag\Percentage;
use Phil\FeatureFlagsBundle\Domain\Flag\Rule\MemberOf;
use Phil\FeatureFlagsBundle\Domain\Flag\Rule\PercentageRollout;
use Phil\FeatureFlagsBundle\Domain\Flag\Rule\Rule;
use Phil\FeatureFlagsBundle\Domain\Flag\TargetingAttribute;
use Phil\FeatureFlagsBundle\Domain\Flag\Variant;
use Phil\FeatureFlagsBundle\Domain\Flag\VariantName;

/**
 * One shape for a flag, read from the application configuration, from a database row and from an admin form,
 * and written back to the last two:
 *
 * ```yaml
 * active: true
 * rules:
 *     - { attribute: tenant, members: [tenant-a, tenant-b] }
 *     - { attribute: tenant, percentage: 20 }
 * experiment:
 *     attribute: tenant
 *     variants: { control: 50, treatment: 50 }
 * description: The one-page checkout.
 * owner: checkout-team
 * kind: experiment
 * expires_on: '2027-03-31'
 * ```
 *
 * A flag that forces a circuit open takes no rule and no experiment: a circuit is forced open for every request
 * or for none.
 */
final class FlagDefinitionFormat
{
    private const string DATE = 'Y-m-d';

    private function __construct()
    {
    }

    /**
     * @throws InvalidValue
     */
    public static function read(FlagName $name, mixed $flag): FlagDefinition
    {
        if (!\is_array($flag)) {
            throw InvalidValue::because('A flag is declared by a map with "active" and "rules".');
        }

        $active = $flag['active'] ?? false;

        if (!\is_bool($active)) {
            throw InvalidValue::because('"active" is true or false.');
        }

        $rules = $flag['rules'] ?? [];

        if (!\is_array($rules)) {
            throw InvalidValue::because('"rules" is a list of rules.');
        }

        $rules = array_values(array_map(self::rule(...), $rules));
        $experiment = self::experiment($flag['experiment'] ?? null);

        if (([] !== $rules || $experiment instanceof Experiment) && str_starts_with($name->value, FlagCircuitOverride::FORCE_OPEN_FLAG_PREFIX)) {
            throw InvalidValue::because('A flag that forces a circuit open takes no rule and no experiment: a circuit is forced open for every request or for none.');
        }

        return new FlagDefinition($name, $active, $rules, $experiment, self::metadata($flag));
    }

    /**
     * The definition in the shape {@see self::read()} reads, without the keys that hold nothing.
     *
     * @return array<string, mixed>
     */
    public static function write(FlagDefinition $definition): array
    {
        $flag = ['active' => $definition->active];

        if ([] !== $definition->rules) {
            $flag['rules'] = array_map(self::writeRule(...), $definition->rules);
        }

        if ($definition->experiment instanceof Experiment) {
            $variants = [];

            foreach ($definition->experiment->variants as $variant) {
                $variants[$variant->name->value] = $variant->weight;
            }

            $flag['experiment'] = ['attribute' => $definition->experiment->attribute->value, 'variants' => $variants];
        }

        $metadata = $definition->metadata;

        return $flag + array_filter([
            'description' => $metadata->description,
            'owner' => $metadata->owner,
            'kind' => $metadata->kind?->value,
            'expires_on' => $metadata->expiresOn?->format(self::DATE),
        ], static fn (?string $value): bool => null !== $value);
    }

    /**
     * @return array<string, mixed>
     */
    private static function writeRule(Rule $rule): array
    {
        return match (true) {
            $rule instanceof MemberOf => ['attribute' => $rule->attribute->value, 'members' => $rule->members()],
            $rule instanceof PercentageRollout => ['attribute' => $rule->attribute->value, 'percentage' => $rule->percentage->value],
            default => throw InvalidValue::because(\sprintf('A rule of type %s has no written shape.', $rule::class)),
        };
    }

    private static function rule(mixed $rule): Rule
    {
        if (!\is_array($rule)) {
            throw InvalidValue::because('A rule is a map with "attribute" and either "members" or "percentage".');
        }

        $attribute = self::attribute($rule, 'A rule');
        $members = $rule['members'] ?? [];
        $percentage = $rule['percentage'] ?? null;

        if (!\is_array($members)) {
            throw InvalidValue::because('"members" is a list of identifiers.');
        }

        if ([] !== $members && null !== $percentage) {
            throw InvalidValue::because('A rule has either "members" or "percentage", not both.');
        }

        if (null !== $percentage) {
            if (!\is_int($percentage)) {
                throw InvalidValue::because('"percentage" is a whole number.');
            }

            return new PercentageRollout($attribute, Percentage::of($percentage));
        }

        if ([] === $members) {
            throw InvalidValue::because('A rule needs either "members" or "percentage".');
        }

        $identifiers = [];

        foreach ($members as $member) {
            if (!\is_string($member)) {
                throw InvalidValue::because('"members" is a list of identifiers.');
            }

            $identifiers[] = $member;
        }

        return MemberOf::of($attribute, $identifiers);
    }

    private static function experiment(mixed $experiment): ?Experiment
    {
        if (null === $experiment) {
            return null;
        }

        if (!\is_array($experiment)) {
            throw InvalidValue::because('"experiment" is a map with "attribute" and "variants".');
        }

        $weights = $experiment['variants'] ?? null;

        if (!\is_array($weights)) {
            throw InvalidValue::because('"variants" maps each variant to its weight.');
        }

        $variants = [];

        foreach ($weights as $name => $weight) {
            if (!\is_int($weight)) {
                throw InvalidValue::because(\sprintf('The weight of the variant "%s" is a whole number.', $name));
            }

            $variants[] = Variant::of(VariantName::of((string) $name), $weight);
        }

        return Experiment::of(self::attribute($experiment, 'An experiment'), $variants);
    }

    /**
     * @param array<array-key, mixed> $map
     */
    private static function attribute(array $map, string $what): TargetingAttribute
    {
        $attribute = \is_string($map['attribute'] ?? null) ? TargetingAttribute::tryFrom($map['attribute']) : null;

        return $attribute ?? throw InvalidValue::because(\sprintf('%s targets one of the attributes %s.', $what, implode(', ', TargetingAttribute::values())));
    }

    /**
     * @param array<array-key, mixed> $flag
     */
    private static function metadata(array $flag): FlagMetadata
    {
        $kind = self::optionalString($flag, 'kind');
        $expiresOn = self::optionalString($flag, 'expires_on');

        return FlagMetadata::of(
            self::optionalString($flag, 'description'),
            self::optionalString($flag, 'owner'),
            null === $kind ? null : (FlagKind::tryFrom($kind) ?? throw InvalidValue::because(\sprintf('"kind" is one of %s.', implode(', ', FlagKind::values())))),
            null === $expiresOn ? null : self::date($expiresOn),
        );
    }

    private static function date(string $value): DateTimeImmutable
    {
        // The time of day is dropped by `FlagMetadata`, which keeps the date only.
        $date = DateTimeImmutable::createFromFormat(self::DATE, $value, new DateTimeZone('UTC'));

        if (false === $date || $date->format(self::DATE) !== $value) {
            throw InvalidValue::because(\sprintf('"expires_on" is a date written YYYY-MM-DD; got "%s".', $value));
        }

        return $date;
    }

    /**
     * @param array<array-key, mixed> $flag
     */
    private static function optionalString(array $flag, string $key): ?string
    {
        $value = $flag[$key] ?? null;

        if (null !== $value && !\is_string($value)) {
            throw InvalidValue::because(\sprintf('"%s" is a string.', $key));
        }

        return $value;
    }
}
