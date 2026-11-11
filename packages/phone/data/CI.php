<?php

declare(strict_types=1);

// Generated from libphonenumber's metadata (Apache License 2.0) by Phil\Phone\Metadata\MetadataImporter.
return ['id' => 'CI', 'countryCode' => 225, 'internationalPrefix' => '00', 'preferredInternationalPrefix' => null, 'nationalPrefix' => null, 'nationalPrefixForParsing' => null, 'nationalPrefixTransformRule' => null, 'leadingDigits' => null, 'preferredExtnPrefix' => null, 'general' => ['pattern' => '[02]\\d{9}', 'lengths' => [10]], 'types' => ['fixedLine' => ['pattern' => '2(?:[15]\\d{3}|7(?:2(?:0[23]|1[2357]|2[245]|3[45]|4[3-5])|3(?:06|1[69]|[2-6]7)))\\d{5}', 'lengths' => [10]], 'mobile' => ['pattern' => '0[157]\\d{8}', 'lengths' => [10]]], 'formats' => [['pattern' => '(\\d{2})(\\d{2})(\\d)(\\d{5})', 'leadingDigits' => '2', 'format' => '$1 $2 $3 $4', 'nationalRule' => null, 'intlFormat' => '$1 $2 $3 $4'], ['pattern' => '(\\d{2})(\\d{2})(\\d{2})(\\d{4})', 'leadingDigits' => '0', 'format' => '$1 $2 $3 $4', 'nationalRule' => null, 'intlFormat' => '$1 $2 $3 $4']]];
