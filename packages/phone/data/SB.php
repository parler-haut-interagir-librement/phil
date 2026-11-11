<?php

declare(strict_types=1);

// Generated from libphonenumber's metadata (Apache License 2.0) by Phil\Phone\Metadata\MetadataImporter.
return ['id' => 'SB', 'countryCode' => 677, 'internationalPrefix' => '0[01]', 'preferredInternationalPrefix' => null, 'nationalPrefix' => null, 'nationalPrefixForParsing' => null, 'nationalPrefixTransformRule' => null, 'leadingDigits' => null, 'preferredExtnPrefix' => null, 'general' => ['pattern' => '[6-9]\\d{6}|[1-6]\\d{4}', 'lengths' => [5, 7]], 'types' => ['fixedLine' => ['pattern' => '(?:1[4-79]|[23]\\d|4[0-2]|5[03]|6[0-37])\\d{3}', 'lengths' => [5]], 'mobile' => ['pattern' => '(?:48|99[0-8]\\d)\\d{3}|(?:6[89]|7[1-9]|8[4-9]|9[0-8])\\d{5}', 'lengths' => [5, 7]], 'tollFree' => ['pattern' => '1[38]\\d{3}', 'lengths' => [5]], 'voip' => ['pattern' => '5[12]\\d{3}', 'lengths' => [5]]], 'formats' => [['pattern' => '(\\d{2})(\\d{5})', 'leadingDigits' => '6[89]|7|8[4-9]|9(?:[0-8]|9[0-8])', 'format' => '$1 $2', 'nationalRule' => null, 'intlFormat' => '$1 $2']]];
