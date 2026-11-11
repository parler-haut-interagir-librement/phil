<?php

declare(strict_types=1);

// Generated from libphonenumber's metadata (Apache License 2.0) by Phil\Phone\Metadata\MetadataImporter.
return ['id' => 'BH', 'countryCode' => 973, 'internationalPrefix' => '00', 'preferredInternationalPrefix' => null, 'nationalPrefix' => null, 'nationalPrefixForParsing' => null, 'nationalPrefixTransformRule' => null, 'leadingDigits' => null, 'preferredExtnPrefix' => null, 'general' => ['pattern' => '[136-9]\\d{7}', 'lengths' => [8]], 'types' => ['fixedLine' => ['pattern' => '(?:1(?:3[1356]|6[0156]|7\\d)\\d|6(?:1[16]\\d|500|6(?:0\\d|3[12]|44|55|7[7-9]|88)|9[69][69])|7(?:[07]\\d\\d|1(?:11|78)))\\d{4}', 'lengths' => [8]], 'mobile' => ['pattern' => '(?:3(?:[0-79]\\d|8[0-57-9])\\d|6(?:3(?:00|33|6[16])|441|6(?:3[03-9]|[69]\\d|7[0-689])))\\d{4}', 'lengths' => [8]], 'tollFree' => ['pattern' => '8[02369]\\d{6}', 'lengths' => [8]], 'premiumRate' => ['pattern' => '(?:87|9[0-8])\\d{6}', 'lengths' => [8]], 'sharedCost' => ['pattern' => '84\\d{6}', 'lengths' => [8]]], 'formats' => [['pattern' => '(\\d{4})(\\d{4})', 'leadingDigits' => '[13679]|8[02-4679]', 'format' => '$1 $2', 'nationalRule' => null, 'intlFormat' => '$1 $2']]];
