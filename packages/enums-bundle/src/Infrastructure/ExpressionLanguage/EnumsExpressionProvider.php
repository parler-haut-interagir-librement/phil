<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Infrastructure\ExpressionLanguage;

use BackedEnum;
use Closure;
use Override;
use Phil\Enums\Collection\CaseCollection;
use Phil\Enums\Meta\AbstractMetaProperty;
use Phil\Enums\Meta\Reflection;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;
use UnitEnum;

/**
 * Functions for the expressions of security, routing and validation, beside the language's own `enum()`, which
 * reads one case by its constant:
 *
 * - `enum_meta(case, 'App\\Color')`: the value of a meta property on a case;
 * - `enum_in_group(case, 'staff')`: whether the case is in one of the groups named;
 * - `enum_from(value, 'App\\OrderStatus')`: the case of a value, a name for a pure enum, or `null`.
 *
 * ```yaml
 * condition: "enum_in_group(enum_from(params['status'], 'App\\\\OrderStatus'), 'public')"
 * ```
 */
final class EnumsExpressionProvider implements ExpressionFunctionProviderInterface
{
    #[Override]
    public function getFunctions(): array
    {
        return [
            $this->function('enum_meta', 'meta', self::meta(...)),
            $this->function('enum_in_group', 'inGroup', self::inGroup(...)),
            $this->function('enum_from', 'from', self::from(...)),
        ];
    }

    /**
     * The expression function `$name`, compiled to a call of the static method `$method` and evaluated by it.
     */
    private function function(string $name, string $method, Closure $evaluate): ExpressionFunction
    {
        return new ExpressionFunction(
            $name,
            static fn (string ...$arguments): string => \sprintf('\\%s::%s(%s)', self::class, $method, implode(', ', $arguments)),
            static fn (array $values, mixed ...$arguments): mixed => $evaluate(...$arguments),
        );
    }

    /**
     * @param class-string<AbstractMetaProperty<*>> $metaProperty
     */
    public static function meta(UnitEnum $case, string $metaProperty): mixed
    {
        return Reflection::metaValue($metaProperty, $case);
    }

    public static function inGroup(?UnitEnum $case, string ...$groups): bool
    {
        return $case instanceof UnitEnum && !CaseCollection::of([$case])->inGroup(...$groups)->isEmpty();
    }

    /**
     * @param class-string<UnitEnum> $enum
     */
    public static function from(mixed $value, string $enum): ?UnitEnum
    {
        return array_find($enum::cases(), static fn (UnitEnum $case): bool => ($case instanceof BackedEnum ? $case->value : $case->name) === $value);
    }
}
