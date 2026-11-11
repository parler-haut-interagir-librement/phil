<?php

declare(strict_types=1);

// Generated from libphonenumber's metadata (Apache License 2.0) by Phil\Phone\Metadata\MetadataImporter.
return ['id' => 'TM', 'countryCode' => 993, 'internationalPrefix' => '810', 'preferredInternationalPrefix' => '8~10', 'nationalPrefix' => '8', 'nationalPrefixForParsing' => '8', 'nationalPrefixTransformRule' => null, 'leadingDigits' => null, 'preferredExtnPrefix' => null, 'general' => ['pattern' => '[1-7]\\d{7}', 'lengths' => [8]], 'types' => ['fixedLine' => ['pattern' => '(?:1(?:2\\d|3[1-9])|2(?:22|4[0-35-8])|3(?:22|4[03-9])|4(?:22|3[128]|4\\d|6[15])|5(?:22|5[7-9]|6[014-689]))\\d{5}', 'lengths' => [8]], 'mobile' => ['pattern' => '(?:6\\d|7[12])\\d{6}', 'lengths' => [8]]], 'formats' => [['pattern' => '(\\d{2})(\\d{2})(\\d{2})(\\d{2})', 'leadingDigits' => '12', 'format' => '$1 $2-$3-$4', 'nationalRule' => '(8 $1)', 'intlFormat' => '$1 $2-$3-$4'], ['pattern' => '(\\d{3})(\\d)(\\d{2})(\\d{2})', 'leadingDigits' => '[1-5]', 'format' => '$1 $2-$3-$4', 'nationalRule' => '(8 $1)', 'intlFormat' => '$1 $2-$3-$4'], ['pattern' => '(\\d{2})(\\d{6})', 'leadingDigits' => '[67]', 'format' => '$1 $2', 'nationalRule' => '8 $1', 'intlFormat' => '$1 $2']]];
