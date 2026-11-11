<?php

declare(strict_types=1);

// Generated from commerceguys/addressing's data (MIT License) by Phil\Address\Data\DataImporter.
return ['country' => 'AU', 'format' => '%organization
%givenName %familyName
%addressLine1
%addressLine2
%addressLine3
%locality %administrativeArea %postalCode', 'localFormat' => null, 'locale' => null, 'required' => ['addressLine1', 'locality', 'administrativeArea', 'postalCode'], 'uppercase' => ['locality', 'administrativeArea'], 'postalCodePattern' => '\\d{4}', 'postalCodePrefix' => null, 'administrativeAreaType' => 'state', 'localityType' => 'suburb', 'dependentLocalityType' => 'suburb', 'postalCodeType' => 'postal', 'subdivisionDepth' => 1, 'defaults' => []];
