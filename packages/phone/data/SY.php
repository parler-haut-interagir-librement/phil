<?php

declare(strict_types=1);

// Generated from libphonenumber's metadata (Apache License 2.0) by Phil\Phone\Metadata\MetadataImporter.
return ['id' => 'SY', 'countryCode' => 963, 'internationalPrefix' => '00', 'preferredInternationalPrefix' => null, 'nationalPrefix' => '0', 'nationalPrefixForParsing' => '0', 'nationalPrefixTransformRule' => null, 'leadingDigits' => null, 'preferredExtnPrefix' => null, 'general' => ['pattern' => '[1-359]\\d{8}|[1-5]\\d{7}', 'lengths' => [8, 9]], 'types' => ['fixedLine' => ['pattern' => '(?:1(?:[1478]\\d|[2356])|21\\d|3(?:[13]\\d|4)|4[134]|5[1-3])\\d{6}|2[1-5]\\d{6}', 'lengths' => [8, 9]], 'mobile' => ['pattern' => '(?:50|9[03-689])\\d{7}', 'lengths' => [9]]], 'formats' => [['pattern' => '(\\d{2})(\\d{3})(\\d{3,4})', 'leadingDigits' => '[1-4]|5[1-3]', 'format' => '$1 $2 $3', 'nationalRule' => '0$1', 'intlFormat' => '$1 $2 $3'], ['pattern' => '(\\d{3})(\\d{3})(\\d{3})', 'leadingDigits' => '[59]', 'format' => '$1 $2 $3', 'nationalRule' => '0$1', 'intlFormat' => '$1 $2 $3']]];
