<?php

declare(strict_types=1);

// Generated from libphonenumber's metadata (Apache License 2.0) by Phil\Phone\Metadata\MetadataImporter.
return ['id' => 'BT', 'countryCode' => 975, 'internationalPrefix' => '00', 'preferredInternationalPrefix' => null, 'nationalPrefix' => null, 'nationalPrefixForParsing' => null, 'nationalPrefixTransformRule' => null, 'leadingDigits' => null, 'preferredExtnPrefix' => null, 'general' => ['pattern' => '[178]\\d{7}|[2-8]\\d{6}', 'lengths' => [7, 8]], 'types' => ['fixedLine' => ['pattern' => '(?:2[3-6]|[34][5-7]|5[236]|6[2-46]|7[246]|8[2-4])\\d{5}', 'lengths' => [7]], 'mobile' => ['pattern' => '(?:1[67]|[78]7)\\d{6}', 'lengths' => [8]]], 'formats' => [['pattern' => '(\\d{3})(\\d{3})', 'leadingDigits' => '[2-7]', 'format' => '$1 $2', 'nationalRule' => null, 'intlFormat' => null], ['pattern' => '(\\d)(\\d{3})(\\d{3})', 'leadingDigits' => '[2-6]|7[246]|8[2-4]', 'format' => '$1 $2 $3', 'nationalRule' => null, 'intlFormat' => '$1 $2 $3'], ['pattern' => '(\\d{2})(\\d{2})(\\d{2})(\\d{2})', 'leadingDigits' => '1[67]|[78]', 'format' => '$1 $2 $3 $4', 'nationalRule' => null, 'intlFormat' => '$1 $2 $3 $4']]];
