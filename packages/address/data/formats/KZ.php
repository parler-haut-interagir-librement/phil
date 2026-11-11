<?php

declare(strict_types=1);

// Generated from commerceguys/addressing's data (MIT License) by Phil\Address\Data\DataImporter.
return ['country' => 'KZ', 'format' => '%familyName %givenName %additionalName
%organization
%addressLine1
%addressLine2
%addressLine3
%postalCode, %locality
%administrativeArea', 'localFormat' => null, 'locale' => null, 'required' => ['addressLine1', 'locality', 'postalCode'], 'uppercase' => ['locality'], 'postalCodePattern' => '\\d{6}|[A-Z0-9]{7}', 'postalCodePrefix' => null, 'administrativeAreaType' => 'region', 'localityType' => 'city', 'dependentLocalityType' => 'suburb', 'postalCodeType' => 'postal', 'subdivisionDepth' => 1, 'defaults' => []];
