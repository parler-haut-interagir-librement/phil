<!-- phil-dev source-sha256=6205d60dd1b785bfe73026989bdfa3007f408fbde476ec08408d4076eb9f3011 -->
# phil/address

**Des adresses postales vérifiées et écrites comme chaque pays les écrit.**

phil/address représente une adresse postale par un objet-valeur et connaît le format d'adresse de chaque pays. Un format
donne l'ordre des champs, ceux que le pays exige, le motif de ses codes postaux, le nom qu'il donne à ses États et à ses
villes, et ses lignes dans sa propre écriture. La bibliothèque connaît les subdivisions jusqu'à trois niveaux, avec
leurs noms locaux. `AddressValidator` vérifie une adresse par rapport à son pays : champs manquants ou inutilisés, codes
postaux, subdivisions inconnues. `AddressFormatter` l'écrit pour une page ou pour une étiquette postale. `SchemaOrg`
donne un `PostalAddress`, et `AddressArray` un tableau pour une seule colonne JSON. Elle ne fait ni géocodage ni
autocomplétion, et ne vérifie pas qu'une rue existe.

Elle s'adresse à toute application qui reçoit des adresses de plus d'un pays : une boutique qui livre à l'étranger, un
répertoire de lieux, le lieu d'un événement. Un seul jeu de champs avec un « State » et un « ZIP code » convient mal à
un pays et encore moins aux autres. Ici, une adresse québécoise et une adresse japonaise suivent chacune leurs propres
règles.

C'est une simple bibliothèque PHP, utilisable dans tout code PHP. Dans une application Symfony, tournez-vous vers
`phil/address-bundle`, qui ajoute un formulaire qui suit le pays choisi, une contrainte de validation, un type de
colonne Doctrine et des filtres Twig.

Les formats et les subdivisions sont ceux de commerceguys/addressing, importés sous forme de fichiers PHP par le propre
`DataImporter` de la bibliothèque. La bibliothèque lit ces fichiers à l'exécution et ne dépend jamais de
commerceguys/addressing. Les noms de pays viennent de `symfony/intl`, dans toutes les langues qu'il connaît.

## État

| Partie | État |
|---|---|
| `Address`, `AddressField`, `AddressFormat`, `AddressFormats` | Implémenté et testé |
| `Subdivision`, `Subdivisions` : trois niveaux, noms locaux | Implémenté et testé |
| `AddressValidator` : champs obligatoires, inutilisés, codes postaux, subdivisions | Implémenté et testé |
| `AddressFormatter` : lignes, formats locaux, étiquettes postales | Implémenté et testé |
| `SchemaOrg` : un `PostalAddress` de schema.org | Implémenté et testé |
| `AddressArray` ; `DataImporter` et les données de commerceguys/addressing v2.3.2 | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis et installation. |
| [Adresses et formats](doc/fr/formats.md) | `Address`, les champs, le format de chaque pays. |
| [Subdivisions](doc/fr/subdivisions.md) | États, provinces, villes et quartiers, et leurs noms. |
| [Vérifier et écrire](doc/fr/checking-and-writing.md) | `AddressValidator`, `AddressFormatter`, schema.org, stockage. |
| [Données](doc/fr/data.md) | L'origine des données, leur licence et leur mise à jour. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports, les arguments et les données qu'une application choisit ou remplace. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque et le seuil de mutation. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/address
```

```php
use Phil\Address\Address;
use Phil\Address\AddressFormatter;
use Phil\Address\AddressValidator;

$address = Address::of('CA', [
    'addressLine1' => '1234 rue Saint-Denis',
    'locality' => 'Montréal',
    'administrativeArea' => 'Québec',
    'postalCode' => 'H2X 3K8',
]);

AddressValidator::isValid($address);       // true
AddressFormatter::format($address, 'fr');  // "1234 rue Saint-Denis\nMontréal QC H2X 3K8"
```

## Prérequis

PHP 8.5 ou plus récent avec `ext-mbstring` ; `symfony/intl` 8.2.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE). Les données de `data/` viennent de commerceguys/addressing, sous licence MIT : voir [NOTICE](NOTICE)
et [data/LICENSE](data/LICENSE).
