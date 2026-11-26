<?php

declare(strict_types=1);

use Phil\ElearningPackages\Runtime\CmiRules;
use Phil\ElearningPackages\Runtime\ElementName;

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
                '.*for \\(\\$steps = 2 \\* .*',
                // A seed drawn at random from the whole range: one more or less at either end is no different.
                '.*random_int\\(0, .*',
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
                '.*for \\(\\$steps = 2 \\* .*',
                '.*random_int\\(0, .*',
            ]],
            // Two branches of a choice are never the same child of their common ancestor. A selection of as many
            // children as there are picks them all, in their order, and draws nothing from the randomizer.
            'LessThan' => ['ignoreSourceCodeByRegex' => [
                '.*for \\(\\$other = 0, \\$count = \\$data->count.*',
                '.*\\$end < \\$start && .*',
                '.*\\$count < \\\\count\\(\\$identifiers\\).*',
            ]],
            // The flow of the sequencer returns before its budget of steps, twice the activities of the tree: each step
            // moves forward in the order of the tree, or backward until a cluster that moves forward only stops it. A
            // budget of one step more or less, or once the activities, changes no answer; it only stops a traversal
            // that would not end (`IncrementInteger` and `DecrementInteger` above, `GreaterThan` and `Decrement` here).
            'GreaterThan' => ['ignoreSourceCodeByRegex' => ['.*for \\(\\$steps = 2 \\* .*']],
            'Decrement' => ['ignoreSourceCodeByRegex' => ['.*for \\(\\$steps = 2 \\* .*']],
            // `fread()` that fails adds `false`, which is the empty string; an index is joined as its digits whether it
            // is an integer or a string. The launch of a launchable activity is never null, the identifier of an item
            // is always there once the manifest is read, the current activity is set when a request ends an attempt,
            // and a key of the state is an array key, which reads `'1'` as `1` whether it is cast or not.
            'CastString' => ['ignoreSourceCodeByRegex' => [
                '.*\\(string\\) fread\\(.*',
                '.*\\$filled\\[\\] = \\(string\\) \\$this->indexes.*',
                '.*\\$launch = \\(string\\) \\$activity->launch;.*',
                '.*\\(string\\) XmlDocument::attribute\\(\\$element, \'identifier\'\\).*',
                '.*\\$objectives\\[\\(string\\) \\$identifier\\] = .*',
                '.*\\$current = \\(string\\) \\$this->data->current;.*',
                // The pattern has no `u` flag and nothing that can fail on a response: `preg_replace()` gives text.
                '.*\\$body = \\(string\\) preg_replace\\(.*',
            ]],
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
            'ReturnRemoval' => ['ignore' => [CmiRules::class.'::evaluated::73']],
            // The keyword ends the name: the segments after it are none.
            'Continue_' => ['ignore' => [ElementName::class.'::parse::84']],
            // The current branch and the one chosen are children of the common ancestor, offered in this attempt: both
            // are found, and `array_search()` returns an integer for each.
            'CastInt' => ['ignoreSourceCodeByRegex' => ['.*\\$start = \\(int\\) array_search.*', '.*\\$end = \\(int\\) array_search.*']],
            // `SequencingRun::lessonStatus()` and `SequencingData::lessonStatus()` both ask the tree for the activity,
            // which refuses an unknown one with the same words.
            'MethodCallRemoval' => ['ignoreSourceCodeByRegex' => ['.*\\$tree->get\\(\\$activity\\);.*']],
            // An exit rule of the activity itself, the last of the path, ends the attempt that has just ended.
            'UnwrapArraySlice' => ['ignoreSourceCodeByRegex' => ['.*\\\\array_slice\\(\\$this->tree->path\\(\\$current\\), 0, -1\\).*']],
            // SCORM 1.2 and SCORM 2004 give the same code, 201, for an argument where none is expected. Without a
            // parent, `exit` and `exitAll` both end the session.
            'Ternary' => ['ignoreSourceCodeByRegex' => [
                '.*\\$is12 \\? ScormError::INVALID_ARGUMENT_12 : ScormError::GENERAL_ARGUMENT.*',
                '.*RuleAction::ExitAll === \\$action \\? RuleAction::ExitAll : RuleAction::Exit;.*',
            ]],
            // The keys of the objectives of a stored state are array keys, and an array key reads `'1'` as `1`; the
            // order of a list, which only a list passes, needs no `array_values()`.
            'UnwrapArrayMap' => ['ignoreSourceCodeByRegex' => ['.*array_combine\\(array_map\\(strval.*']],
            'UnwrapArrayCombine' => ['ignoreSourceCodeByRegex' => ['.*array_combine\\(array_map\\(strval.*']],
            'UnwrapArrayValues' => ['ignoreSourceCodeByRegex' => ['.*\\$strings = \\\\is_array\\(\\$order\\).*']],
            // A global objective written with a satisfaction takes the other fields it has; the default of a new one
            // holds both keys whatever the first says. The version of a state is read by `toArray()`, not by `trusted()`.
            'ArrayItemRemoval' => ['ignoreSourceCodeByRegex' => ['.*\\$this->globals\\[\\$map->targetObjectiveId\\] = \\[\'satisfied\' => \\$satisfied\\] \\+.*', ".*'version' => SequencingState::VERSION,.*"]],
            // The text of the time allowed is trimmed by the reader before it is matched: a line break cannot end it.
            // The same goes for the whole numbers, the IRIs and the language tags read from attributes, which are
            // trimmed, and for the tokens of a prerequisites expression, which hold no line break.
            'PregMatchRemoveFlags' => ['ignoreSourceCodeByRegex' => [
                '.*return 1 === preg_match\\(.*\\[0-5\\].*',
                '.*\\\\d\\{1,9\\}.*',
                '.*\\^\\[a-z\\].*',
                '.*\\\\d\\+\\$/D.*',
                '.*\\[\\\\w\\.\\\\-:\\]\\+.*',
            ]],
            // A token that reaches the test of an item is a quoted text, a sign or a word: the words alone match.
            'PregMatchRemoveCaret' => ['ignoreSourceCodeByRegex' => ['.*\\[\\\\w\\.\\\\-:\\]\\+.*']],
            'PregMatchRemoveDollar' => ['ignoreSourceCodeByRegex' => ['.*\\[\\\\w\\.\\\\-:\\]\\+.*']],
        ],
    ],
    'fuzz' => [
        // The readers on bytes nobody wrote, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'scorm-manifest' => 'tests/Fuzz/scorm-manifest.php',
            'cmi5-structure' => 'tests/Fuzz/cmi5-structure.php',
            'zip-package' => 'tests/Fuzz/zip-package.php',
            'cmi-commit' => 'tests/Fuzz/cmi-commit.php',
            'xapi-package' => 'tests/Fuzz/xapi-package.php',
            'prerequisites' => 'tests/Fuzz/prerequisites.php',
            'sequencing' => 'tests/Fuzz/sequencing.php',
        ],
    ],
    'docs' => [
        'public' => true,
        // The terms of SCORM, IMS Content Packaging, cmi5, xAPI and IMS Simple Sequencing.
        'words' => ['tincan', 'IRI', 'IRIs', 'imsss', 'adlseq', 'adlnav', 'adlcp', 'mapInfo', 'IDRef', 'objectiveID', 'choiceExit', 'forwardOnly', 'rollupConsiderations', 'satisfiedByMeasure', 'minNormalizedMeasure', 'objectivesGlobalToSystem', 'completedByMeasure', 'minProgressMeasure', 'progressWeight', 'aicc', 'sequencer', 'cmi5', 'SCORM', 'SCO', 'SCOs', 'xAPI', 'imsmanifest', 'moveOn', 'masteryScore', 'launchMethod', 'langstring', 'mebibyte', 'XXE', 'elearning', 'unserializes', 'cmi', 'LMS', 'LMSCommit', 'LMSFinish', 'LMSInitialize', 'ADL', 'HHHH', 'centiseconds', 'hundredths'],
    ],
];
