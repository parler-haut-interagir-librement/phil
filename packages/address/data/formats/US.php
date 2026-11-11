<?php

declare(strict_types=1);

// Generated from commerceguys/addressing's data (MIT License) by Phil\Address\Data\DataImporter.
return ['country' => 'US', 'format' => '%givenName %familyName
%organization
%addressLine1
%addressLine2
%addressLine3
%locality, %administrativeArea %postalCode', 'localFormat' => null, 'locale' => null, 'required' => ['addressLine1', 'locality', 'administrativeArea', 'postalCode'], 'uppercase' => ['locality', 'administrativeArea'], 'postalCodePattern' => '(\\d{5})(?:[ \\-](\\d{4}))?', 'postalCodePrefix' => null, 'administrativeAreaType' => 'state', 'localityType' => 'city', 'dependentLocalityType' => 'suburb', 'postalCodeType' => 'zip', 'subdivisionDepth' => 1, 'defaults' => []];
