<?php

declare(strict_types=1);

// Generated from commerceguys/addressing's data (MIT License) by Phil\Address\Data\DataImporter.
return ['country' => 'CN', 'format' => '%familyName %givenName
%organization
%addressLine1
%addressLine2
%addressLine3
%dependentLocality
%locality
%administrativeArea, %postalCode', 'localFormat' => '%postalCode
%administrativeArea%locality%dependentLocality
%addressLine1
%addressLine2
%addressLine3
%organization
%familyName %givenName', 'locale' => 'zh-Hans', 'required' => ['addressLine1', 'locality', 'administrativeArea', 'postalCode'], 'uppercase' => ['administrativeArea'], 'postalCodePattern' => '\\d{6}', 'postalCodePrefix' => null, 'administrativeAreaType' => 'province', 'localityType' => 'city', 'dependentLocalityType' => 'district', 'postalCodeType' => 'postal', 'subdivisionDepth' => 3, 'defaults' => []];
