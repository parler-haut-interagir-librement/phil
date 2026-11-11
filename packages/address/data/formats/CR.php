<?php

declare(strict_types=1);

// Generated from commerceguys/addressing's data (MIT License) by Phil\Address\Data\DataImporter.
return ['country' => 'CR', 'format' => '%givenName %familyName
%organization
%addressLine1
%addressLine2
%addressLine3
%administrativeArea, %locality
%postalCode', 'localFormat' => null, 'locale' => null, 'required' => ['addressLine1', 'locality', 'administrativeArea'], 'uppercase' => ['locality'], 'postalCodePattern' => '\\d{4,5}|\\d{3}-\\d{4}', 'postalCodePrefix' => null, 'administrativeAreaType' => 'province', 'localityType' => 'city', 'dependentLocalityType' => 'suburb', 'postalCodeType' => 'postal', 'subdivisionDepth' => 1, 'defaults' => []];
