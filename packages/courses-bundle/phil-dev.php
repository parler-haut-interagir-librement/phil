<?php

declare(strict_types=1);

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
            'TrueValue' => ['ignoreSourceCodeByRegex' => [
                '.*self::column\\(\'(activity_hash|state_id)\'.*',
                '.*\\$(seen|holding)\\[\\$block\\] = true;.*',
            ]],
            // Depths counted by two instead of one keep the order of the blocks: only their differences count.
            'IncrementInteger' => ['ignoreSourceCodeByRegex' => ['.*\\(\\$depths\\[\\$parent\\] \\?\\? 0\\) \\+ 1.*']],
            // The configuration refuses a service and a remote store together: the first one named is the only one.
            'Coalesce' => ['ignoreSourceCodeByRegex' => ['.*\\$config\\[\'cmi5\'\\]\\[\'lrs\'\\] \\?\\? self::remoteStore.*']],
            // The driver gives text for a text column: the cast is for the types only.
            'UnwrapArrayMap' => ['ignoreSourceCodeByRegex' => ['.*array_map\\(Rows::text\\(\\.\\.\\.\\), \\$this->connection->fetchFirstColumn\\(\'SELECT subject_id.*']],
            // The store never reads the body of an answer: the media types bound what the client buffers, not what it returns.
            'ArrayItemRemoval' => ['ignoreSourceCodeByRegex' => [
                '.*new ReadRule\\(\'application/json\', \\[\'application/json\'\\]\\).*',
                // The number of an attempt is bound as text and stored as an integer by the column.
                '.*.number. => Types::INTEGER,.*',
            ]],
            // An offset below zero reads as zero, the default included.
            'DecrementInteger' => ['ignoreSourceCodeByRegex' => [
                '.*public function all\\(int \\$limit = 50, int \\$offset = 0\\).*',
                // A commit holds a body of three levels at most: a deeper limit only decides which of two refusals comes first.
                '.*json_decode\\(\\$json, true, 4, .*',
            ]],
            'IncrementInteger' => ['ignoreSourceCodeByRegex' => ['.*json_decode\\(\\$json, true, 4, .*']],
            // A score equal to the best kept is the same score.
            'GreaterThan' => ['ignoreSourceCodeByRegex' => [
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
                'Phil\\CoursesBundle\\Infrastructure\\Doctrine\\DbalScormAttemptRepository::ofEnrollments::90',
                // A target no activity has leaves for the outline, as the line after the branch does.
                'Phil\\CoursesBundle\\Application\\Scorm\\ScormRuntime::navigation::192',
            ]],
            // The modules are appended, or replaced at their place: their keys follow each other already. The list is
            // kept for the types.
            'UnwrapArrayValues' => ['ignoreSourceCodeByRegex' => ['.*array_values\\(\\$modules\\).*']],
        ],
    ],
    'fuzz' => [
        // The checks of the cmi5 statements and the body of a SCORM commit on bytes nobody wrote, from the seeds of
        // tests/Fuzz/corpus/<target>.
        'targets' => [
            'cmi5-statement' => 'tests/Fuzz/cmi5-statement.php',
            'scorm-commit' => 'tests/Fuzz/scorm-commit.php',
        ],
    ],
    'docs' => [
        'public' => true,
        // The terms of SCORM, IMS Content Packaging, cmi5 and xAPI.
        'words' => ['cmi5', 'SCORM', 'SCO', 'SCOs', 'xAPI', 'imsmanifest', 'moveOn', 'masteryScore', 'launchMethod', 'LRS', 'HMAC', 'elearning', 'cmi', 'LMSCommit', 'LMSFinish', 'LMSInitialize', 'LMS', 'ADL', 'HHHH', 'sendBeacon', 'postMessage', 'AssetMapper', 'Webpack', 'MEDIUMTEXT', 'utf8mb4', 'MariaDB'],
    ],
];
