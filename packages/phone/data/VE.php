<?php

declare(strict_types=1);

// Generated from libphonenumber's metadata (Apache License 2.0) by Phil\Phone\Metadata\MetadataImporter.
return ['id' => 'VE', 'countryCode' => 58, 'internationalPrefix' => '00', 'preferredInternationalPrefix' => null, 'nationalPrefix' => '0', 'nationalPrefixForParsing' => '0', 'nationalPrefixTransformRule' => null, 'leadingDigits' => null, 'preferredExtnPrefix' => null, 'general' => ['pattern' => '[68]00\\d{7}|(?:[24]\\d|[59]0)\\d{8}', 'lengths' => [10]], 'types' => ['fixedLine' => ['pattern' => '(?:2(?:12|3[457-9]|[467]\\d|[58][1-9]|9[1-6])|[4-6]00)\\d{7}', 'lengths' => [10]], 'mobile' => ['pattern' => '4(?:1[24-8]|2[246])\\d{7}', 'lengths' => [10]], 'tollFree' => ['pattern' => '800\\d{7}', 'lengths' => [10]], 'premiumRate' => ['pattern' => '90[01]\\d{7}', 'lengths' => [10]], 'uan' => ['pattern' => '501\\d{7}', 'lengths' => [10]]], 'formats' => [['pattern' => '(\\d{3})(\\d{7})', 'leadingDigits' => '[24-689]', 'format' => '$1-$2', 'nationalRule' => '0$1', 'intlFormat' => '$1-$2']]];
