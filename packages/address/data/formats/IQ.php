<?php

declare(strict_types=1);

// Generated from commerceguys/addressing's data (MIT License) by Phil\Address\Data\DataImporter.
return ['country' => 'IQ', 'format' => '%organization
%givenName %familyName
%addressLine1
%addressLine2
%addressLine3
%locality, %administrativeArea
%postalCode', 'localFormat' => null, 'locale' => null, 'required' => ['addressLine1', 'locality', 'administrativeArea'], 'uppercase' => ['locality', 'administrativeArea'], 'postalCodePattern' => '\\d{5}', 'postalCodePrefix' => null, 'administrativeAreaType' => 'province', 'localityType' => 'city', 'dependentLocalityType' => 'suburb', 'postalCodeType' => 'postal', 'subdivisionDepth' => 0, 'defaults' => []];
