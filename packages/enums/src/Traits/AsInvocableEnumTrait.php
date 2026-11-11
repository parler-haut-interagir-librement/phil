<?php

declare(strict_types=1);

namespace Phil\Enums\Traits;

use Phil\Enums\Exceptions\UndefinedCaseError;
use Phil\Enums\Internal\Cases;
use UnitEnum;

/**
 * A case as its primitive — its value when the enum is backed, its name when it is pure — without `->value`: call
 * the case (`$status()`), or call the enum by the case's name (`Status::PAID()`).
 */
trait AsInvocableEnumTrait
{
    /**
     * The primitive of this case.
     */
    public function __invoke(): string|int
    {
        return Cases::primitive($this);
    }

    /**
     * The primitive of the case named like the method called.
     *
     * @param array<int, mixed> $args ignored: a case takes no argument
     *
     * @throws UndefinedCaseError when no case has that name
     */
    public static function __callStatic(string $name, array $args): string|int
    {
        $case = array_find(self::cases(), static fn (UnitEnum $candidate): bool => $candidate->name === $name);

        return null === $case ? throw new UndefinedCaseError(self::class, $name) : Cases::primitive($case);
    }
}
