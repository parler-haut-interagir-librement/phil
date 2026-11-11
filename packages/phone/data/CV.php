<?php

declare(strict_types=1);

// Generated from libphonenumber's metadata (Apache License 2.0) by Phil\Phone\Metadata\MetadataImporter.
return ['id' => 'CV', 'countryCode' => 238, 'internationalPrefix' => '0', 'preferredInternationalPrefix' => null, 'nationalPrefix' => null, 'nationalPrefixForParsing' => null, 'nationalPrefixTransformRule' => null, 'leadingDigits' => null, 'preferredExtnPrefix' => null, 'general' => ['pattern' => '(?:[2-59]\\d\\d|800)\\d{4}', 'lengths' => [7]], 'types' => ['fixedLine' => ['pattern' => '2(?:2[1-7]|3[0-8]|4[12]|5[1256]|6\\d|7[1-3]|8[1-5])\\d{4}', 'lengths' => [7]], 'mobile' => ['pattern' => '(?:36|5[1-37-9]|9\\d)\\d{5}', 'lengths' => [7]], 'tollFree' => ['pattern' => '800\\d{4}', 'lengths' => [7]], 'voip' => ['pattern' => '(?:3[3-5]|4[356])\\d{5}', 'lengths' => [7]]], 'formats' => [['pattern' => '(\\d{3})(\\d{2})(\\d{2})', 'leadingDigits' => '[2-589]', 'format' => '$1 $2 $3', 'nationalRule' => null, 'intlFormat' => '$1 $2 $3']]];
