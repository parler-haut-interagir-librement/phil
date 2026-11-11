<?php

declare(strict_types=1);

// Generated from libphonenumber's metadata (Apache License 2.0) by Phil\Phone\Metadata\MetadataImporter.
return ['id' => 'NE', 'countryCode' => 227, 'internationalPrefix' => '00', 'preferredInternationalPrefix' => null, 'nationalPrefix' => null, 'nationalPrefixForParsing' => null, 'nationalPrefixTransformRule' => null, 'leadingDigits' => null, 'preferredExtnPrefix' => null, 'general' => ['pattern' => '[027-9]\\d{7}', 'lengths' => [8]], 'types' => ['fixedLine' => ['pattern' => '2(?:0(?:20|3[1-8]|4[13-5]|5[14]|6[14578]|7[1-578])|1(?:4[145]|5[14]|6[14-68]|7[169]|88))\\d{4}', 'lengths' => [8]], 'mobile' => ['pattern' => '(?:23|7[0467]|[89]\\d)\\d{6}', 'lengths' => [8]], 'tollFree' => ['pattern' => '08\\d{6}', 'lengths' => [8]], 'premiumRate' => ['pattern' => '09\\d{6}', 'lengths' => [8]]], 'formats' => [['pattern' => '(\\d{2})(\\d{3})(\\d{3})', 'leadingDigits' => '08', 'format' => '$1 $2 $3', 'nationalRule' => null, 'intlFormat' => '$1 $2 $3'], ['pattern' => '(\\d{2})(\\d{2})(\\d{2})(\\d{2})', 'leadingDigits' => '[089]|2[013]|7[0467]', 'format' => '$1 $2 $3 $4', 'nationalRule' => null, 'intlFormat' => '$1 $2 $3 $4']]];
