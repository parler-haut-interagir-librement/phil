<?php

declare(strict_types=1);

// Generated from libphonenumber's metadata (Apache License 2.0) by Phil\Phone\Metadata\MetadataImporter.
return ['id' => 'MW', 'countryCode' => 265, 'internationalPrefix' => '00', 'preferredInternationalPrefix' => null, 'nationalPrefix' => '0', 'nationalPrefixForParsing' => '0', 'nationalPrefixTransformRule' => null, 'leadingDigits' => null, 'preferredExtnPrefix' => null, 'general' => ['pattern' => '(?:[1289]\\d|31|77)\\d{7}|1\\d{6}', 'lengths' => [7, 9]], 'types' => ['fixedLine' => ['pattern' => '1[2-9]\\d{5}', 'lengths' => [7]], 'mobile' => ['pattern' => '111\\d{6}|(?:2[12]|31|77|[89][89])\\d{7}', 'lengths' => [9]]], 'formats' => [['pattern' => '(\\d)(\\d{3})(\\d{3})', 'leadingDigits' => '1[2-9]', 'format' => '$1 $2 $3', 'nationalRule' => '0$1', 'intlFormat' => '$1 $2 $3'], ['pattern' => '(\\d{3})(\\d{2})(\\d{2})(\\d{2})', 'leadingDigits' => '[1-37-9]', 'format' => '$1 $2 $3 $4', 'nationalRule' => '0$1', 'intlFormat' => '$1 $2 $3 $4']]];
