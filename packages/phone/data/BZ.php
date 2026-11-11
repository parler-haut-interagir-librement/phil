<?php

declare(strict_types=1);

// Generated from libphonenumber's metadata (Apache License 2.0) by Phil\Phone\Metadata\MetadataImporter.
return ['id' => 'BZ', 'countryCode' => 501, 'internationalPrefix' => '00', 'preferredInternationalPrefix' => null, 'nationalPrefix' => null, 'nationalPrefixForParsing' => null, 'nationalPrefixTransformRule' => null, 'leadingDigits' => null, 'preferredExtnPrefix' => null, 'general' => ['pattern' => '(?:0800\\d|[2-8])\\d{6}', 'lengths' => [7, 11]], 'types' => ['fixedLine' => ['pattern' => '(?:2(?:[02]\\d|36|[68]0)|[3-58](?:[02]\\d|[68]0)|7(?:[02]\\d|32|[68]0))\\d{4}', 'lengths' => [7]], 'mobile' => ['pattern' => '6[0-35-7]\\d{5}', 'lengths' => [7]], 'tollFree' => ['pattern' => '0800\\d{7}', 'lengths' => [11]]], 'formats' => [['pattern' => '(\\d{3})(\\d{4})', 'leadingDigits' => '[2-8]', 'format' => '$1-$2', 'nationalRule' => null, 'intlFormat' => '$1-$2'], ['pattern' => '(\\d)(\\d{3})(\\d{4})(\\d{3})', 'leadingDigits' => '0', 'format' => '$1-$2-$3-$4', 'nationalRule' => null, 'intlFormat' => '$1-$2-$3-$4']]];
