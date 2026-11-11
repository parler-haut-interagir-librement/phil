<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Application\Translation;

use Phil\Enums\Attribute\Description;
use Phil\Enums\Attribute\Label;
use Phil\Enums\Meta\Reflection;
use ReflectionEnum;
use UnitEnum;

/**
 * The translation keys and domain of a case.
 *
 * - **The label key is the case's `Label`**, when it carries one: the source text is the key, so a missing
 *   translation shows the label as written. Without a label, the key is generated: `<prefix>.<enum>.<case>`, the
 *   short class name and the case name in snake case — `enum.order_status.in_transit`.
 * - **The description key is the case's `Description`**; a case without one has no description to translate.
 * - **The domain** is the `Translatable` attribute's, `enums` by default.
 *
 * The `Translatable` attribute of an enum is read once per process and kept, as `phil/enums` keeps its metadata, and so
 * is the generated key of each case: a label is looked up on every translation, every Twig filter and every
 * normalized case.
 */
final class EnumTranslation
{
    /** @var array<class-string<UnitEnum>, Translatable> the settings of each enum read so far, by class */
    private static array $settings = [];

    /** @var array<class-string<UnitEnum>, array<string, string>> the generated label key of each case built so far, by class then case */
    private static array $generatedKeys = [];

    private function __construct()
    {
    }

    public static function labelKey(UnitEnum $case): string
    {
        $label = Reflection::metaValue(Label::class, $case);

        return \is_string($label) && '' !== $label ? $label : self::generatedKey($case);
    }

    /**
     * The case's `Label`, or its name when it has none: what is shown where nothing translates.
     */
    public static function plainLabel(UnitEnum $case): string
    {
        $label = Reflection::metaValue(Label::class, $case);

        return \is_string($label) && '' !== $label ? $label : $case->name;
    }

    public static function descriptionKey(UnitEnum $case): ?string
    {
        $description = Reflection::metaValue(Description::class, $case);

        return \is_string($description) && '' !== $description ? $description : null;
    }

    public static function domain(UnitEnum $case): string
    {
        return self::settings($case)->domain;
    }

    private static function generatedKey(UnitEnum $case): string
    {
        if (!isset(self::$generatedKeys[$case::class][$case->name])) {
            $parts = explode('\\', $case::class);
            self::$generatedKeys[$case::class][$case->name] = self::settings($case)->prefix.'.'.self::snake(end($parts)).'.'.self::snake($case->name);
        }

        return self::$generatedKeys[$case::class][$case->name];
    }

    private static function settings(UnitEnum $case): Translatable
    {
        if (!isset(self::$settings[$case::class])) {
            $attributes = new ReflectionEnum($case)->getAttributes(Translatable::class);
            self::$settings[$case::class] = [] === $attributes ? new Translatable() : $attributes[0]->newInstance();
        }

        return self::$settings[$case::class];
    }

    /**
     * `OrderStatus` and `ORDER_STATUS` both become `order_status`.
     */
    private static function snake(string $name): string
    {
        return mb_strtolower((string) preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', '_', $name));
    }
}
