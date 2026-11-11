<?php

declare(strict_types=1);

// Generated from libphonenumber's metadata (Apache License 2.0) by Phil\Phone\Metadata\MetadataImporter.
return ['id' => 'FJ', 'countryCode' => 679, 'internationalPrefix' => '0(?:0|52)', 'preferredInternationalPrefix' => '00', 'nationalPrefix' => null, 'nationalPrefixForParsing' => null, 'nationalPrefixTransformRule' => null, 'leadingDigits' => null, 'preferredExtnPrefix' => null, 'general' => ['pattern' => '45\\d{5}|(?:0800\\d|[235-9])\\d{6}', 'lengths' => [7, 11]], 'types' => ['fixedLine' => ['pattern' => '603\\d{4}|(?:3[0-6]|6[25-7]|8[58])\\d{5}', 'lengths' => [7]], 'mobile' => ['pattern' => '(?:[279]\\d|45|5[01568]|8[034679])\\d{5}', 'lengths' => [7]], 'tollFree' => ['pattern' => '0800\\d{7}', 'lengths' => [11]]], 'formats' => [['pattern' => '(\\d{3})(\\d{4})', 'leadingDigits' => '[235-9]|45', 'format' => '$1 $2', 'nationalRule' => null, 'intlFormat' => '$1 $2'], ['pattern' => '(\\d{4})(\\d{3})(\\d{4})', 'leadingDigits' => '0', 'format' => '$1 $2 $3', 'nationalRule' => null, 'intlFormat' => '$1 $2 $3']]];
