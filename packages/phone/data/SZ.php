<?php

declare(strict_types=1);

// Generated from libphonenumber's metadata (Apache License 2.0) by Phil\Phone\Metadata\MetadataImporter.
return ['id' => 'SZ', 'countryCode' => 268, 'internationalPrefix' => '00', 'preferredInternationalPrefix' => null, 'nationalPrefix' => null, 'nationalPrefixForParsing' => null, 'nationalPrefixTransformRule' => null, 'leadingDigits' => null, 'preferredExtnPrefix' => null, 'general' => ['pattern' => '0800\\d{4}|(?:[237]\\d|900)\\d{6}', 'lengths' => [8, 9]], 'types' => ['fixedLine' => ['pattern' => '[23][2-5]\\d{6}', 'lengths' => [8]], 'mobile' => ['pattern' => '7[5-9]\\d{6}', 'lengths' => [8]], 'tollFree' => ['pattern' => '0800\\d{4}', 'lengths' => [8]], 'premiumRate' => ['pattern' => '900\\d{6}', 'lengths' => [9]], 'voip' => ['pattern' => '70\\d{6}', 'lengths' => [8]]], 'formats' => [['pattern' => '(\\d{4})(\\d{4})', 'leadingDigits' => '[0237]', 'format' => '$1 $2', 'nationalRule' => null, 'intlFormat' => '$1 $2'], ['pattern' => '(\\d{5})(\\d{4})', 'leadingDigits' => '9', 'format' => '$1 $2', 'nationalRule' => null, 'intlFormat' => '$1 $2']]];
