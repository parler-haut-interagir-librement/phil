<?php

declare(strict_types=1);

// Generated from libphonenumber's metadata (Apache License 2.0) by Phil\Phone\Metadata\MetadataImporter.
return ['id' => 'CM', 'countryCode' => 237, 'internationalPrefix' => '00', 'preferredInternationalPrefix' => null, 'nationalPrefix' => null, 'nationalPrefixForParsing' => null, 'nationalPrefixTransformRule' => null, 'leadingDigits' => null, 'preferredExtnPrefix' => null, 'general' => ['pattern' => '[26]\\d{8}|88\\d{6,7}', 'lengths' => [8, 9]], 'types' => ['fixedLine' => ['pattern' => '2(?:22|33)\\d{6}', 'lengths' => [9]], 'mobile' => ['pattern' => '(?:24[23]|6(?:[25-9]\\d|4[0-2]))\\d{6}', 'lengths' => [9]], 'tollFree' => ['pattern' => '88\\d{6,7}', 'lengths' => [8, 9]]], 'formats' => [['pattern' => '(\\d{2})(\\d{2})(\\d{2})(\\d{2})', 'leadingDigits' => '88', 'format' => '$1 $2 $3 $4', 'nationalRule' => null, 'intlFormat' => '$1 $2 $3 $4'], ['pattern' => '(\\d)(\\d{2})(\\d{2})(\\d{2})(\\d{2})', 'leadingDigits' => '[26]|88', 'format' => '$1 $2 $3 $4 $5', 'nationalRule' => null, 'intlFormat' => '$1 $2 $3 $4 $5']]];
