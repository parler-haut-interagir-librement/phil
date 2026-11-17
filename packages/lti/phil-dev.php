<?php

declare(strict_types=1);

use Phil\Lti\Jwt\Base64Url;
use Phil\Lti\Key\KeySet;
use Phil\Lti\Key\SafeHttpKeySetSource;
use Phil\Lti\Message\Roles;
use Phil\Lti\Platform\Ags\AgsService;
use Phil\Lti\Platform\Nrps\InMemoryRoster;
use Phil\Lti\Platform\Nrps\NrpsService;
use Phil\Lti\Platform\Token\TokenEndpoint;
use Phil\Lti\Registration\InMemoryPlatformRegistrations;
use Phil\Lti\Registration\InMemoryToolRegistrations;
use Phil\Lti\Service\AccessTokenProvider;

/*
 * What the shared development tooling reads of this package: its paths and its exceptions to the shared
 * configurations, nothing else.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true],
    'deptrac' => [
        // The tokens of the launches, the deep links and the services are signed and verified by web-token/jwt-library
        // (EH-1), as in phil/open-badges: no JOSE code of its own.
        'allow' => ['^Jose\\\\.*'],
    ],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'fuzz' => [
        // The readers of what the other side sends, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'lti-jwt' => 'tests/Fuzz/jwt.php',
            'lti-launch-claims' => 'tests/Fuzz/launch-claims.php',
            'lti-link-header' => 'tests/Fuzz/link-header.php',
        ],
    ],
    'infection' => [
        'mutators' => [
            'DecrementInteger' => [
                'ignore' => [
                    // The offset is brought back to 0 at the first line of each of these methods.
                    AgsService::class.'::lineItems::49',
                    AgsService::class.'::results::151',
                    NrpsService::class.'::page::36',
                ],
            ],
            // The constructor of the set refuses more keys with the same words: the early refusal only stops reading.
            'Throw_' => ['ignore' => [KeySet::class.'::fromJson::66']],
            // The key set sources take `$refresh` to answer the interface: this one has no copy to set aside.
            'FalseValue' => ['ignore' => [SafeHttpKeySetSource::class.'::keySet::40']],
            // Array keys that are numbers as text become integers on their own; the callback of `array_filter()` and the
            // return of a float for an integer are read in weak mode.
            'CastString' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$object\\[\\(string\\) \\$name\\] = \\$member;',
                    '\\$map\\[\\(string\\) \\$key\\] = \\$text;',
                    '\\$query = array_filter\\(.*\\(string\\) \\$limit.*',
                ],
            ],
            'CastFloat' => ['ignoreSourceCodeByRegex' => ['return \\(float\\) \\$value;']],
            // An empty text decodes to an empty text, and a role with a colon is the full name that no role of the
            // vocabulary is the suffix of: the early return does not change what comes back.
            'ReturnRemoval' => [
                'ignore' => [
                    Base64Url::class.'::decode::26',
                    Roles::class.'::full::73',
                ],
            ],
            // The maps are asked about with `isset()`: what they hold under a key is never read.
            'TrueValue' => [
                'ignore' => [
                    InMemoryRoster::class.'::add::36',
                    InMemoryPlatformRegistrations::class.'::add::29',
                    InMemoryToolRegistrations::class.'::add::29',
                ],
            ],
            // The lists are sliced (which numbers them again), sorted or filtered by the scopes of the vocabulary next.
            'UnwrapArrayValues' => [
                'ignore' => [
                    InMemoryRoster::class.'::members::51',
                    TokenEndpoint::class.'::issue::60',
                    AccessTokenProvider::class.'::token::48',
                ],
            ],
            'UnwrapArrayFilter' => ['ignore' => [TokenEndpoint::class.'::issue::60']],
            // An empty relation in the list of a link changes nothing: the relation asked for is never empty.
            'UnwrapTrim' => ['ignoreSourceCodeByRegex' => ['\\$relations = preg_split\\(.*strtolower\\(trim\\(.*']],
            // The catch of `LtiException` after them gives the same refusal: `invalidClient` with the same message.
            'CatchBlockRemoval' => [
                'ignoreSourceCodeByRegex' => [
                    '\\} catch \\(InvalidToken \\$invalidToken\\) \\{',
                    '\\} catch \\(TokenRequestRefused \\$tokenRequestRefused\\) \\{',
                ],
            ],
        ],
    ],
];
