<?php

declare(strict_types=1);

use Phil\Audio\Ffmpeg\FfmpegAudio;
use Phil\Audio\Ffmpeg\ProcessCommandRunner;
use Phil\Audio\PeaksComputer;

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'infection' => [
        'mutators' => [
            // The code of an exception chained to its cause is the default zero, which nothing reads; the limits a reader or
            // a computer is built with by default are a documented policy, whose behaviour is tested with limits a test can
            // reach; and the default run length of the null implementation only gives its signature the length of the real
            // one.
            'IncrementInteger' => ['ignoreSourceCodeByRegex' => [
                '.*, 0, \\$\\w+\\);',
                '.*private int \\$max(Bytes|Length) = .*',
                '.*int \\$samplesPerPixel = 800\\).*',
                // The parameters of a media type are read from its second part: the first is the type, which holds no
                // `codecs=` that would give a format; and a limit of two pieces or three gives the same first piece.
                '.*explode\\(.*, 2\\)\\[0\\].*',
                // A document with no version is a version 1; 1 or 2 are both read, so the default changes nothing.
                '.*\\$version = \\$document\\[\'version\'\\] \\?\\? 1;',
                // A process that was not stopped by a signal has an exit code: the fallback only gives the type.
                '.*getExitCode\\(\\) \\?\\? -1.*',
            ]],
            'DecrementInteger' => ['ignoreSourceCodeByRegex' => [
                '.*, 0, \\$\\w+\\);',
                '.*private int \\$max(Bytes|Length) = .*',
                '.*int \\$samplesPerPixel = 800\\).*',
                '.*\\\\array_slice\\(\\$parts, 1\\).*',
                // The larger of the lowest and the highest sample of a run is never negative, as the lowest is at most the
                // highest: the start of the maximum is any negative or zero value.
                '.*\\$peak = 0;',
                // A chunk of no whole sample gives an empty unpack(): skipping it is the same as reading it.
                '.*if \\(0 === \\$even\\) \\{',
                // A process that was not stopped by a signal has an exit code: the fallback only gives the type.
                '.*getExitCode\\(\\) \\?\\? -1.*',
            ]],
            'UnwrapArraySlice' => ['ignoreSourceCodeByRegex' => ['.*\\\\array_slice\\(\\$parts, 1\\).*']],
            // unpack() with `v*` ignores the byte that ends an odd string.
            'UnwrapSubstr' => ['ignoreSourceCodeByRegex' => ['.*unpack\\(\'v\\*\', substr\\(\\$chunk, 0, \\$even\\)\\);']],
            // PHP forgets the cached size of a file it never read before the run, and the file has no other reader here.
            'FunctionCallRemoval' => ['ignoreSourceCodeByRegex' => ['.*clearstatcache\\(true, \\$target\\);']],
            // A handle is closed by PHP when it goes out of scope, a test cannot see the descriptor stay open one moment more.
            'UnwrapFinally' => ['ignore' => [
                PeaksComputer::class.'::fromString',
                FfmpegAudio::class.'::peaks',
            ]],
            // The process has ended when waitUntil() gives up waiting; wait() only checks its timeout again.
            'MethodCallRemoval' => ['ignoreSourceCodeByRegex' => ['.*\\$process->wait\\(\\);']],
            // Symfony Process starts a program with `exec` through the shell: a missing program is an exit code 126 or 127,
            // and this branch is reached only when the system cannot open a pipe or fork — no test can provoke that.
            'CatchBlockRemoval' => ['ignore' => [ProcessCommandRunner::class.'::run']],
        ],
    ],
    'docs' => ['public' => true, 'translations' => 'blocking', 'words' => ['audiowaveform', 'ffprobe', 'libopus', 'libmp3lame', 'muxer', 'WCAG']],
    'fuzz' => [
        // Each reader on documents nobody wrote, from the seeds of tests/Fuzz/corpus/<target>: whatever it reads, it
        // returns what it read or throws an exception of the library.
        'targets' => [
            'peaks' => 'tests/Fuzz/peaks.php',
            'ffprobe' => 'tests/Fuzz/ffprobe.php',
        ],
    ],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
];
