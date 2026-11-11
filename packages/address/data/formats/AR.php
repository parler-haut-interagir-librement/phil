<?php

declare(strict_types=1);

// Generated from commerceguys/addressing's data (MIT License) by Phil\Address\Data\DataImporter.
return ['country' => 'AR', 'format' => '%givenName %familyName
%organization
%addressLine1
%addressLine2
%addressLine3
%postalCode %locality
%administrativeArea', 'localFormat' => null, 'locale' => null, 'required' => ['addressLine1', 'locality'], 'uppercase' => ['addressLine1', 'addressLine2', 'addressLine3', 'locality', 'postalCode'], 'postalCodePattern' => '((?:[A-HJ-NP-Z])?\\d{4})([A-Z]{3})?', 'postalCodePrefix' => null, 'administrativeAreaType' => 'province', 'localityType' => 'city', 'dependentLocalityType' => 'suburb', 'postalCodeType' => 'postal', 'subdivisionDepth' => 1, 'defaults' => []];
