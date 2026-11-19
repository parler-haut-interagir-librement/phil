<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => [
        'public' => true,
        // French month names, in the examples of days written in words.
        'words' => ['septembre', 'août'],
    ],
    'phpstan' => [
        // La dette de phil/dev-tools v2 : ce que ses règles relèvent et qui reste à corriger.
        'ignoreErrors' => [
            // à corriger : API interne de Doctrine à remplacer par une API publique.
            [
                'identifier' => 'new.internalClass',
                'path' => 'tests/Unit/PhilCalendarBundleTest.php',
                'count' => 1,
            ],
            // à corriger : déplacer les interfaces dans un espace de noms Contract (rupture de l’API publique).
            [
                'identifier' => 'symplify.requiredInterfaceContractNamespace',
                'paths' => [
                    'src/Application/AttendancePolicy.php',
                    'src/Application/EventTagging.php',
                    'src/Application/View/HostLabels.php',
                    'src/Application/View/ItemSource.php',
                    'src/Domain/Repository/AttendanceRepository.php',
                    'src/Domain/Repository/CalendarChoiceRepository.php',
                    'src/Domain/Repository/EventRepository.php',
                    'src/Domain/Repository/PersonalFeedTokenRepository.php',
                    'src/Domain/Repository/VenueRepository.php',
                ],
            ],
        ],
    ],
    'infection' => [
        'mutators' => [
            // The parts of a time of day are numeric strings, which the arithmetic of `secondOfDay()` reads as the
            // integers `intval()` gives: the conversion only serves the types of the list.
            'UnwrapArrayMap' => [
                'ignoreSourceCodeByRegex' => [
                    '\\[\\$hour, \\$minute, \\$second\\] = array_map\\(intval\\(\\.\\.\\.\\), explode\\(\':\', \\$moment->format\\(\'H:i:s\'\\)\\)\\);',
                ],
            ],
            // The reading bounds of the occurrences are margins: `Calendar` asks for one occurrence to know whether
            // there is any, and `Event::occurrences()` reads reach + 1 starts beyond the limit, the margin days
            // counting at most as many without overlap. A larger margin reads one more day or start, which the overlap
            // filter drops: the list returned is the same, only the cost changes. Likewise, the window of
            // `Event::lastStart()` that starts from two years or triples only changes where it searches: `array_last()`
            // returns the same last occurrence. The reach of a cluster of `TimeGrid` starts from the beginning of its
            // first slice: any start value at most equal to its end gives the same reach, since that end also enters
            // the `max()`.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\[\\] !== \\$event->occurrences\\(\\$from, \\$through, 1\\).*',
                    '\\$reach = \\$length \\+ 2;',
                    'foreach \\(\\$this->recurrence\\(\\)->between\\(\\$earliest, \\$through, \\$limit \\+ \\$reach \\+ 1\\) as \\$start\\) \\{',
                    'for \\(\\$years = 1;; \\$years \\*= 2\\) \\{',
                    '\\$reach = max\\(\\$reach \\?\\? \\$slice\\[1\\].*',
                    // One occurrence at an instant is the most there is: `Attendees::startsAt()` only needs the limit to be
                    // above none.
                    '.*array_any\\(\\$event->occurrences\\(\\$start, \\$start, 10\\).*',
                ],
            ],
            // The default page of `Attendees::attendees()` starts at the first attendee: a negative offset is brought
            // back to none by `max(0, $offset)`; and see `IncrementInteger` for the limit of `startsAt()`.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*Occurrence\\|Event \\$what, int \\$limit = 100, int \\$offset = 0\\): array.*',
                    '.*array_any\\(\\$event->occurrences\\(\\$start, \\$start, 10\\).*',
                ],
            ],
            // The coordinates of a venue: `Venue::locate()` keeps the latitude and the longitude together or not at
            // all, and the fallback value of the latitude, only there for PHPStan, is never read.
            'OneZeroFloat' => [
                'ignoreSourceCodeByRegex' => [
                    'return null === \\$this->longitude \\? null : \\[\\$this->latitude \\?\\? 0\\.0, \\$this->longitude\\];',
                ],
            ],
            // The years of `CalendarComponent` are four-digit strings that the subtraction converts on its own; the
            // cast only serves PHPStan's strict rules on arithmetic.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$years = abs\\(\\(int\\) \\$day->format\\(\'Y\'\\) - \\(int\\) \\$now->format\\(\'Y\'\\)\\);',
                    // The days of a printed month are numbers `range()` reads as numbers, whether the text of the date
                    // or an integer.
                    '\\$first = \\$item->firstDay\\(\\)->format\\(\'Y-m\'\\) < \\$of \\? 1 : \\(int\\).*',
                    '\\$last = \\$item->lastDay\\(\\)->format\\(\'Y-m\'\\) > \\$of \\? \\(int\\).*',
                ],
            ],
            // The search for the last start of `Event::lastStart()`: when the window starts from the first start and
            // finds no occurrence there, there is none earlier, and the next round also returns the first start.
            'LessThanOrEqualTo' => [
                'ignoreSourceCodeByRegex' => [
                    'if \\(\\[\\] !== \\$starts \\|\\| \\$from <= \\$this->start\\(\\)\\) \\{',
                ],
            ],
            // The ICU formatters by locale: creating them again gives the same result, only the cost changes.
            'AssignCoalesce' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$formatter = \\$this->formatters\\[\\$locale\\]\\[\\$dateType\\].* \\?\\?= new IntlDateFormatter\\(.*',
                ],
            ],
            // The values of the sets of `MemberChoices` are only read by `isset()`, which is true for `false` as for
            // `true`: the key is the information, the value only there for the type of the array.
            'TrueValue' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$(hidden|followed)\\[\\$choice->source\\(\\)->value\\]\\[\\$choice->value\\(\\)\\] = true;',
                ],
            ],
            // A data subject of phil/privacy-bundle never holds a whitespace or a control character, a line break
            // included: the `D` flag of the member's reference changes nothing for what reaches `member()`.
            'PregMatchRemoveFlags' => [
                'ignoreSourceCodeByRegex' => [
                    '.*preg_match\\(\'/\\^\\[\\\\w\\.:-\\]\\{1,64\\}\\$/D\', \\$subject->value\\).*',
                ],
            ],
            // The memos of an event (recurrence, end of series): without the `from` key, the memo never serves and the
            // value is computed again, identical.
            'ArrayItemRemoval' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$this->recurrenceOf = \\[\'from\' => \\$from, \'recurrence\' => .*',
                    '\\$this->seriesEndOf = \\[\'from\' => \\$from, \'end\' => .*',
                ],
            ],
        ],
    ],
    'deptrac' => [
        // The almanac view and the printed calendar are optional integrations (phil/almanac and phil/paged-media are
        // suggested): the application layer that imports the computed dates, or prints the months, sees the library.
        // The texts of events read the translations of phil/doctrine-behaviours and the locales of
        // phil/locale-dimensions, both required.
        'ruleset' => [
            'PhilCalendarBundleApplication' => ['PhilCalendarBundleDomain', 'DefaultAllowed', 'Forbidden', 'PhilRecurrence', 'PhilRecurrenceBundleDomain', 'PhilRecurrenceBundleApplication', 'PhilRecurrenceBundle', 'PhilAlmanac', 'PhilDoctrineBehaviours', 'PhilLocaleDimensions', 'PhilUrl', 'PhilPagedMedia'],
        ],
    ],
];
