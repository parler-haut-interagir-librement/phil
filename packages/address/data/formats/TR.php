<?php

declare(strict_types=1);

// Generated from commerceguys/addressing's data (MIT License) by Phil\Address\Data\DataImporter.
return ['country' => 'TR', 'format' => '%givenName %familyName
%organization
%addressLine1
%addressLine2
%addressLine3
%postalCode %locality/%administrativeArea', 'localFormat' => null, 'locale' => null, 'required' => ['addressLine1', 'locality', 'administrativeArea', 'postalCode'], 'uppercase' => ['locality'], 'postalCodePattern' => '\\d{5}', 'postalCodePrefix' => null, 'administrativeAreaType' => 'province', 'localityType' => 'district', 'dependentLocalityType' => 'suburb', 'postalCodeType' => 'postal', 'subdivisionDepth' => 1, 'defaults' => []];
