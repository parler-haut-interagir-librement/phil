<?php

declare(strict_types=1);

// Generated from libphonenumber's metadata (Apache License 2.0) by Phil\Phone\Metadata\MetadataImporter.
return ['id' => 'ML', 'countryCode' => 223, 'internationalPrefix' => '00', 'preferredInternationalPrefix' => null, 'nationalPrefix' => null, 'nationalPrefixForParsing' => null, 'nationalPrefixTransformRule' => null, 'leadingDigits' => null, 'preferredExtnPrefix' => null, 'general' => ['pattern' => '[24-9]\\d{7}', 'lengths' => [8]], 'types' => ['fixedLine' => ['pattern' => '2(?:07[0-8]|12[67])\\d{4}|(?:2(?:02|1[4-689])|4(?:0[0-4]|4[1-69]))\\d{5}', 'lengths' => [8]], 'mobile' => ['pattern' => '2(?:0(?:01|79)|17\\d)\\d{4}|(?:5[0-3]|[679]\\d|8[2-59])\\d{6}', 'lengths' => [8]], 'tollFree' => ['pattern' => '80\\d{6}', 'lengths' => [8]]], 'formats' => [['pattern' => '(\\d{4})', 'leadingDigits' => '67(?:0[09]|[59]9|77|8[89])|74(?:0[02]|44|55)', 'format' => '$1', 'nationalRule' => null, 'intlFormat' => null], ['pattern' => '(\\d{2})(\\d{2})(\\d{2})(\\d{2})', 'leadingDigits' => '[24-9]', 'format' => '$1 $2 $3 $4', 'nationalRule' => null, 'intlFormat' => '$1 $2 $3 $4']]];
