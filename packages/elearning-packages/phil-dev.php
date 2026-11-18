<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'infection' => [
        'mutators' => [
            // `getExternalAttributesIndex()` sets both before they are read; when it fails, the system is not Unix
            // whatever its starting value, and no link is seen. The other lines are defaults no path reads: a
            // collection pattern the data model always bounds, a count that is `index + 1` at least, the first of the
            // groups of a duration, which a match always fills, and the overflow guard of a duration, which the
            // bound of `ofCentiseconds()` repeats for the sums the guard lets through.
            'IncrementInteger' => ['ignoreSourceCodeByRegex' => [
                '\\$system = 0;',
                '\\$attributes = 0;',
                '.*DataModel::collections\\(\\$this->version\\)\\[\\$pattern\\] \\?\\? 0.*',
                '.*\\$this->counts\\[\\$collection\\] \\?\\? 0.*',
                '.*\\(int\\) \\(\\$match\\[1\\] \\?\\? 0\\) \\* 365.*',
            ]],
            'DecrementInteger' => ['ignoreSourceCodeByRegex' => [
                '\\$system = 0;',
                '\\$attributes = 0;',
                '.*DataModel::collections\\(\\$this->version\\)\\[\\$pattern\\] \\?\\? 0.*',
                '.*\\$this->counts\\[\\$collection\\] \\?\\? 0.*',
                '.*\\(int\\) \\(\\$match\\[1\\] \\?\\? 0\\) \\* 365.*',
                '.*intdiv\\(self::MAX_CENTISECONDS, 100\\).*',
                // The text of the hours reads as the whole match: PHP casts the digits before the first colon.
                '.*\\(\\(int\\) \\$match\\[1\\] \\* 60 \\+.*',
                // Offsets from -1 and bounds one past the count read the same empty slots.
                '.*for \\(\\$other = 0, \\$count = \\$data->count.*',
            ]],
            'LessThan' => ['ignoreSourceCodeByRegex' => ['.*for \\(\\$other = 0, \\$count = \\$data->count.*']],
            // `fread()` that fails adds `false`, which is the empty string; an index is joined as its digits whether it
            // is an integer or a string.
            'CastString' => ['ignoreSourceCodeByRegex' => ['.*\\(string\\) fread\\(.*', '.*\\$filled\\[\\] = \\(string\\) \\$this->indexes.*']],
            // `masteryScore()` has checked the value with `is_numeric()`: PHP compares and divides a numeric string
            // as the float it casts to, and the return type makes the float of an integer quotient. The values of the
            // run time are numeric strings too: a pattern or `is_numeric()` has checked each before it is compared.
            'CastFloat' => ['ignoreSourceCodeByRegex' => [
                '\\$score = \\(float\\) \\$value;',
                '.*\\(float\\) \\$raw >= \\(float\\) \\$mastery.*',
                '.*\\(float\\) \\$progress >= \\(float\\) \\$threshold.*',
                '.*\\(float\\) \\$scaled >= \\(float\\) \\$passing.*',
                '.*\\$number = \\(float\\) \\$text;.*',
                '.*\\$low = self::isNumber\\(\\$min\\) \\? \\(float\\) \\$min.*',
                '.*\\$high = self::isNumber\\(\\$max\\) \\? \\(float\\) \\$max.*',
                '.*\\(\\(float\\) \\$raw - \\$low\\) / \\(\\$high - \\$low\\).*',
            ]],
            // An element of a document always has its document: `?->` and `->` read the same.
            'NullSafeMethodCall' => ['ignoreSourceCodeByRegex' => ['.*ownerDocument\\?->saveXML.*']],
            // The model of a version is kept once built: building it again changes nothing but the time it takes.
            'AssignCoalesce' => ['ignoreSourceCodeByRegex' => ['.*self::\\$definitions\\[\\$key\\] \\?\\?= .*']],
            // The children are listed by their keys: what each key holds is never read.
            'TrueValue' => ['ignoreSourceCodeByRegex' => ['.*\\$children\\[explode\\(.*\\] = true;.*']],
            // No pattern of the data model holds a `/`: the escape is for the patterns a version may add.
            'UnwrapStrReplace' => ['ignoreSourceCodeByRegex' => ['.*\\.str_replace\\(.*\\$pattern.*']],
            // Every element of the data model with a range has a minimum, and a blank text casts to zero, which a
            // minimum of zero or less accepts: whether the range is read for a blank text or for an element without a
            // minimum changes no answer. `is_numeric()` is false for null and for the empty text alike.
            'NotIdentical' => ['ignoreSourceCodeByRegex' => ['.*\\$text && \\(null !== \\$definition->min.*']],
            'LogicalAnd' => ['ignoreSourceCodeByRegex' => [
                '.*\\$text && \\(null !== \\$definition->min.*',
                '.*return null !== \\$value && .*is_numeric.*',
            ]],
            // SCORM 1.2 has none of the values SCORM 2004 evaluates, so the guard of its version is a shortcut.
            'ReturnRemoval' => ['ignore' => ['Phil\\ElearningPackages\\Runtime\\CmiRules::evaluated::73']],
            // The keyword ends the name: the segments after it are none.
            'Continue_' => ['ignore' => ['Phil\\ElearningPackages\\Runtime\\ElementName::parse::84']],
            // SCORM 1.2 and SCORM 2004 give the same code, 201, for an argument where none is expected.
            'Ternary' => ['ignoreSourceCodeByRegex' => ['.*\\$is12 \\? ScormError::INVALID_ARGUMENT_12 : ScormError::GENERAL_ARGUMENT.*']],
            // The text of the time allowed is trimmed by the reader before it is matched: a line break cannot end it.
            'PregMatchRemoveFlags' => ['ignoreSourceCodeByRegex' => ['.*return 1 === preg_match\\(.*\\[0-5\\].*']],
        ],
    ],
    'fuzz' => [
        // The readers on bytes nobody wrote, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'scorm-manifest' => 'tests/Fuzz/scorm-manifest.php',
            'cmi5-structure' => 'tests/Fuzz/cmi5-structure.php',
            'zip-package' => 'tests/Fuzz/zip-package.php',
            'cmi-commit' => 'tests/Fuzz/cmi-commit.php',
        ],
    ],
    'docs' => [
        'public' => true,
        // The terms of SCORM, IMS Content Packaging and cmi5.
        'words' => ['cmi5', 'SCORM', 'SCO', 'SCOs', 'xAPI', 'imsmanifest', 'moveOn', 'masteryScore', 'launchMethod', 'langstring', 'mebibyte', 'XXE', 'elearning', 'unserializes', 'cmi', 'LMS', 'LMSCommit', 'LMSFinish', 'LMSInitialize', 'ADL', 'HHHH', 'centiseconds', 'hundredths'],
    ],
];
