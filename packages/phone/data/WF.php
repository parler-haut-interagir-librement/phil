<?php

declare(strict_types=1);

// Generated from libphonenumber's metadata (Apache License 2.0) by Phil\Phone\Metadata\MetadataImporter.
return ['id' => 'WF', 'countryCode' => 681, 'internationalPrefix' => '00', 'preferredInternationalPrefix' => null, 'nationalPrefix' => null, 'nationalPrefixForParsing' => null, 'nationalPrefixTransformRule' => null, 'leadingDigits' => null, 'preferredExtnPrefix' => null, 'general' => ['pattern' => '(?:40|72|8\\d{4})\\d{4}|[89]\\d{5}', 'lengths' => [6, 9]], 'types' => ['fixedLine' => ['pattern' => '72\\d{4}', 'lengths' => [6]], 'mobile' => ['pattern' => '(?:72|8[23])\\d{4}', 'lengths' => [6]], 'tollFree' => ['pattern' => '80[0-5]\\d{6}', 'lengths' => [9]], 'voip' => ['pattern' => '9[23]\\d{4}', 'lengths' => [6]], 'voicemail' => ['pattern' => '[48]0\\d{4}', 'lengths' => [6]]], 'formats' => [['pattern' => '(\\d{2})(\\d{2})(\\d{2})', 'leadingDigits' => '[47-9]', 'format' => '$1 $2 $3', 'nationalRule' => null, 'intlFormat' => '$1 $2 $3'], ['pattern' => '(\\d{3})(\\d{2})(\\d{2})(\\d{2})', 'leadingDigits' => '8', 'format' => '$1 $2 $3 $4', 'nationalRule' => null, 'intlFormat' => '$1 $2 $3 $4']]];
