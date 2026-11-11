<?php

declare(strict_types=1);

// Generated from libphonenumber's metadata (Apache License 2.0) by Phil\Phone\Metadata\MetadataImporter.
return ['id' => 'ZM', 'countryCode' => 260, 'internationalPrefix' => '00', 'preferredInternationalPrefix' => null, 'nationalPrefix' => '0', 'nationalPrefixForParsing' => '0', 'nationalPrefixTransformRule' => null, 'leadingDigits' => null, 'preferredExtnPrefix' => null, 'general' => ['pattern' => '800\\d{6}|(?:21|[579]\\d|63)\\d{7}', 'lengths' => [9]], 'types' => ['fixedLine' => ['pattern' => '21[1-8]\\d{6}', 'lengths' => [9]], 'mobile' => ['pattern' => '(?:[59][5-8]|7[5-9])\\d{7}', 'lengths' => [9]], 'tollFree' => ['pattern' => '800\\d{6}', 'lengths' => [9]], 'voip' => ['pattern' => '63\\d{7}', 'lengths' => [9]]], 'formats' => [['pattern' => '(\\d{3})(\\d{3})', 'leadingDigits' => '[1-9]', 'format' => '$1 $2', 'nationalRule' => null, 'intlFormat' => null], ['pattern' => '(\\d{3})(\\d{3})(\\d{3})', 'leadingDigits' => '[28]', 'format' => '$1 $2 $3', 'nationalRule' => '0$1', 'intlFormat' => '$1 $2 $3'], ['pattern' => '(\\d{2})(\\d{7})', 'leadingDigits' => '[579]', 'format' => '$1 $2', 'nationalRule' => '0$1', 'intlFormat' => '$1 $2']]];
