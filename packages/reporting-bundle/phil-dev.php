<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'infection' => [
        'mutators' => [
            // Casts that only serve PHPStan: a widget without a metric has a series, which the configuration checks; a
            // number formatter and a stream of a few bytes fail only when their extension is broken, where the empty
            // text and `false` read alike.
            'CastString' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$widget\\[\'metric\'\\] \\?\\? \\(string\\) \\$widget\\[\'series\'\\],',
                    'return \\[\'direction\' => \\$direction, \'percent\' => \\(string\\) \\$formatter->format\\(.*',
                    'return \\(string\\) stream_get_contents\\(\\$this->handle\\);',
                ],
            ],
            // The streams closed in a `finally`: a stream left open is closed when it is collected, so no test sees the
            // difference, but a worker that runs for days would hold one descriptor per report until then.
            'UnwrapFinally' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$stream->close\\(\\);',
                    '\\$content->close\\(\\);',
                    '\\$target->close\\(\\);',
                ],
            ],
            // A stream its opener closes as soon as it is done with it — a file just written, the file an export command
            // wrote: a stream left open is closed when it is collected, so no test sees the difference, but a worker
            // that runs for days would hold one descriptor per report until then.
            'MethodCallRemoval' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$target->close\\(\\);',
                ],
            ],
            // The depth of a job's JSON is a bound against a hostile file, three levels deep when honest: a file one
            // level deeper or shallower than the bound is refused alike, for its shape.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => ['\\$job = ReportJob::fromArray\\(json_decode\\(\\$json, true, 16, .*'],
            ],
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => ['\\$job = ReportJob::fromArray\\(json_decode\\(\\$json, true, 16, .*'],
            ],
        ],
    ],
    // The package is released from its own repository: its CHANGELOG uses every section.
    'docs' => ['public' => true],
];
