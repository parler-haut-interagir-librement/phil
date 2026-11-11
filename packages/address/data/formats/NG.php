<?php

declare(strict_types=1);

// Generated from commerceguys/addressing's data (MIT License) by Phil\Address\Data\DataImporter.
return ['country' => 'NG', 'format' => '%givenName %familyName
%organization
%addressLine1
%addressLine2
%addressLine3
%dependentLocality
%locality %postalCode
%administrativeArea', 'localFormat' => null, 'locale' => null, 'required' => ['addressLine1', 'locality'], 'uppercase' => ['locality', 'administrativeArea'], 'postalCodePattern' => '\\d{6}|[A-Z]{2}[ -]?(?:0[1-9]|[1-9]\\d)[ -]?[A-Z0-9]{3}[ -]?[A-Z]{2}[ -]?(?:0[1-9]|[1-9]\\d)', 'postalCodePrefix' => null, 'administrativeAreaType' => 'state', 'localityType' => 'city', 'dependentLocalityType' => 'suburb', 'postalCodeType' => 'postal', 'subdivisionDepth' => 1, 'defaults' => []];
