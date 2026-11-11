<?php

declare(strict_types=1);

// Generated from commerceguys/addressing's data (MIT License) by Phil\Address\Data\DataImporter.
return ['country' => 'FR', 'format' => '%organization
%givenName %familyName
%addressLine1
%addressLine2
%addressLine3
%postalCode %locality %sortingCode', 'localFormat' => null, 'locale' => null, 'required' => ['addressLine1', 'locality', 'postalCode'], 'uppercase' => ['locality', 'sortingCode'], 'postalCodePattern' => '\\d{2} ?\\d{3}', 'postalCodePrefix' => null, 'administrativeAreaType' => 'region', 'localityType' => 'city', 'dependentLocalityType' => 'suburb', 'postalCodeType' => 'postal', 'subdivisionDepth' => 0, 'defaults' => []];
