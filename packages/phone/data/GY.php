<?php

declare(strict_types=1);

// Generated from libphonenumber's metadata (Apache License 2.0) by Phil\Phone\Metadata\MetadataImporter.
return ['id' => 'GY', 'countryCode' => 592, 'internationalPrefix' => '001', 'preferredInternationalPrefix' => null, 'nationalPrefix' => null, 'nationalPrefixForParsing' => null, 'nationalPrefixTransformRule' => null, 'leadingDigits' => null, 'preferredExtnPrefix' => null, 'general' => ['pattern' => '(?:[2-8]\\d{3}|9008)\\d{3}', 'lengths' => [7]], 'types' => ['fixedLine' => ['pattern' => '(?:2(?:1[6-9]|2[0-35-9]|3[1-4]|5[3-9]|6\\d|7[0-79])|3(?:2[25-9]|3\\d)|4(?:4[0-24]|5[56])|5(?:0[0-6]|16)|77[1-57])\\d{4}', 'lengths' => [7]], 'mobile' => ['pattern' => '(?:51[01]|6\\d\\d|7(?:[0-6]\\d|70|8[0167]))\\d{4}', 'lengths' => [7]], 'tollFree' => ['pattern' => '(?:289|8(?:00|6[28]|88|99))\\d{4}', 'lengths' => [7]], 'premiumRate' => ['pattern' => '9008\\d{3}', 'lengths' => [7]], 'voip' => ['pattern' => '515\\d{4}', 'lengths' => [7]]], 'formats' => [['pattern' => '(\\d{3})(\\d{4})', 'leadingDigits' => '[2-9]', 'format' => '$1 $2', 'nationalRule' => null, 'intlFormat' => '$1 $2']]];
