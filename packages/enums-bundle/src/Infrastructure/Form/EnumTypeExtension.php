<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Infrastructure\Form;

use Closure;
use Override;
use Phil\Enums\Collection\CaseCollection;
use Phil\Enums\Meta\AbstractMetaProperty;
use Phil\Enums\Meta\Reflection;
use Phil\EnumsBundle\Application\Selection\CaseSelection;
use Phil\EnumsBundle\Application\Translation\EnumTranslation;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatableInterface;
use UnitEnum;

/**
 * Narrows the choices of Symfony's `EnumType` to a subset of the cases, and labels them by their metadata:
 *
 * ```php
 * $builder->add('status', EnumType::class, [
 *     'class' => OrderStatus::class,
 *     'group' => 'customer',                     // the cases in the group `customer`
 *     'except' => [OrderStatus::CANCELLED],
 * ]);
 * ```
 *
 * - **`group`** (a name or a list), **`only`**, **`except`** and **`where_meta`** (meta property class to value)
 *   select the cases as `CaseSelection` does; the choices keep the enum's declaration order. `choices` also
 *   accepts a `CaseCollection`, as any iterable.
 * - **`without_deprecated`** leaves out the cases marked `#[\Deprecated]`; keep it off on a form that edits a
 *   record which may hold one.
 * - **`group_by_meta`** (a meta property class) puts the choices in `<optgroup>`s by the first value of that
 *   property — `Group::class` for the enum's own groups —; a case without it stays outside every group.
 * - **`preferred_group`** (a name or a list) puts the cases of these groups first, as `preferred_choices`.
 * - **The label of a choice** is the case itself when the enum is translatable — Symfony translates it —,
 *   otherwise its `Label`, otherwise its name.
 *
 * Validate the submission with the `EnumCase` constraint and the same selection, so that a case the form did not
 * offer is refused on the server too.
 */
final class EnumTypeExtension extends AbstractTypeExtension
{
    #[Override]
    public static function getExtendedTypes(): iterable
    {
        return [EnumType::class];
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->define('group')->default([])->allowedTypes('string', 'string[]')
            ->info('The group, or groups, whose cases the choices are.');
        $resolver
            ->define('only')->default([])->allowedTypes(UnitEnum::class.'[]')
            ->info('The cases the choices are limited to.');
        $resolver
            ->define('except')->default([])->allowedTypes(UnitEnum::class.'[]')
            ->info('The cases left out of the choices.');
        $resolver
            ->define('where_meta')->default([])->allowedTypes('array')
            ->info('Meta property class to value: the cases whose meta properties have these values.');

        $resolver
            ->define('without_deprecated')->default(false)->allowedTypes('bool')
            ->info('Whether the cases marked #[\\Deprecated] are left out of the choices.');
        $resolver
            ->define('group_by_meta')->default(null)->allowedTypes('null', 'string')
            ->info('The meta property whose first value groups the choices.');
        $resolver
            ->define('preferred_group')->default([])->allowedTypes('string', 'string[]')
            ->info('The group, or groups, whose cases are offered first.');

        $resolver->addNormalizer('choices', static function (Options $options, mixed $choices): mixed {
            $selection = self::selection($options);

            if (true !== $options['without_deprecated']) {
                return $selection->narrows() ? $selection->cases()->all() : $choices;
            }

            $cases = $selection->narrows() ? $selection->cases() : CaseCollection::of(self::cases($choices));

            return $cases->withoutDeprecated()->all();
        });

        $resolver->addNormalizer('group_by', static function (Options $options, mixed $groupBy): mixed {
            $metaProperty = $options['group_by_meta'];

            if (!\is_string($metaProperty)) {
                return $groupBy;
            }

            /** @var class-string<AbstractMetaProperty<*>> $metaProperty */
            return static fn (UnitEnum $case): ?string => self::firstValue($metaProperty, $case);
        });

        $resolver->addNormalizer('preferred_choices', static function (Options $options, mixed $preferred): mixed {
            /** @var list<string> $groups */
            $groups = (array) $options['preferred_group'];

            /** @var class-string<UnitEnum> $enum */
            $enum = $options['class'];

            return [] === $groups ? $preferred : CaseCollection::of($enum::cases())->inGroup(...$groups)->all();
        });

        $resolver->setDefault('choice_label', static fn (Options $options): Closure => self::label(...));
    }

    /**
     * The label of a choice: the key when the choices are keyed by label, else the case when it is translatable,
     * else its `Label`, else its name.
     */
    public static function label(UnitEnum $case, int|string $key): string|TranslatableInterface
    {
        if (\is_string($key)) {
            return $key;
        }

        if ($case instanceof TranslatableInterface) {
            return $case;
        }

        return EnumTranslation::plainLabel($case);
    }

    /**
     * The first value of a meta property on the case, as a group name; `null` puts the case outside every group.
     *
     * @param class-string<AbstractMetaProperty<*>> $metaProperty
     */
    private static function firstValue(string $metaProperty, UnitEnum $case): ?string
    {
        $value = Reflection::metaValueList($metaProperty, $case)[0] ?? null;

        return \is_int($value) || \is_string($value) ? (string) $value : null;
    }

    /**
     * The cases among the choices; anything else is left out.
     *
     * @return list<UnitEnum>
     */
    private static function cases(mixed $choices): array
    {
        $cases = [];

        foreach (is_iterable($choices) ? $choices : [] as $choice) {
            if ($choice instanceof UnitEnum) {
                $cases[] = $choice;
            }
        }

        return $cases;
    }

    /**
     * @param Options<array<string, mixed>> $options
     */
    private static function selection(Options $options): CaseSelection
    {
        return CaseSelection::fromOptions($options['class'], $options['only'], $options['except'], $options['group'], $options['where_meta']);
    }
}
