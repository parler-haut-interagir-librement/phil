<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'deptrac' => [
        // The application layer reads the published libraries it requires — bounce reports, addresses, Markdown,
        // webhook ids, URLs — and Symfony; the infrastructure integrates the optional phil/automations,
        // phil/privacy-bundle and phil/standard-webhooks-bundle, required in development and suggested.
        'ruleset' => [
            'PhilNewsletterBundleApplication' => ['PhilNewsletterBundleDomain', 'DefaultAllowed', 'Forbidden', 'PhilBounceParser', 'PhilEmailAddress', 'PhilMarkup', 'PhilMarkupAdapter', 'PhilStandardWebhooks', 'PhilUrl'],
        ],
    ],
    'fuzz' => [
        // The signed links on tokens nobody wrote, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'signed-link' => 'tests/Fuzz/signed-link.php',
        ],
    ],
    'infection' => [
        'mutators' => [
            // `HTMLDocument` closes the elements and the body at the end of its input, and what follows the end of the
            // body returns to it: the wrapper around the HTML only gives the parser its context, and the mutants that
            // move or drop a part of it read the same document. The order of the two fields of a link is of no weight
            // to a search for a separator.
            'Concat' => [
                'ignoreSourceCodeByRegex' => [
                    '.*createFromString\\(.<!DOCTYPE html><html><body>..\\$html\\..</body></html>.*',
                    'if \\(str_contains\\(\\$subject\\.\\$extra, .\\|.\\)\\) \\{',
                ],
            ],
            'ConcatOperandRemoval' => [
                'ignoreSourceCodeByRegex' => [
                    '.*createFromString\\(.<!DOCTYPE html><html><body>..\\$html\\..</body></html>.*',
                    // The space that joins the line after a literal to the line before it is read by nothing.
                    '\\$line \\.= . .\\.\\$this->line\\(\\);',
                ],
            ],
            // `Url::fromString()` trims the whitespace around an address itself; the sandbox of Twig, `implode()` and
            // the sort of the days do the same work as the calls the mutants unwrap.
            'UnwrapTrim' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$href = trim\\(\\(string\\) \\$anchor->getAttribute\\(.href.\\)\\);',
                    'foreach \\(explode\\(. ., trim\\(substr\\(\\$line, 8\\)\\)\\) as \\$uid\\) \\{',
                ],
            ],
            // Twig's own defaults: `autoescape` is `html`, `cache` and `strict_variables` are false.
            'ArrayItemRemoval' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$environment = new Environment\\(new ArrayLoader\\(\\), .*',
                ],
            ],
            // `implode()` writes an integer as its digits.
            'CastString' => [
                'ignoreSourceCodeByRegex' => [
                    'return substr\\(hash\\(.sha256., implode\\(.*',
                    '\\$payload = implode\\(.\\|., \\[.*',
                ],
            ],
            // A membership found and activated or a new one activated: the same membership once saved.
            'AssignCoalesce' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$membership \\?\\?= new Membership\\(.*',
                ],
            ],
            // The count of soft bounces is one at least when it is compared; `array_fill()` keys vanish in `implode()`;
            // the minutes of a day and the hour of a window come out of numeric strings the same, `$day = -1` is an
            // opening that is past, `substr($line, 7)` and `substr($line, 9)` meet the same digits, the chunk of
            // 65 536 bytes is a size and not a limit, and a delay is one second at least by the window itself.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    'if \\(\\$this->softBounces >= max\\(1, \\$threshold\\)\\) \\{',
                    'for \\(\\$day = 0; \\$day <= 7; \\+\\+\\$day\\) \\{',
                    'return \\(int\\) \\$parts\\[1\\] \\* 60 \\+ \\(int\\) \\$parts\\[2\\];',
                    '.*array_fill\\(0, \\\\count\\(\\$row\\), .\\?.\\).*',
                    'foreach \\(explode\\(. ., trim\\(substr\\(\\$line, 8\\)\\)\\) as \\$uid\\) \\{',
                    '\\$chunk = fread\\(\\$this->stream, max\\(1, min\\(65_536.*',
                    '\\$this->bus->dispatch\\(new SendDelivery\\(\\$delivery->id\\), \\[new DelayStamp\\(max\\(1, \\$seconds\\) \\* 1000\\)\\]\\);',
                ],
            ],
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*array_fill\\(0, \\\\count\\(\\$row\\), .\\?.\\).*',
                    'foreach \\(explode\\(. ., trim\\(substr\\(\\$line, 8\\)\\)\\) as \\$uid\\) \\{',
                    '.*explode\\(. ., \\$command, 2\\)\\[0\\].*',
                    '\\$chunk = fread\\(\\$this->stream, max\\(1, min\\(65_536.*',
                ],
            ],
            'UnwrapSubstr' => [
                'ignoreSourceCodeByRegex' => [
                    'foreach \\(explode\\(. ., trim\\(substr\\(\\$line, 8\\)\\)\\) as \\$uid\\) \\{',
                ],
            ],
            // A token of the IMAP server holds no new line, the `D` flag of its anchor changes nothing.
            'PregMatchRemoveFlags' => [
                'ignoreSourceCodeByRegex' => [
                    'if \\(1 === preg_match\\(./\\^\\[1-9\\]\\[0-9\\]\\{0,9\\}\\$/D., \\$uid\\)\\) \\{',
                ],
            ],
            // `sort()` renumbers the days, `(int)` is what arithmetic does with a numeric string, and an opening that
            // starts at the instant itself is the instant, which `contains()` has already given.
            'UnwrapArrayValues' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$days = array_values\\(array_unique\\(\\$days\\)\\);',
                ],
            ],
            'CastInt' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$minutes = \\(int\\) \\$local->format\\(.G.\\) \\* 60.*',
                    'return \\(int\\) \\$parts\\[1\\] \\* 60 \\+ \\(int\\) \\$parts\\[2\\];',
                ],
            ],
            'GreaterThan' => [
                'ignoreSourceCodeByRegex' => [
                    'if \\(\\\\in_array\\(\\(int\\) \\$opening->format\\(.N.\\), \\$this->days, true\\) && \\$opening->getTimestamp\\(\\) > .*',
                ],
            ],
            // The validator of addresses gives the domain in lowercase already: the call only keeps it so.
            'MBString' => [
                'ignoreSourceCodeByRegex' => [
                    'return \\$validation->address->localPart\\..@.\\.mb_strtolower\\(\\$validation->address->domain\\);',
                ],
            ],
            // The column read gives strings already: the mapping only checks and types them.
            'UnwrapArrayMap' => [
                'ignoreSourceCodeByRegex' => [
                    'return array_map\\(Sql::string\\(\\.\\.\\.\\), \\$query->executeQuery\\(\\)->fetchFirstColumn\\(\\)\\);',
                ],
            ],
        ],
    ],
    'js' => ['paths' => ['assets/src']],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'docs' => ['public' => true, 'translations' => 'blocking', 'words' => ['HMAC', 'VERP', 'IMAP', 'DBAL', 'preheader', 'Preheader', 'RemoteEvent']],
];
