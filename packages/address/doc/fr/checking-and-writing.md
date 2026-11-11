<!-- phil-dev source-sha256=30a521fcb596ea78f8294c0b4bcd7adceaa047eaaf1cf883968fdf58dab05a64 -->
# Vérifier et écrire

## `AddressValidator`

```php
AddressValidator::violations($address);    // list<AddressViolation>, none when valid
AddressValidator::isValid($address);
```

| `Violation` | Quand |
|---|---|
| `Missing` | Un champ que le pays exige n'est pas donné |
| `Unused` | Un champ que le pays n'écrit pas est donné : un État en Allemagne |
| `InvalidPostalCode` | Le code postal ne correspond pas au motif du pays, ou ne commence pas comme les codes postaux de son État ou de sa province |
| `UnknownSubdivision` | Un État, une ville ou un quartier ne fait pas partie de ceux du pays, sous son parent |

Les subdivisions sont vérifiées niveau par niveau, aussi profondément que va le pays et que le permettent les données :
un niveau sans liste est accepté tel quel.

## `AddressFormatter`

```php
AddressFormatter::format($address, 'fr');           // "1234 rue Saint-Denis\nMontréal QC H2X 3K8"
AddressFormatter::lines($address, 'fr');            // the same, a line each
AddressFormatter::postalLabel($address, 'US', 'fr'); // capitals where the country wants them, and "CANADA" from abroad
```

Les lignes du format reçoivent les champs à leur place ; les lignes vides, les séparateurs restants et les lignes
formées uniquement de ponctuation disparaissent. Une subdivision qui a son propre code s'écrit par ce code — `QC`, `TX`
—, une subdivision qui n'en a pas par son nom, le nom local dans sa langue. Dans la langue du pays, c'est le format
local qui sert : celui du Japon commence par `〒`.

## schema.org

```php
SchemaOrg::of($address); // ['@type' => 'PostalAddress', 'streetAddress' => '1234 rue Saint-Denis', …]
```

`SchemaOrg::of()` donne un `PostalAddress` : les lignes d'adresse jointes, les localités, la région, le code postal et
le code du pays. Le destinataire du courrier est omis. Le tableau a un `@type` et pas de `@context`, pour s'intégrer à
un document JSON-LD plus large, celui d'un lieu ou d'une organisation.

## Stockage

`AddressArray::from()` écrit une adresse sous la forme `{"country": "CA", "addressLine1": "…", …}`. `to()` la relit et
lève `UnreadableAddress` pour toute autre chose. C'est ce que le type de colonne de `phil/address-bundle` stocke en
JSON.
