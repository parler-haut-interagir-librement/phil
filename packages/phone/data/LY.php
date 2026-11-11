<?php

declare(strict_types=1);

// Generated from libphonenumber's metadata (Apache License 2.0) by Phil\Phone\Metadata\MetadataImporter.
return ['id' => 'LY', 'countryCode' => 218, 'internationalPrefix' => '00', 'preferredInternationalPrefix' => null, 'nationalPrefix' => '0', 'nationalPrefixForParsing' => '0', 'nationalPrefixTransformRule' => null, 'leadingDigits' => null, 'preferredExtnPrefix' => null, 'general' => ['pattern' => '[2-9]\\d{8}', 'lengths' => [9]], 'types' => ['fixedLine' => ['pattern' => '(?:2(?:0[56]|[1-6]\\d|7[124579]|8[124])|3(?:1\\d|2[2356])|4(?:[17]\\d|2[1-357]|5[2-4]|8[124])|5(?:[1347]\\d|2[1-469]|5[13-5]|8[1-4])|6(?:[1-479]\\d|5[2-57]|8[1-5])|7(?:[13]\\d|2[13-79])|8(?:[124]\\d|5[124]|84))\\d{6}', 'lengths' => [9]], 'mobile' => ['pattern' => '9[1-6]\\d{7}', 'lengths' => [9]]], 'formats' => [['pattern' => '(\\d{2})(\\d{7})', 'leadingDigits' => '[2-9]', 'format' => '$1-$2', 'nationalRule' => '0$1', 'intlFormat' => '$1-$2']]];
