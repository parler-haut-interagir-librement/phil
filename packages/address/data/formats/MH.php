<?php

declare(strict_types=1);

// Generated from commerceguys/addressing's data (MIT License) by Phil\Address\Data\DataImporter.
return ['country' => 'MH', 'format' => '%givenName %familyName
%organization
%addressLine1
%addressLine2
%addressLine3
%locality %administrativeArea %postalCode', 'localFormat' => null, 'locale' => null, 'required' => ['addressLine1', 'locality', 'administrativeArea', 'postalCode'], 'uppercase' => ['addressLine1', 'addressLine2', 'addressLine3', 'locality', 'familyName', 'additionalName', 'givenName', 'organization', 'administrativeArea'], 'postalCodePattern' => '(969[67]\\d)(?:[ \\-](\\d{4}))?', 'postalCodePrefix' => null, 'administrativeAreaType' => 'state', 'localityType' => 'city', 'dependentLocalityType' => 'suburb', 'postalCodeType' => 'zip', 'subdivisionDepth' => 0, 'defaults' => []];
