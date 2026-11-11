<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Infrastructure\Validator;

use Attribute;
use InvalidArgumentException;
use Phil\Enums\Meta\AbstractMetaProperty;
use Phil\EnumsBundle\Application\Selection\CaseSelection;
use Symfony\Component\Validator\Constraint;
use UnitEnum;

/**
 * The value is an `EnumSet` of `enum` whose every case is of the selection — among `only`, not in `except`, in one
 * of the groups `inGroup` names, with the meta property values `whereMeta` gives —, holding between `min` and `max`
 * cases. `null` is valid, as for every constraint but `NotNull`.
 *
 * ```php
 * #[EnumSetOf(Permission::class, inGroup: 'basic', min: 1, max: 2)]
 * public EnumSet $permissions;
 * ```
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class EnumSetOf extends Constraint
{
    public const string NOT_A_SET_ERROR = '0f3a7c52-9d1e-4b86-a2c4-5e8f1b7d3a60';

    public const string NOT_ALLOWED_ERROR = '7b2e9d14-6a0c-4f3b-8e51-d9c4a6f2b083';

    public const string TOO_FEW_ERROR = 'c5d8a1e7-3b9f-4c02-96ad-1f7e4b2c8d95';

    public const string TOO_MANY_ERROR = 'e9a4c3b6-1d7f-4a85-b0e2-6c3d9f5a1b27';

    protected const array ERROR_NAMES = [
        self::NOT_A_SET_ERROR => 'NOT_A_SET_ERROR',
        self::NOT_ALLOWED_ERROR => 'NOT_ALLOWED_ERROR',
        self::TOO_FEW_ERROR => 'TOO_FEW_ERROR',
        self::TOO_MANY_ERROR => 'TOO_MANY_ERROR',
    ];

    public string $message = 'This value is not a set of allowed choices.';

    public string $notAllowedMessage = 'The value {{ value }} is not an allowed choice.';

    public string $minMessage = 'You must select at least {{ limit }} choice.|You must select at least {{ limit }} choices.';

    public string $maxMessage = 'You must select at most {{ limit }} choice.|You must select at most {{ limit }} choices.';

    public readonly CaseSelection $selection;

    /**
     * @param class-string<UnitEnum> $enum
     * @param list<UnitEnum>         $only
     * @param list<UnitEnum>         $except
     * @param string|list<string>    $inGroup
     * @param array<class-string<AbstractMetaProperty<*>>, mixed> $whereMeta
     * @param list<string>|null $groups the validation groups
     *
     * @throws InvalidArgumentException when a bound is negative or `min` is above `max`
     */
    public function __construct(
        string $enum,
        array $only = [],
        array $except = [],
        string|array $inGroup = [],
        array $whereMeta = [],
        public readonly ?int $min = null,
        public readonly ?int $max = null,
        ?string $message = null,
        ?string $notAllowedMessage = null,
        ?string $minMessage = null,
        ?string $maxMessage = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(null, $groups, $payload);

        if ($min < 0 || $max < 0 || (null !== $min && null !== $max && $min > $max)) {
            throw new InvalidArgumentException(\sprintf('The bounds of a set are 0 or more, the minimum at most the maximum; %s and %s given.', $min ?? 'none', $max ?? 'none'));
        }

        $this->selection = new CaseSelection($enum, $only, $except, \is_string($inGroup) ? [$inGroup] : $inGroup, $whereMeta);
        $this->message = $message ?? $this->message;
        $this->notAllowedMessage = $notAllowedMessage ?? $this->notAllowedMessage;
        $this->minMessage = $minMessage ?? $this->minMessage;
        $this->maxMessage = $maxMessage ?? $this->maxMessage;
    }
}
