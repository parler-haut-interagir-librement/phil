<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Domain\Flag;

/**
 * An enumerable provider that can also list the flags it holds but cannot read, such as a database table whose
 * rows can be edited by hand. Such a flag is not absent: {@see FlagProvider::find()} throws `InvalidValue` for it
 * (the evaluation is `PARSE_ERROR`), {@see EnumerableFlagProvider::all()} leaves it out, and `unreadable()` names
 * it with its reason, so a listing shows it apart.
 */
interface DiagnosableFlagProvider extends EnumerableFlagProvider
{
    /**
     * @return list<UnreadableFlag> by name
     */
    public function unreadable(): array;
}
