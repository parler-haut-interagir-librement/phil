<?php

declare(strict_types=1);

// Generated from libphonenumber's metadata (Apache License 2.0) by Phil\Phone\Metadata\MetadataImporter.
return ['id' => 'QA', 'countryCode' => 974, 'internationalPrefix' => '00', 'preferredInternationalPrefix' => null, 'nationalPrefix' => null, 'nationalPrefixForParsing' => null, 'nationalPrefixTransformRule' => null, 'leadingDigits' => null, 'preferredExtnPrefix' => null, 'general' => ['pattern' => '800\\d{4}|(?:2|800)\\d{6}|(?:0080|[3-7])\\d{7}', 'lengths' => [7, 8, 9, 11]], 'types' => ['fixedLine' => ['pattern' => '4(?:(?:[014]\\d\\d|999)\\d|2022)\\d{3}', 'lengths' => [8]], 'mobile' => ['pattern' => '[35-7]\\d{7}', 'lengths' => [8]], 'tollFree' => ['pattern' => '800\\d{4}|(?:0080[01]|800)\\d{6}', 'lengths' => [7, 9, 11]], 'pager' => ['pattern' => '2[136]\\d{5}', 'lengths' => [7]]], 'formats' => [['pattern' => '(\\d{3})(\\d{4})', 'leadingDigits' => '2[136]|8', 'format' => '$1 $2', 'nationalRule' => null, 'intlFormat' => '$1 $2'], ['pattern' => '(\\d{4})(\\d{4})', 'leadingDigits' => '[3-7]', 'format' => '$1 $2', 'nationalRule' => null, 'intlFormat' => '$1 $2']]];
