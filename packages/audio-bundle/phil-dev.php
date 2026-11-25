<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'infection' => [
        'mutators' => [
            // The code of an exception chained to its cause is the default zero, which nothing reads; and the file of an
            // excerpt that is heard has an excerpt, so that the start the player reads from it has no fallback to need.
            'IncrementInteger' => ['ignoreSourceCodeByRegex' => ['.*, 0, \\$\\w+\\);', '.*->policy->excerpt->start \\?\\? 0.*']],
            'DecrementInteger' => ['ignoreSourceCodeByRegex' => ['.*, 0, \\$\\w+\\);', '.*->policy->excerpt->start \\?\\? 0.*']],
            // The build of Symfony's bundle class does nothing: the call only keeps the override complete.
            'MethodCallRemoval' => ['ignoreSourceCodeByRegex' => ['.*parent::build\\(\\$container\\);.*']],
            // phil/media-bundle is installed where the tests run: its repository, its library and its storage exist
            // together, and no test can uninstall one of them, so that the files and the renditions are always known.
            'LogicalAnd' => ['ignoreSourceCodeByRegex' => [
                '.*\\$stored && interface_exists\\(MessageBusInterface::class\\).*',
                '.*interface_exists\\(MediaRepository::class\\) && class_exists\\(MediaLibrary::class\\);',
                '.*\\$container->has\\(AudioFiles::class\\) && \\$container->has\\(RenditionStore::class\\);',
            ]],
        ],
    ],
    'docs' => ['public' => true, 'translations' => 'blocking', 'words' => ['ffprobe', 'WCAG']],
];
