<?php

declare(strict_types=1);

// Generated from commerceguys/addressing's data (MIT License) by Phil\Address\Data\DataImporter.
return ['country' => 'MY', 'format' => '%givenName %familyName
%organization
%addressLine1
%addressLine2
%addressLine3
%dependentLocality
%postalCode %locality
%administrativeArea', 'localFormat' => null, 'locale' => null, 'required' => ['addressLine1', 'locality', 'administrativeArea', 'postalCode'], 'uppercase' => ['locality', 'administrativeArea'], 'postalCodePattern' => '\\d{5}', 'postalCodePrefix' => null, 'administrativeAreaType' => 'state', 'localityType' => 'city', 'dependentLocalityType' => 'village_township', 'postalCodeType' => 'postal', 'subdivisionDepth' => 1, 'defaults' => []];
