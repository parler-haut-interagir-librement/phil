<?php

declare(strict_types=1);

// Generated from commerceguys/addressing's data (MIT License) by Phil\Address\Data\DataImporter.
return ['country' => 'IN', 'format' => '%givenName %familyName
%organization
%addressLine1
%addressLine2
%addressLine3
%locality %postalCode
%administrativeArea', 'localFormat' => null, 'locale' => null, 'required' => ['addressLine1', 'locality', 'administrativeArea', 'postalCode'], 'uppercase' => ['locality'], 'postalCodePattern' => '\\d{6}', 'postalCodePrefix' => null, 'administrativeAreaType' => 'state', 'localityType' => 'city', 'dependentLocalityType' => 'suburb', 'postalCodeType' => 'pin', 'subdivisionDepth' => 1, 'defaults' => []];
