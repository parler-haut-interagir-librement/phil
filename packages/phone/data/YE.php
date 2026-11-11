<?php

declare(strict_types=1);

// Generated from libphonenumber's metadata (Apache License 2.0) by Phil\Phone\Metadata\MetadataImporter.
return ['id' => 'YE', 'countryCode' => 967, 'internationalPrefix' => '00', 'preferredInternationalPrefix' => null, 'nationalPrefix' => '0', 'nationalPrefixForParsing' => '0', 'nationalPrefixTransformRule' => null, 'leadingDigits' => null, 'preferredExtnPrefix' => null, 'general' => ['pattern' => '(?:1|7\\d)\\d{7}|[1-7]\\d{6}', 'lengths' => [7, 8, 9]], 'types' => ['fixedLine' => ['pattern' => '78[0-7]\\d{4}|17\\d{6}|(?:[12][2-68]|3[2358]|4[2-58]|5[2-6]|6[3-58]|7[24-6])\\d{5}', 'lengths' => [7, 8]], 'mobile' => ['pattern' => '7[01378]\\d{7}', 'lengths' => [9]]], 'formats' => [['pattern' => '(\\d)(\\d{3})(\\d{3,4})', 'leadingDigits' => '[1-6]|7(?:[24-6]|8[0-7])', 'format' => '$1 $2 $3', 'nationalRule' => '0$1', 'intlFormat' => '$1 $2 $3'], ['pattern' => '(\\d{3})(\\d{3})(\\d{3})', 'leadingDigits' => '7', 'format' => '$1 $2 $3', 'nationalRule' => '0$1', 'intlFormat' => '$1 $2 $3']]];
