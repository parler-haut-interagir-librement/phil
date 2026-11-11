<?php

declare(strict_types=1);

// Generated from libphonenumber's metadata (Apache License 2.0) by Phil\Phone\Metadata\MetadataImporter.
return ['id' => 'PW', 'countryCode' => 680, 'internationalPrefix' => '01[12]', 'preferredInternationalPrefix' => null, 'nationalPrefix' => null, 'nationalPrefixForParsing' => null, 'nationalPrefixTransformRule' => null, 'leadingDigits' => null, 'preferredExtnPrefix' => null, 'general' => ['pattern' => '(?:[24-8]\\d\\d|345|900)\\d{4}', 'lengths' => [7]], 'types' => ['fixedLine' => ['pattern' => '(?:2(?:55|77)|345|488|5(?:35|44|87)|6(?:22|54|79)|7(?:33|47)|8(?:24|55|76)|900)\\d{4}', 'lengths' => [7]], 'mobile' => ['pattern' => '(?:(?:46|83)[0-5]|(?:6[2-4689]|78)0)\\d{4}|(?:45|77|88)\\d{5}', 'lengths' => [7]]], 'formats' => [['pattern' => '(\\d{3})(\\d{4})', 'leadingDigits' => '[2-9]', 'format' => '$1 $2', 'nationalRule' => null, 'intlFormat' => '$1 $2']]];
