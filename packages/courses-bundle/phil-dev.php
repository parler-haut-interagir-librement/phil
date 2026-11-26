<?php

declare(strict_types=1);

use Phil\CoursesBundle\Application\Scorm\ScormRuntime;
use Phil\CoursesBundle\Application\Training\Transcripts;
use Phil\CoursesBundle\Infrastructure\Doctrine\CourseTables;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalCatalogueRepository;
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
            // Past the five hundredth target the loop only reads the rest of the outline: a `continue` there names none
            // either, so the answer is the same and only the walk is longer.
            'Break_' => ['ignore' => [ScormRuntime::class.'::targets::313']],
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
                // The learner of a catalogue obligation is a column of its primary key too.
                CourseTables::class.'::tables::335',
            ], 'ignoreSourceCodeByRegex' => [
                '.*self::column\\(\'(activity_hash|state_id)\'.*',
                '.*\\$(seen|holding)\\[\\$block\\] = true;.*',
                // The activities with an ended attempt are read by their keys: the value only marks them.
                '.*\\$activities\\[\\$attempt->activity->value\\] = true;.*',
                // The courses assigned to a learner are read by their keys: `isset` is true for a value of `false`.
                '.*\\$assigned\\[\\$obligation->course->value\\] = true;.*',
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
                // The same for the boolean of an entry open to self-enrollment.
                '.*\\], \\[.self_enrollment. => Types::BOOLEAN, .price_minor. => Types::BIGINT\\]\\);.*',
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
                // A page below the first reads as the first: the query bounds it on the way in.
                '.*int \\$page = 1,.*',
                '.*\\(int\\) \\$page : 1,.*',
                // The limit and the offset of a page of catalogues are bounded again by the store.
                '.*all\\(max\\(1, min\\(\\$limit, 200\\)\\), max\\(0, \\$offset\\)\\).*',
                // The ranks of the languages keep their order whichever number the best one takes.
                '.*\\$course->language === \\$language => 0,.*',
            ]],
            'IncrementInteger' => ['ignoreSourceCodeByRegex' => [
                // A percentage is held between 0 and 100 by the line that follows: a whole path reads 100 at most, and a
                // bound raised to 101 only lets through what 100 * completed / required never reaches.
                '.*\\$whole = \\$enrollment->isCompleted\\(\\) \\? 100 : 0;.*',
                '.*max\\(0, min\\(100, \\$percent\\)\\).*',
                '.*json_decode\\(\\$json, true, 4, .*',
                // Depths counted by two instead of one keep the order of the blocks: only their differences count.
                '.*\\(\\$depths\\[\\$parent\\] \\?\\? 0\\) \\+ 1.*',
                // The rank of a course in no language and the default of the first one stay behind the others whichever number they take.
                '.*default => 2,.*',
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
                // The digits an amount matched by its pattern multiply and add as the numbers they read: only the type of the sum is at stake.
                '.*self::hundredths\\(\\(int\\) \\$whole \\* 100 \\+ \\(int\\) str_pad.*',
            ]],
            // `finish` is a boolean whichever default stands in for its absence: both are checked as booleans.
            // Nothing sets a `Vary` on a response before it is answered: adding to the header or replacing it gives the same one.
            'FalseValue' => ['ignoreSourceCodeByRegex' => ['.*is_bool\\(\\$body\\[.finish.\\] \\?\\? false\\).*', '.*\\$response->setVary\\(.Origin., false\\);.*']],
            // No enrollment asks for no row, and the query of an empty list reads none either.
            // The tag of an entry is trimmed before its pattern reads it, and so is a currency: a line break at their end never reaches the `$`.
            'PregMatchRemoveFlags' => ['ignoreSourceCodeByRegex' => [
                '.*preg_match\\(./\\^\\[\\\\p\\{L\\}\\\\p\\{N\\}\\].*',
                '.*preg_match\\(./\\^\\[A-Z\\]\\{3\\}\\$/D., \\$currency\\).*',
            ]],
            // Without the digits of the currency, the formatter writes the ones its own data gives: the same for every currency.
            'MethodCallRemoval' => ['ignoreSourceCodeByRegex' => ['.*\\$formatter->setAttribute\\(NumberFormatter::FRACTION_DIGITS, \\$digits\\);.*']],
            // Symfony's data gives two digits to a currency it does not know, as the test sets them apart.
            'LogicalAnd' => ['ignoreSourceCodeByRegex' => ['.*class_exists\\(Currencies::class\\) && Currencies::exists.*']],
            'ReturnRemoval' => ['ignore' => [
                DbalScormAttemptRepository::class.'::ofEnrollments::94',
                // A transcript holds none of no course, and the query of an empty list reads none either.
                Transcripts::class.'::holdsAny::91',
                DbalLearningPathRepository::class.'::openEnrollmentsWith::144',
                // No unit asks for the public catalogues only; the query of the units reads them by the same test.
                DbalCatalogueRepository::class.'::shownTo::77',
            ]],
            'UnwrapArrayValues' => ['ignoreSourceCodeByRegex' => [
                // The keys of the courses kept are never read: they are counted, searched and saved as a list.
                '.*\\$others = array_values\\(array_filter\\(\\$this->groups->courses.*',
                '.*array_values\\(array_filter\\(\\$step->prerequisites, static fn.*',
                // The modules are appended, or replaced at their place: their keys follow each other already. The list is
                // kept for the types.
                '.*array_values\\(\\$modules\\).*',
                // The driver expands a list parameter by its values, whatever the keys of the list: the names a variadic call may give.
                // The tags are listed once by `sort()`, the units by `array_slice()`, the courses of a page by `usort()` or `array_slice()`
                // and the tags of an entry by its constructor: each takes the keys away after the call.
                '.*\\$tags = array_values\\(array_unique\\(array_merge.*',
                '.*array_slice\\(array_values\\(array_unique\\(\\$this->units->units.*',
                '.*return array_values\\(array_filter\\(\\$offered, static fn \\(OfferedCourse \\$course\\): bool => match.*',
                '.*return array_values\\(array_filter\\(\\$offered, static fn \\(OfferedCourse \\$course\\): bool => !\\$course->group.*',
                '.*\\? array_values\\(array_filter\\(\\$values, is_string.*',
                '.*return array_values\\(array_map\\(static fn \\(EnrollmentId \\$id\\): string => \\$id->value, \\$enrollments\\)\\);.*',
            ]],
        ],
    ],
    'fuzz' => [
        // The checks of the cmi5 and xAPI statements, the body of a SCORM commit and the query string of a catalogue page on
        // bytes nobody wrote, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'catalogue-query' => 'tests/Fuzz/catalogue-query.php',
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
