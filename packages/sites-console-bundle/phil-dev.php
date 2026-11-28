<?php

declare(strict_types=1);

use Phil\SitesConsoleBundle\Domain\Model\ConfigurationPush;
use Phil\SitesConsoleBundle\Domain\Model\UpdatePlan;
use Phil\SitesConsoleBundle\Infrastructure\Composer\LockFilePackages;

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true, 'translations' => 'blocking'],
    'deptrac' => [
        // The PSR logger is of the default; another package lists it as a layer of its own, which takes it out of the
        // default for the others: the agent logs the requests it refuses and the keys the console rotates, and the
        // console logs the sites it could not read.
        'allow' => ['^Psr\\\\Log\\\\LoggerInterface$'],
    ],
    'fuzz' => [
        // The status an agent answers, the composer.lock of a site, and the key requests and stored console keys of an
        // agent, read from bytes nobody wrote, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'status-document' => 'tests/Fuzz/status-document.php',
            'composer-lock' => 'tests/Fuzz/composer-lock.php',
            'key-request' => 'tests/Fuzz/key-request.php',
        ],
    ],
    'infection' => [
        'mutators' => [
            // The keys of the refused settings are only written in a sentence, and the sites of a push are only counted
            // and walked: a list or a map with gaps reads the same. array_reverse numbers its result anew.
            'UnwrapArrayValues' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$refused = array_values\\(array_filter\\(.*',
                    '.*array_reverse\\(array_values\\(\\$entries\\)\\).*',
                    // The groups are sorted right after, which numbers them anew.
                    '.*\\$unique = array_values\\(array_unique\\(\\$groups\\)\\);.*',
                ],
                'ignore' => [ConfigurationPush::class.'::create'],
            ],
            // What no test can tell apart: the status a stored answer carries is never read, only its body; a limit is
            // clamped to 1 again by every store; the first snapshot of a history is the same whether one or two are
            // asked; the rows a DELETE removes always come back as an integer; a version that has no order, which is
            // compared as 0, gives no alert whether it stands for 0, 1 or -1; the ranks of the health states only
            // order them; the read of a lock file is capped one byte past its limit, and two bytes past it as well.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*StoredResponse::of\\(200, .*',
                    '.*self::Down => 3,.*',
                    '.*\\$this->history\\(\\$siteId, 1\\)\\[0\\] \\?\\? null;.*',
                    '.*\\?\\? 0\\) < 0.*',
                    '.*\\$removed \\+= \\\\is_int.*',
                    '.*MAX_BYTES \\+ 1\\) : false;.*',
                ],
            ],
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*StoredResponse::of\\(200, .*',
                    '.*self::Up => 0,.*',
                    '.*->recent\\(max\\(1, \\$limit\\).*',
                    '.*\\$this->history\\(\\$siteId, 1\\)\\[0\\] \\?\\? null;.*',
                    '.*\\?\\? 0\\) > 0.*',
                    '.*\\$removed \\+= \\\\is_int.*',
                ],
            ],
            // The date is made of digits, which PHP subtracts and writes the same as integers.
            // A declared length is only digits when it is cast, and PHP compares a numeric string with an integer by value.
            'CastInt' => ['ignoreSourceCodeByRegex' => ['.*abs\\(\\(int\\) \\$date - .*', '.*\\(int\\) \\$declared > \\$maxBytes.*']],
            // implode() and the headers of Symfony write an integer as its digits.
            'CastString' => [
                'ignoreSourceCodeByRegex' => [
                    '.*self::VERSION\\.\' (request|response)\'.*',
                    '.*\\$headers\\[\'Retry-After\'\\] = \\(string\\) .*',
                ],
            ],
            // Two equal ranks are the same state, so which one is returned does not show.
            'GreaterThanOrEqualTo' => ['ignoreSourceCodeByRegex' => ['.*return \\$this->rank\\(\\) >= \\$other->rank\\(\\).*']],
            // Only one of the expected version and the fleet's is missing here: the other one is returned by either order.
            'Coalesce' => ['ignoreSourceCodeByRegex' => ['.*return \\$expected \\?\\? \\$fleet;.*']],
            // A plan that is finished has no pending step left, and a lock file that holds no object has no section.
            'ReturnRemoval' => [
                'ignore' => [
                    UpdatePlan::class.'::next',
                    LockFilePackages::class.'::read',
                ],
            ],
            // The snapshot that is being looked at is in the store, so its site has a latest one.
            'NullSafeMethodCall' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$latest\\?->id\\(\\) \\?\\? \'\'.*',
                    '.*\\$this->latest\\(\\$snapshot->siteId\\(\\)\\)\\?->id\\(\\) !== \\$id.*',
                ],
            ],
            // Absent or false, "replayed" is a boolean: the check passes the same way.
            'FalseValue' => ['ignoreSourceCodeByRegex' => ['.*!\\\\is_bool\\(\\$object\\[\'replayed\'\\] \\?\\? false\\).*']],
        ],
    ],
    'phpstan' => [
        // The fuzz targets run inside the fuzzer, which gives them what PHPStan does not know.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
];
