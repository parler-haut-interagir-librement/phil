<?php

declare(strict_types=1);

// Generated from commerceguys/addressing's data (MIT License) by Phil\Address\Data\DataImporter.
return ['country' => 'PG', 'format' => '%givenName %familyName
%organization
%addressLine1
%addressLine2
%addressLine3
%locality %postalCode %administrativeArea', 'localFormat' => null, 'locale' => null, 'required' => ['addressLine1', 'locality', 'administrativeArea'], 'uppercase' => ['locality'], 'postalCodePattern' => '\\d{3}', 'postalCodePrefix' => null, 'administrativeAreaType' => 'province', 'localityType' => 'city', 'dependentLocalityType' => 'suburb', 'postalCodeType' => 'postal', 'subdivisionDepth' => 1, 'defaults' => []];
