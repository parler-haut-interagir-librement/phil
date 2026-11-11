<?php

declare(strict_types=1);

// Generated from commerceguys/addressing's data (MIT License) by Phil\Address\Data\DataImporter.
return ['country' => 'BR', 'format' => '%organization
%givenName %familyName
%addressLine1
%addressLine2
%addressLine3
%dependentLocality
%locality-%administrativeArea
%postalCode', 'localFormat' => null, 'locale' => null, 'required' => ['addressLine1', 'administrativeArea', 'locality', 'postalCode'], 'uppercase' => ['locality', 'administrativeArea'], 'postalCodePattern' => '\\d{5}-?\\d{3}', 'postalCodePrefix' => null, 'administrativeAreaType' => 'state', 'localityType' => 'city', 'dependentLocalityType' => 'neighborhood', 'postalCodeType' => 'postal', 'subdivisionDepth' => 2, 'defaults' => []];
