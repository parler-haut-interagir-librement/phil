<?php

declare(strict_types=1);

// Generated from commerceguys/addressing's data (MIT License) by Phil\Address\Data\DataImporter.
return ['country' => 'CA', 'format' => '%givenName %familyName
%organization
%addressLine1
%addressLine2
%addressLine3
%locality %administrativeArea %postalCode', 'localFormat' => null, 'locale' => null, 'required' => ['addressLine1', 'locality', 'administrativeArea', 'postalCode'], 'uppercase' => ['addressLine1', 'addressLine2', 'addressLine3', 'locality', 'familyName', 'additionalName', 'givenName', 'organization', 'administrativeArea', 'postalCode'], 'postalCodePattern' => '[ABCEGHJKLMNPRSTVXY]\\d[ABCEGHJ-NPRSTV-Z] ?\\d[ABCEGHJ-NPRSTV-Z]\\d', 'postalCodePrefix' => null, 'administrativeAreaType' => 'province', 'localityType' => 'city', 'dependentLocalityType' => 'suburb', 'postalCodeType' => 'postal', 'subdivisionDepth' => 1, 'defaults' => []];
