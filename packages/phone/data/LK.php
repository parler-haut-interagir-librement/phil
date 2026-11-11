<?php

declare(strict_types=1);

// Generated from libphonenumber's metadata (Apache License 2.0) by Phil\Phone\Metadata\MetadataImporter.
return ['id' => 'LK', 'countryCode' => 94, 'internationalPrefix' => '00', 'preferredInternationalPrefix' => null, 'nationalPrefix' => '0', 'nationalPrefixForParsing' => '0', 'nationalPrefixTransformRule' => null, 'leadingDigits' => null, 'preferredExtnPrefix' => null, 'general' => ['pattern' => '[1-9]\\d{8}', 'lengths' => [9]], 'types' => ['fixedLine' => ['pattern' => '(?:12[2-9]|602|8[12]\\d|9(?:1\\d|22|9[245]))\\d{6}|(?:11|2[13-7]|3[1-8]|4[157]|5[12457]|6[35-7])[2-57]\\d{6}', 'lengths' => [9]], 'mobile' => ['pattern' => '7(?:[0-25-8]\\d|4[0-4])\\d{6}', 'lengths' => [9]], 'uan' => ['pattern' => '1973\\d{5}', 'lengths' => [9]]], 'formats' => [['pattern' => '(\\d{2})(\\d{3})(\\d{4})', 'leadingDigits' => '7', 'format' => '$1 $2 $3', 'nationalRule' => '0$1', 'intlFormat' => '$1 $2 $3'], ['pattern' => '(\\d{3})(\\d{3})(\\d{3})', 'leadingDigits' => '[1-689]', 'format' => '$1 $2 $3', 'nationalRule' => '0$1', 'intlFormat' => '$1 $2 $3']]];
