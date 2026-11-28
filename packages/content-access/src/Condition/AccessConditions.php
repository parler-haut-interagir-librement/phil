<?php

declare(strict_types=1);

namespace Phil\ContentAccess\Condition;

use Phil\Rules\Type\ConditionTypes;

/**
 * The condition types of access, registered beside the generic comparisons of `phil/rules` or beside a registry the
 * application already built.
 */
final class AccessConditions
{
    private function __construct()
    {
    }

    /**
     * `access.member`, `access.role`, `access.feature` and `access.institution`, added to the given registry, or to the
     * built-in comparisons of `phil/rules` when none is given.
     */
    public static function types(?ConditionTypes $base = null): ConditionTypes
    {
        return ($base ?? ConditionTypes::builtIn())->with(
            new MemberCondition(),
            new RoleCondition(),
            new FeatureCondition(),
            new InstitutionCondition(),
        );
    }
}
