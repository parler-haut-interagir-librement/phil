<?php

declare(strict_types=1);

// Generated from commerceguys/addressing's data (MIT License) by Phil\Address\Data\DataImporter.
return ['country' => 'IR', 'format' => '%organization
%givenName %familyName
%administrativeArea
%locality, %dependentLocality
%addressLine1
%addressLine2
%addressLine3
%postalCode', 'localFormat' => null, 'locale' => null, 'required' => ['addressLine1', 'locality', 'administrativeArea'], 'uppercase' => ['locality'], 'postalCodePattern' => '\\d{5}-?\\d{5}', 'postalCodePrefix' => null, 'administrativeAreaType' => 'province', 'localityType' => 'city', 'dependentLocalityType' => 'neighborhood', 'postalCodeType' => 'postal', 'subdivisionDepth' => 1, 'defaults' => []];
