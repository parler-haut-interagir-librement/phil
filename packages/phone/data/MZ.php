<?php

declare(strict_types=1);

// Generated from libphonenumber's metadata (Apache License 2.0) by Phil\Phone\Metadata\MetadataImporter.
return ['id' => 'MZ', 'countryCode' => 258, 'internationalPrefix' => '00', 'preferredInternationalPrefix' => null, 'nationalPrefix' => null, 'nationalPrefixForParsing' => null, 'nationalPrefixTransformRule' => null, 'leadingDigits' => null, 'preferredExtnPrefix' => null, 'general' => ['pattern' => '(?:2|8\\d)\\d{7}', 'lengths' => [8, 9]], 'types' => ['fixedLine' => ['pattern' => '2(?:[1346]\\d|5[0-2]|[78][12]|93)\\d{5}', 'lengths' => [8]], 'mobile' => ['pattern' => '8[2-9]\\d{7}', 'lengths' => [9]], 'tollFree' => ['pattern' => '800\\d{6}', 'lengths' => [9]]], 'formats' => [['pattern' => '(\\d{2})(\\d{3})(\\d{3,4})', 'leadingDigits' => '2|8[2-9]', 'format' => '$1 $2 $3', 'nationalRule' => null, 'intlFormat' => '$1 $2 $3'], ['pattern' => '(\\d{3})(\\d{3})(\\d{3})', 'leadingDigits' => '8', 'format' => '$1 $2 $3', 'nationalRule' => null, 'intlFormat' => '$1 $2 $3']]];
