<?php

declare(strict_types=1);

// Generated from commerceguys/addressing's data (MIT License) by Phil\Address\Data\DataImporter.
return ['country' => 'TH', 'format' => '%givenName %familyName
%organization
%addressLine1
%addressLine2
%addressLine3
%dependentLocality, %locality
%administrativeArea %postalCode', 'localFormat' => '%givenName %familyName
%organization
%addressLine1
%addressLine2
%addressLine3
%dependentLocality %locality
%administrativeArea %postalCode', 'locale' => 'th', 'required' => ['addressLine1', 'locality', 'administrativeArea'], 'uppercase' => ['administrativeArea'], 'postalCodePattern' => '\\d{5}', 'postalCodePrefix' => null, 'administrativeAreaType' => 'province', 'localityType' => 'city', 'dependentLocalityType' => 'suburb', 'postalCodeType' => 'postal', 'subdivisionDepth' => 1, 'defaults' => []];
