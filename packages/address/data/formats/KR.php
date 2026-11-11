<?php

declare(strict_types=1);

// Generated from commerceguys/addressing's data (MIT License) by Phil\Address\Data\DataImporter.
return ['country' => 'KR', 'format' => '%givenName %familyName
%organization
%addressLine1
%addressLine2
%addressLine3
%dependentLocality
%locality
%administrativeArea
%postalCode', 'localFormat' => '%administrativeArea %locality%dependentLocality
%addressLine1
%addressLine2
%addressLine3
%organization
%givenName %familyName
%postalCode', 'locale' => 'ko', 'required' => ['addressLine1', 'locality', 'administrativeArea', 'postalCode'], 'uppercase' => ['postalCode'], 'postalCodePattern' => '\\d{5}', 'postalCodePrefix' => null, 'administrativeAreaType' => 'do_si', 'localityType' => 'city', 'dependentLocalityType' => 'district', 'postalCodeType' => 'postal', 'subdivisionDepth' => 3, 'defaults' => []];
