<?php

declare(strict_types=1);

// Generated from libphonenumber's metadata (Apache License 2.0) by Phil\Phone\Metadata\MetadataImporter.
return ['id' => 'ME', 'countryCode' => 382, 'internationalPrefix' => '00', 'preferredInternationalPrefix' => null, 'nationalPrefix' => '0', 'nationalPrefixForParsing' => '0', 'nationalPrefixTransformRule' => null, 'leadingDigits' => null, 'preferredExtnPrefix' => null, 'general' => ['pattern' => '(?:20|[3-79]\\d)\\d{6}|80\\d{6,7}', 'lengths' => [8, 9]], 'types' => ['fixedLine' => ['pattern' => '(?:20[2-8]|3(?:[0-2][2-7]|3[24-7])|4(?:0[2-467]|1[2467])|5(?:0[2467]|1[24-7]|2[2-467]))\\d{5}', 'lengths' => [8]], 'mobile' => ['pattern' => '6(?:[07-9]\\d|3[024]|6[0-25])\\d{5}', 'lengths' => [8]], 'tollFree' => ['pattern' => '80(?:[0-2578]|9\\d)\\d{5}', 'lengths' => [8, 9]], 'premiumRate' => ['pattern' => '9(?:4[1568]|5[178])\\d{5}', 'lengths' => [8]], 'voip' => ['pattern' => '78[1-49]\\d{5}', 'lengths' => [8]], 'uan' => ['pattern' => '77[1-9]\\d{5}', 'lengths' => [8]]], 'formats' => [['pattern' => '(\\d{2})(\\d{3})(\\d{3,4})', 'leadingDigits' => '[2-9]', 'format' => '$1 $2 $3', 'nationalRule' => '0$1', 'intlFormat' => '$1 $2 $3']]];
