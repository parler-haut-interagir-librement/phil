<?php

declare(strict_types=1);

// Generated from libphonenumber's metadata (Apache License 2.0) by Phil\Phone\Metadata\MetadataImporter.
return ['id' => 'IQ', 'countryCode' => 964, 'internationalPrefix' => '00', 'preferredInternationalPrefix' => null, 'nationalPrefix' => '0', 'nationalPrefixForParsing' => '0', 'nationalPrefixTransformRule' => null, 'leadingDigits' => null, 'preferredExtnPrefix' => null, 'general' => ['pattern' => '(?:1|7\\d\\d)\\d{7}|[2-6]\\d{7,8}', 'lengths' => [8, 9, 10]], 'types' => ['fixedLine' => ['pattern' => '1\\d{7}|(?:2[13-5]|3[02367]|4[023]|5[03]|6[026])\\d{6,7}', 'lengths' => [8, 9]], 'mobile' => ['pattern' => '7[3-9]\\d{8}', 'lengths' => [10]]], 'formats' => [['pattern' => '(\\d)(\\d{3})(\\d{4})', 'leadingDigits' => '1', 'format' => '$1 $2 $3', 'nationalRule' => '0$1', 'intlFormat' => '$1 $2 $3'], ['pattern' => '(\\d{2})(\\d{3})(\\d{3,4})', 'leadingDigits' => '[2-6]', 'format' => '$1 $2 $3', 'nationalRule' => '0$1', 'intlFormat' => '$1 $2 $3'], ['pattern' => '(\\d{3})(\\d{3})(\\d{4})', 'leadingDigits' => '7', 'format' => '$1 $2 $3', 'nationalRule' => '0$1', 'intlFormat' => '$1 $2 $3']]];
