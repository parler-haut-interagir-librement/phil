<?php

declare(strict_types=1);

use Phil\CoursesBundle\Application\Scorm\ScormRuntime;
use Phil\CoursesBundle\Application\Training\Transcripts;
use Phil\CoursesBundle\Infrastructure\Doctrine\CourseTables;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalLearningPathRepository;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalScormAttemptRepository;

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'phpstan' => [
        // The fuzz target runs inside nikic/php-fuzzer, which gives it `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'infection' => [
        'mutators' => [
            // Twig and the CSRF tokens are installed together in the tests: the pages are wired with both, and the `||`
            // that skips them when either is missing reads as `&&` there.
            'LogicalOr' => ['ignoreSourceCodeByRegex' => ['.*!class_exists\\(Environment::class\\) \\|\\| !interface_exists\\(CsrfTokenManagerInterface::class\\).*']],
            // A doubled `/` names the same file.
            'UnwrapRtrim' => ['ignoreSourceCodeByRegex' => ['.*rtrim\\(\\$directory, \'/\'\\).*']],
            // The columns of a primary key are never null, whatever they are declared; `isset` is true for a key whose value
            // is `false`, and the walk and the holding blocks only need the keys.
            'TrueValue' => ['ignore' => [
                // The learner of an obligation is a column of its primary key, which the schema makes not null.
                CourseTables::class.'::tables::329',
            ], 'ignoreSourceCodeByRegex' => [
                '.*self::column\\(\'(activity_hash|state_id)\'.*',
                '.*\\$(seen|holding)\\[\\$block\\] = true;.*',
                // The activities with an ended attempt are read by their keys: the value only marks them.
                '.*\\$activities\\[\\$attempt->activity->value\\] = true;.*',
            ]],
            // The configuration refuses a service and a remote store together: the first one named is the only one.
            'Coalesce' => ['ignoreSourceCodeByRegex' => ['.*\\$config\\[\'cmi5\'\\]\\[\'lrs\'\\] \\?\\? self::remoteStore.*']],
            // The driver gives text for a text column: the cast is for the types only.
            'UnwrapArrayMap' => ['ignoreSourceCodeByRegex' => ['.*array_map\\(Rows::text\\(\\.\\.\\.\\), \\$this->connection->fetchFirstColumn\\(\'SELECT subject_id.*']],
            // The store never reads the body of an answer: the media types bound what the client buffers, not what it returns.
            'ArrayItemRemoval' => ['ignoreSourceCodeByRegex' => [
                '.*new ReadRule\\(\'application/json\', \\[\'application/json\'\\]\\).*',
                // The number of an attempt is bound as text and stored as an integer by the column.
                '.*.number. => Types::INTEGER,.*',
                // SQLite stores the boolean of a step the same way whether the column is typed or not: the type is for the other databases.
                '.*\\], \\[.required. => Types::BOOLEAN\\]\\);.*',
            ]],
            // An offset below zero reads as zero, the default included.
            'DecrementInteger' => ['ignoreSourceCodeByRegex' => [
                // A percentage and a position below zero read as zero: the bound only types them.
                '.*\\$whole = \\$enrollment->isCompleted\\(\\) \\? 100 : 0;.*',
                '.*max\\(0, min\\(100, \\$percent\\)\\).*',
                '.*max\\(0, Rows::int\\(\\$row\\[.position.\\]\\)\\).*',
                '.*public function all\\(int \\$limit = 50, int \\$offset = 0\\).*',
                // The pages are read with an offset of zero at least; SQLite reads a negative one as zero, MySQL and PostgreSQL refuse it.
                '.*max\\(1, \\$limit\\)\\.. OFFSET .\\.max\\(0, \\$offset\\).*',
                // A commit holds a body of three levels at most: a deeper limit only decides which of two refusals comes first.
                '.*json_decode\\(\\$json, true, 4, .*',
            ]],
            'IncrementInteger' => ['ignoreSourceCodeByRegex' => [
                // A percentage is held between 0 and 100 by the line that follows: a whole path reads 100 at most, and a
                // bound raised to 101 only lets through what 100 * completed / required never reaches.
                '.*\\$whole = \\$enrollment->isCompleted\\(\\) \\? 100 : 0;.*',
                '.*max\\(0, min\\(100, \\$percent\\)\\).*',
                '.*json_decode\\(\\$json, true, 4, .*',
                // Depths counted by two instead of one keep the order of the blocks: only their differences count.
                '.*\\(\\$depths\\[\\$parent\\] \\?\\? 0\\) \\+ 1.*',
            ]],
            // A score equal to the best kept is the same score.
            'GreaterThan' => ['ignoreSourceCodeByRegex' => [
                // Two equivalent completions at the same moment are the same moment.
                '.*\\$completedAt > \\$latest.*',
                '.*\\$outcome->score->scaled > \\$this->score->scaled.*',
                // A token is 103 characters at most whatever its length is checked at: its pattern is the bound.
                '.*strlen\\(\\$token\\) > self::MAX_LENGTH.*',
            ]],
            // The digits of a token's expiry, and of the length a header states, compare as the integers they cast to.
            'CastInt' => ['ignoreSourceCodeByRegex' => [
                '.*\\(int\\) \\$match\\[3\\] < .*',
                '.*\\(int\\) \\$length > self::MAX_BYTES.*',
            ]],
            // `finish` is a boolean whichever default stands in for its absence: both are checked as booleans.
            'FalseValue' => ['ignoreSourceCodeByRegex' => ['.*is_bool\\(\\$body\\[.finish.\\] \\?\\? false\\).*']],
            // No enrollment asks for no row, and the query of an empty list reads none either.
            'ReturnRemoval' => ['ignore' => [
                DbalScormAttemptRepository::class.'::ofEnrollments::94',
                // A target no activity has leaves for the outline, as the line after the branch does.
                ScormRuntime::class.'::navigation::316',
                // A transcript holds none of no course, and the query of an empty list reads none either.
                Transcripts::class.'::holdsAny::91',
                DbalLearningPathRepository::class.'::openEnrollmentsWith::144',
            ]],
            'UnwrapArrayValues' => ['ignoreSourceCodeByRegex' => [
                // The keys of the courses kept are never read: they are counted, searched and saved as a list.
                '.*\\$others = array_values\\(array_filter\\(\\$this->groups->courses.*',
                '.*array_values\\(array_filter\\(\\$step->prerequisites, static fn.*',
                // The modules are appended, or replaced at their place: their keys follow each other already. The list is
                // kept for the types.
                '.*array_values\\(\\$modules\\).*',
                // The driver expands a list parameter by its values, whatever the keys of the list: the names a variadic call may give.
                '.*return array_values\\(array_map\\(static fn \\(EnrollmentId \\$id\\): string => \\$id->value, \\$enrollments\\)\\);.*',
            ]],
        ],
    ],
    'fuzz' => [
        // The checks of the cmi5 and xAPI statements and the body of a SCORM commit on bytes nobody wrote, from the seeds of
        // tests/Fuzz/corpus/<target>.
        'targets' => [
            'cmi5-statement' => 'tests/Fuzz/cmi5-statement.php',
            'scorm-commit' => 'tests/Fuzz/scorm-commit.php',
            'xapi-statement' => 'tests/Fuzz/xapi-statement.php',
        ],
    ],
    'docs' => [
        'public' => true,
        // The terms of SCORM, IMS Content Packaging, cmi5 and xAPI.
        'words' => ['cmi5', 'SCORM', 'SCO', 'SCOs', 'xAPI', 'imsmanifest', 'moveOn', 'masteryScore', 'launchMethod', 'LRS', 'HMAC', 'elearning', 'cmi', 'LMSCommit', 'LMSFinish', 'LMSInitialize', 'LMS', 'ADL', 'HHHH', 'sendBeacon', 'postMessage', 'AssetMapper', 'Webpack', 'MEDIUMTEXT', 'utf8mb4', 'MariaDB'],
    ],
];
