<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true, 'words' => ['EICAR', 'OOXML']],
    'infection' => [
        // Each mutant left alive by name is an equivalent: no input can tell it from the code. The reason is given
        // by file and method.
        'mutators' => [
            'LogicalAnd' => [
                // FilesystemPendingImports::keep(): the directory looked at again after a failed mkdir() is only
                // there when another process made it in between.
                'ignoreSourceCodeByRegex' => ['.*!is_dir\(\$this->directory\) && !@mkdir\(.*'],
            ],
            'LogicalAndSingleSubExprNegation' => [
                // PhilDocumentImportBundle::loadExtension(): symfony/messenger is installed wherever the tests run,
                // so the condition is false whichever way async is negated.
                'ignoreSourceCodeByRegex' => ['.*\$config\[.async.\]\[.enabled.\] && !interface_exists\(.*'],
            ],
        ],
    ],
];
