<?php

declare(strict_types=1);

use Phil\StructuredDataBundle\Application\PageStructuredData;
use Phil\StructuredDataBundle\Infrastructure\Twig\StructuredDataExtension;
use Phil\StructuredDataBundle\PhilStructuredDataBundle;

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'infection' => [
        'mutators' => [
            // The answer is cancelled to free its connection before the refusal: MockHttpClient, which the tests use,
            // answers the same with or without it, and the exception thrown next ends the read alike.
            'MethodCallRemoval' => [
                'ignoreSourceCodeByRegex' => ['.*\\$response->cancel\\(\\);.*'],
            ],
            // A saved page is read one byte past the bound of the reader, which then refuses it: reading two bytes past
            // it is refused alike.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => ['.*file_get_contents\\(\\$page, length: JsonLdReader::MAX_HTML_BYTES \\+ 1\\).*'],
            ],
            // Records named by their argument keep their keys: a spread of named records fills the variadic of about()
            // and of IndexingPolicy::ofAll() alike, and array_values only serves the list PHPStan reads.
            'UnwrapArrayValues' => [
                'ignore' => [PageStructuredData::class.'::about', StructuredDataExtension::class.'::about'],
            ],
            // DefaultNodes::nodes() returns an array, built whole by the call: the spread only makes sure a provider
            // that would yield its nodes is read to the end; without it, or wrapped, the same values are refused.
            'SpreadRemoval' => [
                'ignore' => [PhilStructuredDataBundle::class.'::loadExtension'],
            ],
            'SpreadAssignment' => [
                'ignore' => [PhilStructuredDataBundle::class.'::loadExtension'],
            ],
            // The breadcrumb of phil/menu-bundle is a list of arrays that each have a string name, the label of a menu
            // item: the test of the element only serves the types PHPStan reads, and an element that is no array has
            // no name either.
            'LogicalAnd' => [
                'ignoreSourceCodeByRegex' => ['if \\(\\\\is_array\\(\\$element\\) && \\\\is_string\\(\\$element\\[\'name\'\\] \\?\\? null\\)\\) \\{'],
            ],
        ],
    ],
    'docs' => [
        'public' => true,
        // The vocabulary and the formats the bundle speaks.
        'words' => ['JSON-LD', 'schema.org', 'BCP'],
    ],
];
