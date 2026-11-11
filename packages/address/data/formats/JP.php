<?php

declare(strict_types=1);

// Generated from commerceguys/addressing's data (MIT License) by Phil\Address\Data\DataImporter.
return ['country' => 'JP', 'format' => '%familyName %givenName
%organization
%addressLine1
%addressLine2
%addressLine3
%locality, %administrativeArea
%postalCode', 'localFormat' => '〒%postalCode
%administrativeArea%locality
%addressLine1
%addressLine2
%addressLine3
%organization
%familyName %givenName', 'locale' => 'ja', 'required' => ['addressLine1', 'administrativeArea', 'postalCode'], 'uppercase' => ['administrativeArea'], 'postalCodePattern' => '\\d{3}-?\\d{4}', 'postalCodePrefix' => null, 'administrativeAreaType' => 'prefecture', 'localityType' => 'city', 'dependentLocalityType' => 'suburb', 'postalCodeType' => 'postal', 'subdivisionDepth' => 1, 'defaults' => []];
