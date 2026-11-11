<?php

declare(strict_types=1);

// Generated from libphonenumber's metadata (Apache License 2.0) by Phil\Phone\Metadata\MetadataImporter.
return ['id' => 'BM', 'countryCode' => 1, 'internationalPrefix' => '011', 'preferredInternationalPrefix' => null, 'nationalPrefix' => '1', 'nationalPrefixForParsing' => '([2-9]\\d{6})$|1', 'nationalPrefixTransformRule' => '441$1', 'leadingDigits' => '441', 'preferredExtnPrefix' => null, 'general' => ['pattern' => '(?:441|[58]\\d\\d|900)\\d{7}', 'lengths' => [10]], 'types' => ['fixedLine' => ['pattern' => '441(?:[46]\\d\\d|5(?:4\\d|60|89))\\d{4}', 'lengths' => [10]], 'mobile' => ['pattern' => '441(?:[2378]\\d|5[0-39]|9[02])\\d{5}', 'lengths' => [10]], 'tollFree' => ['pattern' => '8(?:00|33|44|55|66|77|88)[2-9]\\d{6}', 'lengths' => [10]], 'premiumRate' => ['pattern' => '900[2-9]\\d{6}', 'lengths' => [10]], 'personalNumber' => ['pattern' => '52(?:3(?:[2-46-9][02-9]\\d|5(?:[02-46-9]\\d|5[0-46-9]))|4(?:[2-478][02-9]\\d|5(?:[034]\\d|2[024-9]|5[0-46-9])|6(?:0[1-9]|[2-9]\\d)|9(?:[05-9]\\d|2[0-5]|49)))\\d{4}|52[34][2-9]1[02-9]\\d{4}|5(?:00|2[125-9]|3[23]|44|66|77|88)[2-9]\\d{6}', 'lengths' => [10]]], 'formats' => []];
