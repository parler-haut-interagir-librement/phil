<?php

declare(strict_types=1);

// Generated from commerceguys/addressing's data (MIT License) by Phil\Address\Data\DataImporter.
return ['country' => 'VI', 'format' => '%givenName %familyName
%organization
%addressLine1
%addressLine2
%addressLine3
%locality %postalCode', 'localFormat' => null, 'locale' => null, 'required' => ['addressLine1', 'locality', 'postalCode'], 'uppercase' => ['addressLine1', 'addressLine2', 'addressLine3', 'locality', 'familyName', 'additionalName', 'givenName', 'organization'], 'postalCodePattern' => '(008(?:(?:[0-4]\\d)|(?:5[01])))(?:[ \\-](\\d{4}))?', 'postalCodePrefix' => null, 'administrativeAreaType' => 'province', 'localityType' => 'city', 'dependentLocalityType' => 'suburb', 'postalCodeType' => 'zip', 'subdivisionDepth' => 0, 'defaults' => []];
