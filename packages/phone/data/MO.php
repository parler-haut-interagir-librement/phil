<?php

declare(strict_types=1);

// Generated from libphonenumber's metadata (Apache License 2.0) by Phil\Phone\Metadata\MetadataImporter.
return ['id' => 'MO', 'countryCode' => 853, 'internationalPrefix' => '00', 'preferredInternationalPrefix' => null, 'nationalPrefix' => null, 'nationalPrefixForParsing' => null, 'nationalPrefixTransformRule' => null, 'leadingDigits' => null, 'preferredExtnPrefix' => null, 'general' => ['pattern' => '0800\\d{3}|(?:28|[68]\\d)\\d{6}', 'lengths' => [7, 8]], 'types' => ['fixedLine' => ['pattern' => '(?:28[2-9]|8(?:11|[2-57-9]\\d))\\d{5}', 'lengths' => [8]], 'mobile' => ['pattern' => '6800[0-79]\\d{3}|6(?:[235]\\d\\d|6(?:0[0-5]|[1-9]\\d)|8(?:0[1-9]|[14-8]\\d|2[5-9]|[39][0-4]))\\d{4}', 'lengths' => [8]], 'tollFree' => ['pattern' => '0800\\d{3}', 'lengths' => [7]]], 'formats' => [['pattern' => '(\\d{4})(\\d{3})', 'leadingDigits' => '0', 'format' => '$1 $2', 'nationalRule' => null, 'intlFormat' => '$1 $2'], ['pattern' => '(\\d{4})(\\d{4})', 'leadingDigits' => '[268]', 'format' => '$1 $2', 'nationalRule' => null, 'intlFormat' => '$1 $2']]];
