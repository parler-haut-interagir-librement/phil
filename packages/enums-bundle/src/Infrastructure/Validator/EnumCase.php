<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Infrastructure\Validator;

use Attribute;
use Phil\Enums\Meta\AbstractMetaProperty;
use Phil\EnumsBundle\Application\Selection\CaseSelection;
use Symfony\Component\Validator\Constraint;
use UnitEnum;

/**
 * The value is a case of the selection: of `enum`, among `only`, not in `except`, in one of the groups `inGroup`
 * names, with the meta property values `whereMeta` gives. A backed value or, for a pure enum, a case name is
 * read as its case first; `null` is valid, as for every constraint but `NotNull`.
 *
 * ```php
 * #[EnumCase(OrderStatus::class, inGroup: 'customer', except: [OrderStatus::CANCELLED])]
 * public ?OrderStatus $status = null;
 * ```
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class EnumCase extends Constraint
{
    public const string NOT_A_CASE_ERROR = '6d1c9a8e-2f0b-4f6c-9a77-3c2e5b1d8f40';

    public const string NOT_ALLOWED_ERROR = 'b4e0f2a1-7c3d-4e8b-9f16-0a5d2c7e9b31';

    protected const array ERROR_NAMES = [
        self::NOT_A_CASE_ERROR => 'NOT_A_CASE_ERROR',
        self::NOT_ALLOWED_ERROR => 'NOT_ALLOWED_ERROR',
    ];

    public string $message = 'The value {{ value }} is not an allowed choice.';

    public readonly CaseSelection $selection;

    /**
     * @param class-string<UnitEnum> $enum
     * @param list<UnitEnum>         $only
     * @param list<UnitEnum>         $except
     * @param string|list<string>    $inGroup
     * @param array<class-string<AbstractMetaProperty<*>>, mixed> $whereMeta
     * @param list<string>|null $groups the validation groups
     */
    public function __construct(
        string $enum,
        array $only = [],
        array $except = [],
        string|array $inGroup = [],
        array $whereMeta = [],
        ?string $message = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(null, $groups, $payload);

        $this->selection = new CaseSelection($enum, $only, $except, \is_string($inGroup) ? [$inGroup] : $inGroup, $whereMeta);
        $this->message = $message ?? $this->message;
    }
}
