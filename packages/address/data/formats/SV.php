<?php

declare(strict_types=1);

// Generated from commerceguys/addressing's data (MIT License) by Phil\Address\Data\DataImporter.
return ['country' => 'SV', 'format' => '%givenName %familyName
%organization
%addressLine1
%addressLine2
%addressLine3
%postalCode-%locality
%administrativeArea', 'localFormat' => null, 'locale' => null, 'required' => ['addressLine1', 'locality', 'administrativeArea'], 'uppercase' => ['locality', 'administrativeArea', 'postalCode'], 'postalCodePattern' => 'CP [1-3][1-7][0-2]\\d', 'postalCodePrefix' => null, 'administrativeAreaType' => 'province', 'localityType' => 'city', 'dependentLocalityType' => 'suburb', 'postalCodeType' => 'postal', 'subdivisionDepth' => 1, 'defaults' => []];
