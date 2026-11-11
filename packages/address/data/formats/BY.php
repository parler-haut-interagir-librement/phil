<?php

declare(strict_types=1);

// Generated from commerceguys/addressing's data (MIT License) by Phil\Address\Data\DataImporter.
return ['country' => 'BY', 'format' => '%organization
%familyName %givenName %additionalName
%addressLine1
%addressLine2
%addressLine3
%postalCode, %locality
%administrativeArea', 'localFormat' => null, 'locale' => null, 'required' => ['addressLine1', 'locality'], 'uppercase' => ['locality'], 'postalCodePattern' => '\\d{6}', 'postalCodePrefix' => null, 'administrativeAreaType' => 'region', 'localityType' => 'city', 'dependentLocalityType' => 'suburb', 'postalCodeType' => 'postal', 'subdivisionDepth' => 0, 'defaults' => []];
