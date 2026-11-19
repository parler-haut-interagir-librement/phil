<?php

declare(strict_types=1);

use Phil\VirtualClassroomBundle\Application\Classrooms;
use Phil\VirtualClassroomBundle\Infrastructure\Dbal\DbalClassroomStore;
use Phil\VirtualClassroomBundle\Infrastructure\Memory\InMemoryClassroomStore;

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests', 'config'],
    'docs' => ['public' => true, 'translations' => 'blocking'],
    'infection' => [
        'mutators' => [
            // The same moment, taken twice, is the same first arrival.
            'LessThan' => [
                'ignoreSourceCodeByRegex' => ['.*if \\(null === \$first \|\| \$interval->joinedAt < \$first\) \\{.*'],
            ],
            // A participant has one stay open at most — an arrival inside an open stay begins none —, so the loop
            // that looks for one never meets a second.
            'Break_' => ['ignore' => [InMemoryClassroomStore::class.'::present']],
            // The rows come as `int|string` from a driver that stringifies: the casts only tell PHPStan so, and the
            // parameters they feed are typed as integers or compared as numbers.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => [
                    '.*return 1 === \\(int\\) \$this->connection->executeStatement\\(.*',
                    '.*\\[\$at->getTimestamp\\(\\), \$room->value, \$participant->value, \\(int\\) \$joined\\],.*',
                ],
            ],
            // A room the server no longer knows ends, and ending closes every stay: what follows the early return
            // finds no participant present and departs none. A recording already known is updated and answers
            // false; the insert that would follow breaks the primary key and answers false too.
            'ReturnRemoval' => ['ignore' => [Classrooms::class.'::reconcile', DbalClassroomStore::class.'::saveRecording']],
            // The columns the query names are never missing from its rows: the default only tells PHPStan so.
            'FalseValue' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\(bool\\) \\(\$row\[.published.\] \?\? false\\),.*',
                    '.*\\(bool\\) \\(\$row\[.record.\] \?\? false\\),.*',
                ],
            ],
            // The first row of the ordered rows is the one read, with a limit of one or two.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => ['.*ORDER BY created_at DESC, id DESC., 1\\),.*'],
            ],
            // The names of the enabled bundles are keys of a map, never numbers: the filter only tells PHPStan so, and
            // `in_array` reads the list with or without gaps.
            'UnwrapArrayFilter' => [
                'ignoreSourceCodeByRegex' => ['.*array_values\\(array_filter\\(array_keys\\(\$bundles\\), is_string\\(\\.\\.\\.\\)\\)\\).*'],
            ],
            'UnwrapArrayValues' => [
                'ignoreSourceCodeByRegex' => ['.*array_values\\(array_filter\\(array_keys\\(\$bundles\\), is_string\\(\\.\\.\\.\\)\\)\\).*'],
            ],
            // The type of the second parameter, the room, is the string PDO binds anyway.
            'ArrayItemRemoval' => [
                'ignoreSourceCodeByRegex' => ['.*\\[ParameterType::INTEGER, ParameterType::STRING\\],.*'],
            ],
        ],
    ],
];
