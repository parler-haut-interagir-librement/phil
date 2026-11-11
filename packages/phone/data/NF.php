<?php

declare(strict_types=1);

// Generated from libphonenumber's metadata (Apache License 2.0) by Phil\Phone\Metadata\MetadataImporter.
return ['id' => 'NF', 'countryCode' => 672, 'internationalPrefix' => '00', 'preferredInternationalPrefix' => null, 'nationalPrefix' => null, 'nationalPrefixForParsing' => '([0-258]\\d{4})$', 'nationalPrefixTransformRule' => '3$1', 'leadingDigits' => null, 'preferredExtnPrefix' => null, 'general' => ['pattern' => '[13]\\d{5}', 'lengths' => [6]], 'types' => ['fixedLine' => ['pattern' => '(?:1(?:06|17|28|39)|3[0-2]\\d)\\d{3}', 'lengths' => [6]], 'mobile' => ['pattern' => '(?:14|3[58])\\d{4}', 'lengths' => [6]]], 'formats' => [['pattern' => '(\\d{2})(\\d{4})', 'leadingDigits' => '1[0-3]', 'format' => '$1 $2', 'nationalRule' => null, 'intlFormat' => '$1 $2'], ['pattern' => '(\\d)(\\d{5})', 'leadingDigits' => '[13]', 'format' => '$1 $2', 'nationalRule' => null, 'intlFormat' => '$1 $2']]];
