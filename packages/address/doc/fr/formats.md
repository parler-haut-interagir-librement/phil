<!-- phil-dev source-sha256=a1c403cd96ff136473b97e435d116e7a83f440736e56ebe60288eca66c8d7997 -->
# Adresses et formats

## `Address`

```php
$address = Address::of('ca', ['addressLine1' => ' 1234 rue Saint-Denis ', 'postalCode' => 'H2X 3K8', 'addressLine2' => '']);
$address->country;                          // "CA"
$address->get(AddressField::PostalCode);    // "H2X 3K8"
$address->with(AddressField::Locality, 'Montréal');
```

Un pays, par son code ISO 3166-1 alpha-2, et des champs épurés de leurs espaces — un champ vide n'en est pas un, et
chacun compte au plus 255 caractères. Un pays ou un champ inconnu lève `InvalidAddress`. Une adresse n'est pas vérifiée
à sa création : `AddressValidator` indique si elle convient à son pays.

## Les champs

`AddressField` : `givenName`, `additionalName`, `familyName`, `organization` — le destinataire,
`AddressField::recipient()` —, `addressLine1` à `addressLine3`, `dependentLocality` (un quartier, un arrondissement),
`locality` (une ville), `administrativeArea` (un État, une province), `postalCode`, `sortingCode` (le CEDEX de la
France).

## `AddressFormat`

`AddressFormats::of($country)` donne le format d'un pays ; un pays sans format propre prend le format générique.

| Propriété | Contient |
|---|---|
| `format`, `localFormat`, `locale` | Les lignes, avec `%field` à leur place ; les lignes dans l'écriture du pays et sa langue — celles du Japon, en `ja` |
| `required`, `uppercase` | Les champs que le pays exige, ceux qu'une étiquette postale écrit en majuscules |
| `postalCodePattern`, `postalCodePrefix` | Le motif de ses codes postaux ; un préfixe que certains écrivent, `SE-` |
| `administrativeAreaType`, `localityType`, `dependentLocalityType`, `postalCodeType` | Le nom qu'il leur donne : `state`, `province`, `prefecture` ; `city`, `post_town` ; `zip`, `postal`, `eircode` |
| `subdivisionDepth`, `subdivisionFields()` | Le nombre de niveaux de subdivisions que nomment ses adresses, à partir de la région administrative |
| `defaults` | Des valeurs que les adresses d'un pays ont toujours : `Singapore` pour la localité de Singapour |

`fields()` donne les champs qu'écrit le format, dans l'ordre ; `uses()` et `requires()` interrogent sur un champ.
