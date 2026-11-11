<?php

declare(strict_types=1);

// Generated from commerceguys/addressing's data (MIT License) by Phil\Address\Data\DataImporter.
return ['country' => 'TW', 'format' => '%familyName %givenName
%organization
%addressLine1
%addressLine2
%addressLine3
%locality, %administrativeArea %postalCode', 'localFormat' => '%postalCode
%administrativeArea%locality
%addressLine1
%addressLine2
%addressLine3
%organization
%familyName %givenName', 'locale' => 'zh-Hant', 'required' => ['addressLine1', 'locality', 'administrativeArea', 'postalCode'], 'uppercase' => ['locality'], 'postalCodePattern' => '\\d{3}(?:\\d{2,3})?', 'postalCodePrefix' => null, 'administrativeAreaType' => 'county', 'localityType' => 'city', 'dependentLocalityType' => 'suburb', 'postalCodeType' => 'postal', 'subdivisionDepth' => 2, 'defaults' => []];
