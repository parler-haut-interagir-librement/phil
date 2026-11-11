<?php

declare(strict_types=1);

// Generated from libphonenumber's metadata (Apache License 2.0) by Phil\Phone\Metadata\MetadataImporter.
return ['id' => 'WS', 'countryCode' => 685, 'internationalPrefix' => '0', 'preferredInternationalPrefix' => null, 'nationalPrefix' => null, 'nationalPrefixForParsing' => null, 'nationalPrefixTransformRule' => null, 'leadingDigits' => null, 'preferredExtnPrefix' => null, 'general' => ['pattern' => '(?:[2-6]|8\\d{5})\\d{4}|[78]\\d{6}|[68]\\d{5}', 'lengths' => [5, 6, 7, 10]], 'types' => ['fixedLine' => ['pattern' => '6[1-9]\\d{3}|(?:[2-5]|60)\\d{4}', 'lengths' => [5, 6]], 'mobile' => ['pattern' => '(?:7[1-35-8]|8(?:[3-7]|9\\d{3}))\\d{5}', 'lengths' => [7, 10]], 'tollFree' => ['pattern' => '800\\d{3}', 'lengths' => [6]]], 'formats' => [['pattern' => '(\\d{5})', 'leadingDigits' => '[2-5]|6[1-9]', 'format' => '$1', 'nationalRule' => null, 'intlFormat' => '$1'], ['pattern' => '(\\d{3})(\\d{3,7})', 'leadingDigits' => '[68]', 'format' => '$1 $2', 'nationalRule' => null, 'intlFormat' => '$1 $2'], ['pattern' => '(\\d{2})(\\d{5})', 'leadingDigits' => '7', 'format' => '$1 $2', 'nationalRule' => null, 'intlFormat' => '$1 $2']]];
