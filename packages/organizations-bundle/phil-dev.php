<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true, 'translations' => 'blocking'],
    'infection' => [
        'mutators' => [
            // The key of the innermost context: with no context the stack is empty, so no key is looked up at all and
            // `-1`, `-2` or `0` find nothing alike; with one, `array_key_last` gives the key.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*array_key_last\\(\\$this->stack\\) \\?\\? -1.*',
                    // DBAL binds the parameters of a query builder in the order they were given, whatever the key.
                    '.*->setParameter\\(0, \\$id->value\\);.*',
                ],
            ],
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*array_key_last\\(\\$this->stack\\) \\?\\? -1.*',
                    '.*->setParameter\\(0, \\$id->value\\);.*',
                ],
            ],
            // A catalogue is read through permissionsOf() and allPermissions(), which each deduplicate and sort their
            // result again: the order and the repeats of what is kept per role never show.
            'UnwrapArrayValues' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$admin = array_values\\(array_diff\\(Permission::BUILT_IN.*',
                    '.*\\$all = array_values\\(array_unique\\(array_merge.*',
                    '.*\\$unique = array_values\\(array_unique\\(\\$list\\)\\);.*',
                    // The usort on the next line renumbers the list.
                    '.*\\$found = array_values\\(\\$this->teamMemberships\\[\\$teamId->value\\] \\?\\? \\[\\]\\);.*',
                    // The usort of `sortedUnits()` renumbers the list.
                    '.*\\$found = array_values\\(array_filter\\(\\$this->units, \\$wanted\\)\\);.*',
                    // A role is read by iterating the list, whatever its keys: `Roles::of()` keys them by name.
                    '.*\\$roles = array_values\\(array_filter\\(explode\\(.*',
                ],
            ],
            'UnwrapArrayUnique' => [
                'ignoreSourceCodeByRegex' => ['.*\\$unique = array_values\\(array_unique\\(\\$list\\)\\);.*'],
            ],
            'Foreach_' => [
                'ignoreSourceCodeByRegex' => ['.*foreach \\(\\$permissions as \\$role => \\$list\\) \\{.*'],
            ],
            'FunctionCallRemoval' => [
                'ignoreSourceCodeByRegex' => ['.*sort\\(\\$unique, \\\\SORT_STRING\\);.*'],
            ],
            // `trim()` has already taken the line break the `D` flag would refuse at the end of the name.
            'PregMatchRemoveFlags' => [
                'ignoreSourceCodeByRegex' => ['.*preg_match\\(\'/\\^\\[\\^\\\\p\\{Cc\\}\\]\\+\\$/Du\', \\$name\\).*'],
            ],
            // A driver counts the rows it deleted as an integer or as the text of one, and `'1' > 0` holds as `1 > 0` does.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => ['.*return \\(int\\) \\$this->connection->delete\\(.*'],
            ],
            // `sprintf('%s')` writes an integer, a float or a boolean as the cast would.
            'CastString' => [
                'ignoreSourceCodeByRegex' => ['.*\\\\is_scalar\\(\\$name\\) \\? \\(string\\) \\$name : get_debug_type.*'],
            ],
            // The lock of the in-memory store is never taken: it has one writer.
            'FalseValue' => [
                'ignoreSourceCodeByRegex' => ['.*public function organization\\(OrganizationId \\$id, bool \\$lock = false\\): \\?Organization.*'],
            ],
            // The name of a unit is read inside the `array_map` over the unit memberships: with no unit store the list is
            // empty and the callback never runs.
            'NullSafeMethodCall' => [
                'ignoreSourceCodeByRegex' => ['.*\'name\' => \\$this->units\\?->unit\\(\\$membership->unitId\\)\\?->name->value,.*'],
            ],
            // `true && null` is `false`: the cast only tells PHPStan so.
            'CastBool' => [
                'ignoreSourceCodeByRegex' => ['.*\\$organization->isActive\\(\\) && \\(bool\\) \\$organization->emailDomain\\?->equals.*'],
            ],
            // The stored memberships of an organization, of a team and of a unit are replaced, never changed in place, so
            // the objects a snapshot holds cannot differ from copies of them.
            'UnwrapArrayMap' => [
                'ignoreSourceCodeByRegex' => ['.*array_map\\(\\$copy, \\$this->(memberships|teamMemberships|unitMemberships)\\),.*'],
            ],
            // Whether the access bundle is installed: the development environment has both of its classes, so only
            // the flag the tests pass decides.
            'LogicalAnd' => [
                'ignoreSourceCodeByRegex' => ['.*\\$installed = interface_exists\\(ModelContributor::class\\) && class_exists\\(RelationWriter::class\\);.*'],
            ],
        ],
    ],
];
